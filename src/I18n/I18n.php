<?php

namespace bdk;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Internationalization / translation
 *
 * We iterate through user's preferred locales until matching translation file is found
 * This is done on a per-domain basis
 * If we match on a locale with a region, we'll attempt to merge with non-region specific
 *    ie, we'll merge 'en_US' with 'en'
 * If no match was found, we'll try 'localeFallback'.   localFallback is not otherwise used/merged
 *
 * if user specifies locale with a region, we'll add the non-region locale to the end of the list
 *   ie, if user specifies 'en-GB', we'll also check for 'en' (after exhausting all user-specified locales)
 *
 * we support the following file types:
 * - csv
 * - ini / properties
 * - json
 * - php
 *
 *  .ini (& .properties), .json, & .php data structures are flattened
 *  so keys result in the format 'depth1.depth2.depth3...'
 *
 * You may register additional file type parsers with `registerExtParser('ext', callable)`
 * Ie we don't support yaml, but you could register a yaml parser
 */
class I18n
{
    /**
     * Configuration
     *
     * Best matching language is done on a per-domain basis
     * May specify domain specific filepath templates with "domainFilepath"
     *   (not necessary to include the {domain} placeholder)
     *
     * localFirstChoice: will be given top priority..  perhaps comes from a route attribute
     * localeFallback: if no translation found, we'll try this locale
     *
     * @var array
     */
    private $cfg = array(
        'defaultDomain' => 'messages',
        'domainFilepath' => array(),
        'filepath' => './trans/{domain}/{locale}.php',
        'localeFallback' => 'en',
        'localeFirstChoice' => null,
        'priority' => array(
            'cfg:localeFirstChoice',
            'request:get.lang',
            'request:session.lang',
            'request:cookie.lang',
            'request:header.Accept-Language',
            'cfg:localeFallback',
        ),
    );

    /** @var array domain / locale / strings */
    private $data = array();

    /**
     * Domain to user locale mapping
     *
     * @var array
     */
    private $domainLocale = array();

    /** @var ServerRequestInterface */
    private $serverRequest;

    /** @var list<string> User's preferred locales */
    private $userLocales = [];

    /** @var array<string,callable> */
    private $extParsers = array();

    /** @var string */
    private $messageFormatterClass = 'MessageFormatter';

    /**
     * Constructor
     *
     * @param ServerRequestInterface $serverRequest ServerRequest instance
     * @param array                  $cfg           Configuration
     */
    public function __construct(ServerRequestInterface $serverRequest, $cfg = array())
    {
        $this->serverRequest = $serverRequest;
        $this->cfg = \array_merge($this->cfg, $cfg);
        $this->messageFormatterClass = \class_exists('MessageFormatter', false) && PHP_VERSION_ID >= 50500
            ? 'MessageFormatter'
            : 'bdk\I18n\MessageFormatter';
        $this->userLocales = $this->getUserLocales();
        $this->registerExtParser('csv', array($this, 'parseExtCsv'));
        $this->registerExtParser('json', static function ($filepath) {
            $data = \json_decode(\file_get_contents($filepath), true);
            return self::arrayFlatten($data);
        });
        $this->registerExtParser('php', static function ($filepath) {
            $data = include $filepath;
            return self::arrayFlatten($data);
        });
        $this->registerExtParser('ini', array($this, 'parseExtIni'));
        $this->registerExtParser('properties', array($this, 'parseExtIni'));
    }

    /**
     * Flatten array
     *
     * Resulting array will have keys in the format 'depth1.depth2.depth3'
     *
     * This utility method is made public for use in custom file parsers
     *
     * @param array  $array    array to flatten
     * @param string $joinWith ('.') string to join keys with
     * @param string $prefix   @internal
     *
     * @return array
     */
    public static function arrayFlatten($array, $joinWith = '.', $prefix = '')
    {
        $return = array();
        foreach ($array as $key => $value) {
            $newKey = $prefix . $key;
            \is_array($value) === false
                ? $return[$newKey] = $value
                : $return = \array_merge($return, self::arrayFlatten($value, $joinWith, $newKey . $joinWith));
        }
        return $return;
    }

