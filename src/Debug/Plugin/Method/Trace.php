<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0b1
 */

namespace bdk\Debug\Plugin\Method;

use bdk\Backtrace;
use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\LogEntry;
use bdk\Debug\Plugin\CustomMethodTrait;
use bdk\PubSub\SubscriberInterface;

/**
 * Trace method
 */
class Trace implements SubscriberInterface
{
    use CustomMethodTrait;

    /** @var string[] */
    protected $methods = [
        'trace',
    ];

    /** @var string[] */
    private $commonFilePrefix = '';

    /**
     * Constructor
     *
     * @codeCoverageIgnore
     */
    public function __construct()
    {
    }

    /**
     * Log a stack trace
     *
     * Essentially PHP's `debug_backtrace()`, but displayed as a table
     *
     * Params may be passed in any order
     *
     * @param bool             $inclContext (`false`) Include code snippet
     * @param string           $caption     ('trace') Specify caption for the trace table
     * @param int              $limit       (0) limit the number of stack frames returned.  By default (limit = 0) all stack frames are collected
     * @param array|\Exception $trace       Optionally specify trace
     *
     * @return Debug
     *
     * @since 3.3 added limit argument
     */
    public function trace($inclContext = false, $caption = null, $limit = 0, $trace = null)
    {
        if (!$this->debug->getCfg('collect', Debug::CONFIG_DEBUG)) {
            return $this->debug;
        }
        $argsDefault = $this->debug->rootInstance->reflection->getMethodDefaultArgs(__METHOD__);
        $logEntry = new LogEntry(
            $this->debug,
            __FUNCTION__,
            $this->getTraceArgs(\func_get_args(), $argsDefault),
            array(),
            $argsDefault,
            [
                'caption',
                'inclContext',
                'limit',
                'trace',
            ]
        );
        $this->doTrace($logEntry);
        $this->debug->log($logEntry);
        return $this->debug;
    }

    /**
     * Handle trace()
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    public function doTrace(LogEntry $logEntry)
    {
        $this->debug = $logEntry->getSubject();
        $meta = $this->getMeta($logEntry);
        $trace = $this->getTrace($logEntry);
        if ($meta['inclContext']) {
            $this->debug->addPlugin($this->debug->pluginHighlight, 'highlight');
        }
        unset($meta['trace']);
        $logEntry['args'] = [$trace];
        $logEntry['meta'] = $meta;
        $this->evalRows($logEntry);
        $this->debug->rootInstance->getPlugin('methodTable')->doTable($logEntry);
    }

    /**
     * Extract the trace from the log entry or generate it
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return array
     */
    private function getTrace(LogEntry $logEntry)
    {
        $meta = $this->getMeta($logEntry);
        $getOptions = $meta['inclArgs'] ? Backtrace::INCL_ARGS : 0;
        $getOptions |= $meta['inclInternal'] ? Backtrace::INCL_INTERNAL : 0;
        $trace = \is_array($meta['trace'])
            ? $this->debug->backtrace->normalize($meta['trace'])
            : $this->debug->backtrace->get(
                $getOptions,
                $meta['inclInternal']
                    ? 0
                    : $meta['limit'],
                $meta['trace'] // null or Exception
            );
        return $this->getTraceFinish($trace, $meta);
    }

    /**
     * Apply final adjustments to the trace
     *
     * @param array $trace Backtrace frames
     * @param array $meta  meta values
     *
     * @return array
     */
    private function getTraceFinish(array $trace, array $meta)
    {
        if ($meta['inclInternal']) {
            $trace = $this->removeMinInternal($trace);
        }
        if ($meta['limit'] > 0) {
            $trace = \array_slice($trace, 0, $meta['limit']);
        }
        if ($meta['inclContext']) {
            $trace = $this->debug->backtrace->addContext($trace);
        }
        $this->setCommonFilePrefix($trace);
        return \array_map(function ($frame) {
            $frame['file'] = $frame['file'] === 'eval()\'d code'
                ? $frame['file']
                : $this->parseFilePath($frame['file'], $this->commonFilePrefix);
            $frame['function'] = isset($frame['function'])
                ? new Abstraction(Type::TYPE_IDENTIFIER, array(
                    'typeMore' => Type::TYPE_IDENTIFIER_METHOD,
                    'value' => $frame['function'],
                ))
                : Abstracter::UNDEFINED; // either not set or null
            return $frame;
        }, $trace);
    }

    /**
     * Remove Internal Frames
     *
     * @param array $trace Backtrace frames
     *
     * @return array
     */
    private function removeMinInternal(array $trace)
    {
        $count = \count($trace);
        $internalClasses = [
            __CLASS__,
            'bdk\PubSub\Manager',
        ];
        for ($i = 3; $i < $count; $i++) {
            $frame = $trace[$i];
            \preg_match('/^(?P<classname>.+)->(?P<function>.+)$/', $frame['function'], $matches);
            $isInternal = \in_array($matches['classname'], $internalClasses, true) || $matches['function'] === 'publishBubbleEvent';
            if ($isInternal === false) {
                break;
            }
        }
        return \array_slice($trace, $i);
    }

