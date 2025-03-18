<?php

namespace bdk;

use bdk\I18n\FileLoader;
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
 * Ie we don't support yaml out of the box, but you could register a yaml parser
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
     * localFirstChoice: will be given top priority..  perhaps comes from a route attribute, or framework determined locale
     * localeFallback: if no translation found, we'll try this locale
     *
     * @var array
     */
    private $cfg = array(
        'defaultDomain' => 'messages',
        'displayNameFromData' => false, // availableLocales()...
                                        //   should it load each file to get locale.displayName value ?
                                        //   will fallback to Locale::getDisplayName() if available
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

    /** @var array domain => locale => strings */
    private $data = array();

    /**
     * Domain to user locale mapping
     *
     * @var array
     */
    private $domainLocale = array();

    /** @var FileLoader */
    private $fileLoader;

    /** @var classname */
    private $messageFormatterClass = 'MessageFormatter';

    /** @var ServerRequestInterface */
    private $serverRequest;

    /** @var list<string> User's preferred locales */
    private $userLocales = [];

    /**
     * Constructor
     *
     * @param ServerRequestInterface $serverRequest ServerRequest instance
     * @param array                  $cfg           Configuration
     */
    public function __construct(ServerRequestInterface $serverRequest, $cfg = array())
    {
        $this->fileLoader = new FileLoader();
        $this->serverRequest = $serverRequest;
        $this->messageFormatterClass = \class_exists('MessageFormatter', false) && PHP_VERSION_ID >= 50500
            ? 'MessageFormatter'
            : 'bdk\I18n\MessageFormatter';
        $this->setCfg($cfg);
    }

    /**
     * Get a list of available locales for the given domain
     *
     * This is useful for displaying a language selector to the user
     *
     * @param string $domain Domain (defaults to configured defaultDomain)
     *
     * @return array
     */
    public function availableLocales($domain = null)
    {
        $domain = $domain ?: $this->cfg['defaultDomain'];
        $template = $this->filepathTemplate($domain);
        $globTemplate = \strtr($template, array(
            '{domain}' => $domain,
            '{locale}' => '*',
        ));
        $regex = '#' . \str_replace('\\*', '(\w+)', \preg_quote($globTemplate, '#')) . '#';
        $locales = array();
        $filepaths = \glob($globTemplate);
        \array_walk($filepaths, function ($filepath) use ($domain, &$locales, $regex) {
            \preg_match($regex, $filepath, $matches);
            $locale = $matches[1];
            $locales[$locale] = $this->displayname($locale, $domain);
        });
        return $locales;
    }

    /**
     * Get the applied locale for the given domain
     *
     * @param string|null $domain Domain (defaults to configured defaultDomain)
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
        $this->fileLoader->registerExtParser($ext, $callable);
        return $this;
    }

    /**
     * Set one or more config values
     *
     *    setCfg('key', 'value')
     *    setCfg(array('k1'=>'v1', 'k2'=>'v2'))
     *
     * @param array|string $mixed key=>value array or key
     * @param mixed        $value new value
     *
     * @return static
     */
    public function setCfg($mixed, $value = null)
    {
        if (\is_array($mixed) === false) {
            $mixed = array($mixed => $value);
        }
        $this->cfg = \array_replace($this->cfg, $mixed);
        $noResetKeys = ['defaultDomain', 'displayNameFromData'];
        $haveResetKey = \count(\array_diff(\array_keys($mixed), $noResetKeys)) > 0;
        if ($haveResetKey) {
            $this->data = array();
            $this->domainLocale = array();
            $this->userLocales = $this->userLocales();
        }
        return $this;
    }

    /**
     * Translate a string
     *
     * @param string $str    string to translate
     * @param array  $args   optional arguments
     * @param string $domain optional domain (defaults to defaultDomain)
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
        return $args
            ? \call_user_func([$this->messageFormatterClass, 'formatMessage'], $locale, $str, $args)
            : $str;
    }

    /**
     * Get user preferred locales (in addition to configured firstChoice and fallback)
     *
     * Returns a prioritized list of locales in the format 'en_US'
     *
     * @return string[]
     */
    public function userLocales()
    {
        $userLocales = array();
        foreach ($this->cfg['priority'] as $priority) {
            list($method, $params) = \array_replace(['', ''], \explode(':', $priority));
            $method = 'getLocaleFrom' . \ucfirst($method);
            $params = \explode('.', $params);
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
     * Return the display name for the given locale
     *
     * @param string $locale Locale ('en_US')
     * @param string $domain Domain (defaults to configured defaultDomain)
     *
     * @return string
     */
    private function displayName($locale, $domain = null)
    {
        $domain = $domain ?: $this->cfg['defaultDomain'];
        $messages = array();
        if ($this->cfg['displayNameFromData']) {
            $filepath = $this->filepathDomainLocale($domain, $locale);
            $messages = $this->fileLoader->load($filepath);
        }
        $displayName = null;
        if (isset($messages['locale.displayName'])) {
            $displayName = $messages['locale.displayName'];
        } elseif (\class_exists('Locale')) {
            $displayName = \Locale::getDisplayName($locale, $locale);
        }
        return $displayName ?: $locale;
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
        return \strtr($this->filepathTemplate($domain), array(
            '{domain}' => $domain,
            '{locale}' => $locale,
        ));
    }

    /**
     * Return the filepath template for the given domain
     *
     * @param string $domain Domain ('messages')
     *
     * @return string
     */
    private function filepathTemplate($domain)
    {
        $template = isset($this->cfg['domainFilepath'][$domain])
            ? $this->cfg['domainFilepath'][$domain]
            : $this->cfg['filepath'];
        return \preg_replace('/^.\//', __DIR__ . '/', $template);
    }

    /**
     * Load the given domain + locale messages
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
        $this->data[$domain][$locale] = $this->fileLoader->load($filepath);
        $parsed = $this->parseLocale($locale);
        if ($parsed['region']) {
            // merge with non-region specific
            $filepath = $this->filepathDomainLocale($domain, $parsed['lang']);
            $this->data[$domain][$locale] = \array_merge(
                $this->fileLoader->load($filepath),
                $this->data[$domain][$locale]
            );
        }
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
     * Parse Accept-Language header value
     *
     * @param string $headerValue Accept-Language header value
     *
     * @return array
     */
    private static function parseHeaderVal($headerValue)
    {
        $locales = array();
        $headerValues = \array_filter(\preg_split('/,\s*/', $headerValue));
        foreach ($headerValues as $value) {
            list($locale, $priority) = \array_replace(['', 1], \explode(';q=', $value, 2));
            if (\is_numeric($priority) === false) {
                continue;
            }
            $locales[$locale] = $priority;
        }
        \arsort($locales, SORT_NUMERIC);
        return \array_keys($locales);
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
        $firstChoices = [];
        if (\reset($userLocales) === $this->cfg['localeFirstChoice']) {
            $firstChoice = \array_shift($userLocales);
            $lang = \explode('_', $firstChoice)[0];
            $firstChoices = [$firstChoice, $lang];
        }
        $localesSansRegion = [];
        foreach ($userLocales as $locale) {
            $lang = \explode('_', $locale)[0];
            $localesSansRegion[] = $lang;
        }
        $userLocales = \array_merge($firstChoices, $userLocales, $localesSansRegion, [$fallback]);
        return \array_unique($userLocales);
    }
}