    /**
     * Get the applied locale for the given domain
     *
     * @param string|null $domain domain (defaults to configured defaultDomain)
     *
     * @return string|false
     */
    public function getLocale($domain = null)
    {
        $domain = $domain ?: $this->cfg['defaultDomain'];
        if (isset($this->domainLocale[$domain])) {
            return $this->domainLocale[$domain];
        }
        foreach ($this->userLocales as $locale) {
            $filepath = $this->filepathDomainLocale($domain, $locale);
            if (\is_file($filepath)) {
                $this->domainLocale[$domain] = $locale;
                return $locale;
            }
        }
        $this->domainLocale[$domain] = false;
        return false;
    }

    /**
     * Get user preferred locales
     *
     * returns a prioritized list of locales in the format 'en_US'
     *
     * @return string[]
     */
    public function getUserLocales()
    {
        $userLocales = array();
        foreach ($this->cfg['priority'] as $priority) {
            list($method, $params) = \array_replace(['', ''], \explode(':', $priority));
            $params = \explode('.', $params);
            $method = 'getLocaleFrom' . \ucfirst($method);
            $locales = \call_user_func_array([$this, $method], $params);
            $userLocales = \array_merge($userLocales, (array) $locales);
        }
        $userLocales = \array_map(function ($locale) {
            $parsed = $this->parseLocale($locale);
            return $parsed
                ? \implode('_', \array_filter([$parsed['lang'], $parsed['region']]))
                : false;
        }, $userLocales);
        $userLocales = \array_filter($userLocales);
        $userLocales = $this->userLocaleInsertFallbacks($userLocales);
        return \array_values(\array_unique($userLocales));
    }

    /**
     * Register extension parser.
     *
     * Define a custom file-extension parser
     *
     * @param string   $ext      File extension (ie 'yml')
     * @param callable $callable A callable that returns key => value array
     *
     * @return static
     */
    public function registerExtParser($ext, $callable)
    {
        $this->extParsers[$ext] = $callable;
        return $this;
    }

    /**
     * Translate a string
     *
     * @param string $str    string to translate
     * @param array  $args   optional arguments
     * @param string $domain optional domain
     * @param string $locale optional locale
     *
     * @return string
     */
    public function trans($str, array $args = array(), $domain = null, $locale = null)
    {
        $domain = $domain ?: $this->cfg['defaultDomain'];
        $locale = $locale ?: $this->getLocale($domain);
        $this->loadDomainLocale($domain, $locale);
        $str = isset($this->data[$domain][$locale][$str])
            ? $this->data[$domain][$locale][$str]
            : $str;
        if (empty($args)) {
            return $str;
        }
        return \call_user_func([$this->messageFormatterClass, 'formatMessage'], $locale, $str, $args);
    }

    /**
     * Get the filepath for a domain and locale
     *
     * Filepath is not validated
     *
     * @param string $domain Domain ('messages')
     * @param string $locale Locale ('en_US')
     *
     * @return string
     */
    private function filepathDomainLocale($domain, $locale)
    {
        $filepathTemplate = isset($this->cfg['domainFilepath'][$domain])
            ? $this->cfg['domainFilepath'][$domain]
            : $this->cfg['filepath'];
        $filepath = \strtr($filepathTemplate, array(
            '{domain}' => $domain,
            '{locale}' => $locale,
        ));
        return \preg_replace('/^.\//', __DIR__ . '/', $filepath);
    }

    /**
     * Load the given domain and locale
     *
     * @param string $domain domain
     * @param string $locale locale
     *
     * @return void
     */
    private function loadDomainLocale($domain, $locale)
    {
        if (isset($this->data[$domain][$locale])) {
            // already loaded
            return;
        }
        if (empty($locale)) {
            // locale not found
            return;
        }
        $filepath = $this->filepathDomainLocale($domain, $locale);
        $this->data[$domain][$locale] = $this->loadFile($filepath);
        $parsed = $this->parseLocale($locale);
        if ($parsed['region']) {
            // merge with non-region specific
            $filepath = $this->filepathDomainLocale($domain, $parsed['lang']);
            $this->data[$domain][$locale] = \array_merge(
                $this->loadFile($filepath),
                $this->data[$domain][$locale]
            );
        }
    }

    /**
     * Get translations from given filepath
     *
     * @param string $filepath file path
     *
     * @return array
     */
    private function loadFile($filepath)
    {
        if (\is_file($filepath) === false) {
            return array();
        }
        $ext = \substr(\strrchr($filepath, '.'), 1);
        return isset($this->extParsers[$ext])
            ? $this->extParsers[$ext]($filepath)
            : array();
    }