    /**
     * Set default meta values
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return array meta values
     */
    private function getMeta(LogEntry $logEntry)
    {
        $meta = \array_merge(array(
            'caption' => $this->debug->i18n->trans('method.trace'),
            'columns' => ['file','line','function'],
            'inclArgs' => null,  // incl arguments with context?
                                 // will default to $inclContext
                                 //   may want to set meta['cfg']['objectsExclude'] = '*'
            'inclContext' => false,
            'inclInternal' => false,
            'limit' => 0,
            'sortable' => false,
            'trace' => null,  // set to array or Exception to specify trace
        ), $logEntry['meta']);

        if ($meta['inclArgs'] === null) {
            $meta['inclArgs'] = $meta['inclContext'];
        }
        return $meta;
    }

    /**
     * Handle "eval()'d code" frames
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    private function evalRows(LogEntry $logEntry)
    {
        $meta = \array_replace_recursive(array(
            'tableInfo' => array(
                'rows' => array(),
            ),
        ), $logEntry['meta']);
        $trace = $logEntry['args'][0];
        foreach ($trace as $i => $frame) {
            if (!empty($frame['evalLine'])) {
                $meta['tableInfo']['rows'][$i]['attribs'] = array(
                    'data-file' => (string) $frame['file'],
                    'data-line' => $frame['line'],
                );
                $trace[$i]['file'] = 'eval()\'d code';
                $trace[$i]['line'] = $frame['evalLine'];
            }
        }
        $logEntry['meta'] = $meta;
        $logEntry['args'] = [$trace];
    }

    /**
     * Get trace args
     *
     * @param array $argsPassed  arguments passed to trace()
     * @param array $argsDefault default arguments
     *
     * @return array args passed to trace() but sorted to match method signature
     */
    private function getTraceArgs(array $argsPassed, array $argsDefault)
    {
        $argsTyped = array();
        \array_walk($argsPassed, static function ($val) use (&$argsTyped) {
            $type = \gettype($val);
            $typeToKey = array(
                'array' => 'trace',
                'boolean' => 'inclContext',
                'integer' => 'limit',
                'object' => 'trace', // exception
                'string' => 'caption',
            );
            $key = isset($typeToKey[$type])
                ? $typeToKey[$type]
                : null;
            if ($key && isset($argsTyped[$key]) === false) {
                $argsTyped[$key] = $val;
                return;
            }
            $argsTyped[] = $val;
        });
        $argsDefault['caption'] = !empty($argsTyped['limit'])
            ? $this->debug->i18n->trans('method.trace')
                . ' (' . $this->debug->i18n->trans('method.trace.limited', array('limit' => $argsTyped['limit'])) . ')'
            : $this->debug->i18n->trans('method.trace');
        $args = \array_merge($argsDefault, $argsTyped);
        return \array_values($args);
    }

    /**
     * Parse file path into parts
     *
     * @param string $filePath     filepath (ie /var/www/html/index.php)
     * @param string $commonPrefix prefix shared by current group of files
     *
     * @return string|Abstraction
     */
    private function parseFilePath($filePath, $commonPrefix)
    {
        $docRoot = (string) $this->debug->serverRequest->getServerParam('DOCUMENT_ROOT');
        $baseName = \basename($filePath);
        $containsDocRoot = $docRoot && \strpos($filePath, $docRoot) === 0;
        $pathCommon = '';
        $pathRel = \substr($filePath, 0, 0 - \strlen($baseName));
        if ($commonPrefix || $containsDocRoot) {
            $strLengths = \array_intersect_key(
                [\strlen($commonPrefix), \strlen($docRoot)],
                \array_filter([$commonPrefix, $containsDocRoot])
            );
            $maxLen = \max($strLengths);
            $pathCommon = \substr($pathRel, 0, $maxLen);
            $pathRel = \substr($pathRel, $maxLen);
            if ($containsDocRoot) {
                $pathCommon = \substr($pathCommon, \strlen($docRoot));
            }
        }
        // phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
        return new Abstraction(Type::TYPE_STRING, array(
            'typeMore' => Type::TYPE_STRING_FILEPATH,
            'docRoot' => $containsDocRoot,
            'pathCommon' => $pathCommon,
            'pathRel' => $pathRel,
            'baseName' => $baseName,
        ));
    }

    /**
     * Determine the common file prefix for the given trace
     *
     * @param array $trace Backtrace frames
     *
     * @return void
     */
    private function setCommonFilePrefix(array $trace)
    {
        $this->commonFilePrefix = '';
        if (\count($trace) < 2) {
            return;
        }
        $files = [];
        foreach ($trace as $frame) {
            if (empty($frame['evalLine']) && !empty($frame['file'])) {
                $files[] = $frame['file'];
            }
        }
        $this->commonFilePrefix = $this->debug->stringUtil->commonPrefix($files);
    }
}
