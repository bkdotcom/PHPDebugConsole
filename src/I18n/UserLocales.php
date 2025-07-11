<?php

/**
 * @package   bdk/i18n
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2025-2025 Brad Kent
 * @since     1.0
 */

namespace bdk\I18n;

use bdk\I18n;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Get user prefered locales via ServerRequest and I18n config
 */
class UserLocales
{
    /** @var I18n */
    private $i18n;

    /** @var ServerRequestInterface */
    private $serverRequest;

    /**
     * Constructor
     *
     * @param ServerRequestInterface $serverRequest ServerRequest instance
     * @param I18n                   $i18n          I18n instance
     */
    public function __construct(ServerRequestInterface $serverRequest, I18n $i18n)
    {
        $this->i18n = $i18n;
        $this->serverRequest = $serverRequest;
    }

    /**
     * Get user preferred locales (in addition to configured firstChoice and fallback)
     *
     * Returns a prioritized list of locales in the format 'en_US'
     *
     * @return string[]
     */
    public function get()
    {
        $userLocales = array();
        foreach ($this->i18n->getCfg('priority') as $priority) {
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
     * Parse locale/lang into lang and region
     *
     * @param string $locale locale/language
     *
     * @return array|false
     */
    public static function parseLocale($locale)
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
        return $this->i18n->getCfg($key);
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
        $fallback = \end($userLocales) === $this->i18n->getCfg('localeFallback')
            ? \array_pop($userLocales)
            : null;
        $firstChoices = [];
        if (\reset($userLocales) === $this->i18n->getCfg('localeFirstChoice')) {
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