    /**
     * Get cfg value
     *
     * @param string $key cfg key (localeFirstChoice / localeFallback)
     *
     * @return string
     *
     * @disregard unused private function
     */
    private function getLocaleFromCfg($key)
    {
        return $this->cfg[$key];
    }

    /**
     * Get value from request
     *
     * @param string $var request var (cookie, get, session)
     * @param string $key key (ie 'lang')
     *
     * @return string|null
     *
     * @disregard unused private function
     */
    private function getLocaleFromRequest($var, $key)
    {
        $array = array();
        switch ($var) {
            case 'cookie':
                $array = $this->serverRequest->getCookieParams();
                break;
            case 'get':
                $array = $this->serverRequest->getQueryParams();
                break;
            case 'header':
                $headerVal = $this->serverRequest->getHeaderLine($key);
                return $this->parseHeaderVal($headerVal);
            case 'post':
                $array = $this->serverRequest->getParsedBody();
                break;
            case 'session':
                $array = isset($_SESSION) ? $_SESSION : array();
                break;
        }
        return isset($array[$key])
            ? $array[$key]
            : null;
    }

    /**
     * Parse csv translation file
     *
     * @param string $filepath file path
     *
     * @return array
     *
     * @disregard
     */
    private static function parseExtCsv($filepath)
    {
        try {
            $handle = \fopen($filepath, 'r');
        } catch (\Exception $e) {
            $handle = false;
        }
        if ($handle === false) {
            return array();
        }
        $return = array();
        while (($data = \fgetcsv($handle, 2048, ',', '"', '\\')) !== false) {
            if (\count($data) === 1 && empty($data[0])) {
				// blank line
				continue;
            }
            $data = \array_map('trim', $data);
            $return[$data[0]] = $data[1];
        }
        \fclose($handle);
        return $return;
    }

    /**
     * Parse ini/properties file
     *
     * @param string $filepath file path
     *
     * @return array
     */
    private static function parseExtIni($filepath)
    {
        $parsed = \parse_ini_file($filepath, true) ?: array();
        return self::arrayFlatten($parsed);
    }

    /**
     * Parse Accept-Language header value
     *
     * @param string $locales Accept-Language header value
     *
     * @return array
     */
    private static function parseHeaderVal($locales)
    {
        $userLocales = array();
        $locales = \array_filter(\preg_split('/,\s*/', $locales));
        foreach ($locales as $locale) {
            list($locale, $priority) = \array_replace(['', 1], \explode(';q=', $locale, 2));
            if (\is_numeric($priority) === false) {
                continue;
            }
            $userLocales[$locale] = $priority;
        }
        \arsort($userLocales, SORT_NUMERIC);
        return \array_keys($userLocales);
    }

    /**
     * Parse locale/lang into lang and region
     *
     * @param string $locale locale/language
     *
     * @return array|false
     */
    private static function parseLocale($locale)
    {
        \preg_match('/^
            ([a-z]{2,3})  # language
            (?: [_-] ([a-z]{2}|[0-9]{1,3}) )?  # country-region
            (?:\.[a-z]+(-?[0-9]+)?)?  # code-page
            $/xi', \trim($locale), $matches);
        if (empty($matches)) {
            return false;
        }
        $matches = \array_replace(['', ''], \array_slice($matches, 1, 2));
        return array(
            'lang' => \strtolower($matches[0]),
            'region' => \strtoupper($matches[1]),
        );
    }

    /**
     * Insert non-region specific languages into list
     *
     * given ['en_US', 'fr_FR', 'en_GB'], assume 'en' and 'fr' are also acceptable
     * list will become ['en_US', 'fr_FR', 'en_GB', en', 'fr']
     *
     * @param array $userLocales user preferred languages
     *
     * @return array
     */
    private function userLocaleInsertFallbacks($userLocales)
    {
        $fallback = \end($userLocales) === $this->cfg['localeFallback']
            ? \array_pop($userLocales)
            : null;
        $localesSansRegion = [];
        foreach ($userLocales as $locale) {
            $lang = \explode('_', $locale)[0];
            $localesSansRegion[] = $lang;
        }
        $userLocales = \array_merge($userLocales, $localesSansRegion, [$fallback]);
        return \array_unique($userLocales);
    }
}
