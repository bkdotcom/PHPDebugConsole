<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.3
 */

namespace bdk\Debug\Route;

use bdk\Debug;
use bdk\Debug\LogEntry;
use bdk\PubSub\Event;
use Exception;

/**
 * Output log to a stream
 */
class Stream extends AbstractRoute
{
    /** @var resource|null */
    protected $fileHandle;

    /** @var array<string,mixed> */
    protected $cfg = array(
        'ansi' => 'default',        // default | true | false
        'channels' => ['*'],
        'channelsExclude' => [
            'events',
            'files',
        ],
        'output' => false,          // kept in sync with Debug->cfg['output']
        'stream' => 'php://stderr', // filepath/uri/resource
    );

    /**
     * Constructor
     *
     * @param Debug $debug Debug Instance
     */
    public function __construct(Debug $debug)
    {
        parent::__construct($debug);
        if (!$this->dumper) {
            $this->dumper = $debug->getDump('text');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_CONFIG => 'onConfig',
            Debug::EVENT_LOG => 'onLog',
            Debug::EVENT_PLUGIN_INIT => 'init',
        );
    }

    /**
     * Returns whether color output is supported
     *
     * @param resource $streamResource stream resouce
     *
     * @return bool
     *
     * @see https://github.com/composer/composer/blob/main/src/Composer/Util/Platform.php
     * @see https://github.com/symfony/console/blob/7.2/Output/StreamOutput.php#L90
     */
    public static function hasColorSupport($streamResource)
    {
        if (self::ansiTestNo($streamResource)) {
            return false;
        }

        if (self::ansiTestYes($streamResource)) {
            return true;
        }

        if (\function_exists('posix_isatty')) {
            try {
                return \posix_isatty($streamResource);
            } catch (Exception $e) {
                // do nothing
            }
        }

        // See https://github.com/chalk/supports-color/blob/d4f413efaf8da045c5ab440ed418ef02dbb28bf1/index.js#L157
        $term = (string) \getenv('TERM');
        $termValues = 'screen|xterm|vt100|vt220|putty|rxvt|ansi|cygwin|linux';
        return \preg_match('/^((' . $termValues . ').*)|(.*-256(color)?(-bce)?)$/', $term) === 1;
    }

    /**
     * Debug::EVENT_PLUGIN_INIT subscriber
     *
     * @return void
     */
    public function init()
    {
        $this->cfg['output'] = $this->debug->isCli()
            ? $this->debug->getCfg('output', Debug::CONFIG_DEBUG)    // if cli, only output if explicitly true
            : true;                             //  otherwise push to stream
    }

    /**
     * Debug::EVENT_CONFIG subscriber
     *
     * @param Event $event Event instance
     *
     * @return void
     */
    public function onConfig(Event $event)
    {
        $cfg = $event['debug'] ?: array();
        if (isset($cfg['output']) && $this->debug->isCli()) {
            $this->cfg['output'] = $cfg['output'];
        }
        if ($this->cfg['output']) {
            $this->openStream($this->cfg['stream']);
        }
    }

    /**
     * Debug::EVENT_LOG event subscriber
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    public function onLog(LogEntry $logEntry)
    {
        if (!$this->fileHandle) {
            return;
        }
        if (!$this->cfg['output']) {
            return;
        }
        $method = $logEntry['method'];
        if ($method === 'groupUncollapse') {
            return;
        }
        $str = $this->processLogEntryViaEvent($logEntry);
        \fwrite($this->fileHandle, $str);
    }

    /**
     * Determine whether or not to use ANSI escape sequences (color output)
     *
     * @return bool
     */
    private function ansiCheck()
    {
        return $this->cfg['ansi'] === true
            || ($this->cfg['ansi'] === 'default' && self::hasColorSupport($this->fileHandle));
    }

    /**
     * Test if environment indicates no ANSI support
     *
     * @param resource $streamResource stream resource
     *
     * @return bool
     */
    private static function ansiTestNo($streamResource)
    {
        $term = (string) \getenv('TERM');
        return \count(\array_filter([
            $term === 'dumb',
            !\defined('STDOUT'),
            isset($_SERVER['NO_COLOR']),
            \getenv('NO_COLOR') !== false,
            (\function_exists('stream_isatty') && \stream_isatty($streamResource) === false
                // see https://github.com/composer/composer/issues/9690#issuecomment-779700967
                && !\in_array(\strtoupper((string) \getenv('MSYSTEM')), ['MINGW32', 'MINGW64'], true)),
        ])) > 0;
    }

    /**
     * Test if environment indicates ANSI support
     *
     * @param resource $streamResource stream resource
     *
     * @return bool
     */
    private static function ansiTestYes($streamResource)
    {
        return \getenv('TERM_PROGRAM') === 'Hyper'
            || \getenv('ANSICON') !== false
            || \getenv('COLORTERM') !== false
            || \getenv('ConEmuANSI') === 'ON'
            || (\function_exists('sapi_windows_vt100_support') && \sapi_windows_vt100_support($streamResource));
    }

    /**
     * Is file path writable?
     *
     * @param string $file file path
     *
     * @return bool
     */
    private function isWritable($file)
    {
        if (\strpos($file, 'php://') === 0 || \is_writable($file)) {
            return true;
        }
        $dir = \dirname($file);
        return !\file_exists($file) && \is_writeable($dir);
    }

    /**
     * Open file/stream
     *
     * @param resource|string $stream file path, uri, or stream resource
     *
     * @return void
     */
    protected function openStream($stream)
    {
        if ($this->fileHandle) {
            $meta = \stream_get_meta_data($this->fileHandle);
            if ($meta['uri'] === $stream) {
                // no change
                return;
            }
            // close existing file
            \fclose($this->fileHandle);
            $this->fileHandle = null;
        }
        if (!$stream) {
            return;
        }
        $this->setFilehandle($stream);
        if (!$this->fileHandle) {
            return;
        }
        $this->dumper = $this->ansiCheck()
            ? $this->debug->getDump('textAnsi')
            : $this->debug->getDump('text');
    }

    /**
     * Open stream
     *
     * @param resource|string $stream file path, uri, or stream resource
     *
     * @return void
     */
    private function setFilehandle($stream)
    {
        if (\is_resource($stream)) {
            $this->fileHandle = $stream;
            return;
        }
        $file = $stream;
        $fileExists = \file_exists($file);
        if ($this->isWritable($file) === false) {
            \trigger_error($file . ' is not writable', E_USER_NOTICE);
            return;
        }
        $this->fileHandle = \fopen($file, 'a');
        if (!$this->fileHandle) {
            return;
        }
        $meta = \stream_get_meta_data($this->fileHandle);
        if ($meta['wrapper_type'] !== 'plainfile') {
            return;
        }
        \fwrite($this->fileHandle, '***** ' . \date('Y-m-d H:i:s T') . ' *****' . "\n");
        if (!$fileExists) {
            // we just created file
            \chmod($file, 0660);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function postSetCfg($cfg = array(), $prev = array())
    {
        if (\array_key_exists('stream', $cfg)) {
            // changing stream?
            $this->openStream($cfg['stream']);
        }
    }
}
