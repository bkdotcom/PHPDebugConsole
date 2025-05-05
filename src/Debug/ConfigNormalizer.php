<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.5
 */

namespace bdk\Debug;

use bdk\Debug;

/**
 * Configuration manager
 */
class ConfigNormalizer
{
    /** @var array<string,list<string|list<string>>> */
    protected $configKeys = array(
        'abstracter' => [
            'brief',
            'caseAttributeCollect',
            'caseAttributeOutput',
            'caseCollect',
            'caseOutput',
            'constAttributeCollect',
            'constAttributeOutput',
            'constCollect',
            'constOutput',
            'fullyQualifyPhpDocType',
            'interfacesCollapse',
            'maxDepth',
            'methodAttributeCollect',
            'methodAttributeOutput',
            'methodCollect',
            'methodDescOutput',
            'methodOutput',
            'methodStaticVarCollect',
            'methodStaticVarOutput',
            'objAttributeCollect',
            'objAttributeOutput',
            'objectSectionOrder',
            'objectsExclude',
            'objectSort',
            'objectsWhitelist',
            'paramAttributeCollect',
            'paramAttributeOutput',
            'phpDocCollect',
            'phpDocOutput',
            'propAttributeCollect',
            'propAttributeOutput',
            'propVirtualValueCollect',
            'stringMaxLen',
            'stringMaxLenBrief',
            'stringMinLen',
            'toStringOutput',
            'useDebugInfo',
        ],
        'debug' => [
            // any key not found falls under 'debug'...
        ],
        'errorHandler' => array(
            'continueToPrevHandler',
            'errorFactory',
            'errorReporting',
            'errorThrow',
            'onError',
            'onFirstError',
            'onEUserError',
            'suppressNever',
            'enableEmailer',
            'emailer' => [
                'dateTimeFmt',
                'emailBacktraceDumper',
                // 'emailFrom',
                // 'emailFunc',
                'emailMask',
                'emailMin',
                'emailThrottledSummary',
                'emailTraceMask',
                // 'emailTo',
            ],
            'enableStats',
            'stats' => [
                'dataStoreFactory',
                'errorStatsFile',
            ],
        ),
        'routeHtml' => [
            'css',
            'drawer',
            'filepathCss',
            'filepathMicroDom',
            'filepathScript',
            'outputCss',
            'outputScript',
            'sidebar',
            'tooltip',
        ],
        'routeStream' => [
            'ansi',
            'stream',
        ],
    );

    /** @var Debug */
    protected $debug;

    /**
     * Constructor
     *
     * @param Debug $debug Debug instance
     */
    public function __construct(Debug $debug)
    {
        $this->debug = $debug;
    }

    /**
     * Get the config "categories"
     *
     * @return string[]
     */
    public function serviceKeys()
    {
        return \array_keys($this->configKeys);
    }

    /**
     * Normalizes cfg..  groups values by class
     *
     * converts
     *   array(
     *      'methodCollect' => false,
     *      'emailMask' => 123,
     *   )
     * to
     *   array(
     *       'abstracter' => array(
     *           'methodCollect' => false,
     *       ),
     *       'errorHandler' => array(
     *           'emailer' => array(
     *               'emailMask' => 123,
     *           ),
     *       ),
     *   )
     *
     * @param array $cfg config array
     *
     * @return array
     */
    public function normalizeArray(array $cfg)
    {
        $return = array();
        \array_walk($cfg, function ($v, $path) use (&$return) {
            $ref = &$return;
            $path = $this->normalizePath($path);
            foreach ($path as $k) {
                if (!isset($ref[$k])) {
                    $ref[$k] = array();
                }
                $ref = &$ref[$k];
            }
            $ref = \is_array($v)
                ? \array_merge($ref, $v)
                : $v;
        });
        return $return;
    }

    /**
     * Normalize string path
     * Returns one of
     *     ['*']             all config values grouped by class
     *     ['class']         we want all config values for class
     *     ['class', key...] we want specific value from this class
     *
     * 'class' may be debug
     *
     * @param array|string|null $path path
     *
     * @return array
     */
    public function normalizePath($path)
    {
        if (\is_string($path)) {
            $path = \array_filter(\preg_split('#[\./]#', $path), 'strlen');
        }
        if (\in_array($path, [null, []], true) || $path[0] === '*') {
            return ['*'];
        }
        if (\end($path) === '*') {
            \array_pop($path);
        }
        return isset($this->configKeys[$path[0]])
            ? $path
            : $this->normalizePathFind($path);
    }

    /**
     * Find config key's full path in this->configKeys
     *
     * @param array $path Config path
     *
     * @return array
     */
    private function normalizePathFind($path)
    {
        if (\count($path) > 1) {
            \array_unshift($path, 'debug');
            return $path;
        }
        $pathNew = $this->debug->arrayUtil->searchRecursive($path[0], $this->configKeys, true);
        $pathNew =  $pathNew
            ? $pathNew
            : \array_merge(['debug'], $path);
        if (\end($pathNew) !== \end($path)) {
            \array_pop($pathNew);
            $pathNew[] = \end($path);
        }
        return $pathNew;
    }
}
