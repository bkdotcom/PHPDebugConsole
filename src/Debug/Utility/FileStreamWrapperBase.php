<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.3
 */

namespace bdk\Debug\Utility;

use bdk\Debug;
use bdk\PubSub\Manager;
use UnexpectedValueException;

/**
 * Handle FileStreamWrapper:
 *    registration
 *    whether should transform file
 *    publish Debug::EVENT_STREAM_WRAP
 */
class FileStreamWrapperBase
{
    /** @var string */
    const OUTPUT_ACCESS_MODE = 'rb+';
    /** @var string */
    const OUTPUT_DESTINATION = 'php://memory';
    /** @var int */
    const STREAM_OPEN_FOR_INCLUDE = 128;

    /** @var resource|null The current context, or null if no context was passed to the caller function */
    public $context;

    /** @var list<string> */
    public static $filesTransformed = [];

    /** @var Manager|null */
    protected static $eventManager;

    /** @var bool */
    protected static $isRegistered = false;

    /** @var list<string> */
    protected static $protocols = ['file', 'phar'];

    /** @var list<string> paths to exclude from adding tick declaration */
    protected static $pathsExclude = [];

    /**
     * Register this stream wrapper
     *
     * @return void
     *
     * @throws UnexpectedValueException
     */
    public static function register()
    {
        if (static::$isRegistered) {
            return;
        }
        foreach (static::$protocols as $protocol) {
            self::registerProtocol($protocol);
        }
        static::$isRegistered = true;
        /*
            Disable OPcache
                a) want to make sure we modify required files
                b) don't want to cache modified files
        */
        \ini_set('opcache.enable', '0');
    }

    /**
     * Restore previous wrapper
     *
     * @return void
     *
     * @throws UnexpectedValueException
     */
    public static function unregister()
    {
        if (static::$isRegistered === false) {
            return;
        }
        foreach (static::$protocols as $protocol) {
            $result = \stream_wrapper_restore($protocol);
            if ($result === false) {
                throw new UnexpectedValueException('Failed to restore stream wrapper for ' . $protocol);
            }
        }
        static::$isRegistered = false;
    }

    /**
     * Define EventManager
     *
     * @param Manager $eventManager Event manager
     *
     * @return void
     */
    public static function setEventManager(Manager $eventManager)
    {
        static::$eventManager = $eventManager;
    }

    /**
     * Set paths/directories to exclude
     *
     * @param string[] $pathsExclude paths/directories to exclude
     *
     * @return void
     */
    public static function setPathsExclude(array $pathsExclude)
    {
        static::$pathsExclude = \array_values(\array_unique(\array_filter(\array_map('realpath', $pathsExclude))));
    }

    /**
     * Get paths/directories to exclude
     *
     * @return list<string>
     */
    public static function getPathsExclude()
    {
        return static::$pathsExclude;
    }

    /**
     * Get file resource
     *
     * @param string $file       File path
     * @param string $mode       The mode used to open the file, as detailed for fopen().
     * @param int    $options    Holds additional flags set by the streams API.
     * @param string $openedPath the full path of the file/resource that was actually opened
     *
     * @return resource|false
     * @throws UnexpectedValueException
     */
    protected function getResource($file, $mode, $options, &$openedPath)
    {
        $useIncludePath = (bool) ($options & STREAM_USE_PATH);
        $args = $this->popNull([$file, $mode, $useIncludePath, $this->context]);
        /** @var resource|false */
        $resource = \call_user_func_array('fopen', $args);
        /*
            Determine opened path
        */
        if ($resource) {
            $meta = \stream_get_meta_data($resource);
            if (!isset($meta['uri'])) {
                throw new UnexpectedValueException('Uri not in meta data');
            }
            $openedPath = $meta['uri'];
        }
        return $resource;
    }

    /**
     * Return a resource with modified content
     *
     * @param string $file       File path
     * @param int    $options    Holds additional flags set by the streams API.
     * @param string $openedPath the full path of the file/resource that was actually opened
     *
     * @return resource|false
     */
    protected function getResourceTransformed($file, $options, &$openedPath)
    {
        $resource = \fopen(static::OUTPUT_DESTINATION, static::OUTPUT_ACCESS_MODE);
        if ($resource === false) {
            return false;
        }
        $useIncludePath = (bool) ($options & STREAM_USE_PATH);
        $args = $this->popNull([$file, $useIncludePath, $this->context]);
        /** @var string|false */
        $content = \call_user_func_array('file_get_contents', $args);
        $resolvedPath = \stream_resolve_include_path($file);
        $openedPath = $useIncludePath && $resolvedPath
            ? $resolvedPath
            : $file;
        if (static::$eventManager) {
            $event = static::$eventManager->publish(Debug::EVENT_STREAM_WRAP, $resource, array(
                'content' => $content,
                'filepath' => $file,
            ));
            if ($event['content'] !== $content) {
                self::$filesTransformed[] = $openedPath;
            }
            $content = $event['content'];
        }
        \fwrite($resource, $content);
        \rewind($resource);
        return $resource;
    }

    /**
     * Check whether this file should be transformed
     *
     * @param string $file file path
     *
     * @return bool
     */
    private static function isTargeted($file)
    {
        foreach (static::$pathsExclude as $excludePath) {
            if ($file === $excludePath) {
                return false;
            }
            if (\strpos($file, $excludePath . DIRECTORY_SEPARATOR) === 0) {
                return false;
            }
        }
        return true;
    }

    /**
     * Remove null values from end of list
     *
     * @param array $values Values to trim
     *
     * @return array
     */
    protected function popNull($values)
    {
        $count = \count($values);
        for ($i = $count; $i > 0; $i--) {
            if ($values[$i - 1] !== null) {
                break;
            }
        }
        return \array_slice($values, 0, $i);
    }

    /**
     * Register stream wrapper for the specified protocol
     *
     * First unregisters current protocol
     *
     * @param string $protocol Protocol such as "file" or "phar"
     *
     * @return void
     *
     * @throws UnexpectedValueException
     */
    private static function registerProtocol($protocol)
    {
        $result = \stream_wrapper_unregister($protocol);
        if ($result === false) {
            throw new UnexpectedValueException('Failed to unregister stream wrapper for ' . $protocol);
        }
        $result = \stream_wrapper_register($protocol, \get_called_class());
        if ($result === false) {
            throw new UnexpectedValueException('Failed to register stream wrapper for ' . $protocol);
        }
    }

    /**
     * Test if file should be transformed
     *
     * @param string $file    Specifies the file/URL that was passed to the original function.
     * @param int    $options Holds additional flags set by the streams API.
     *
     * @return bool
     */
    protected static function shouldTransform($file, $options)
    {
        $including = (bool) ($options & static::STREAM_OPEN_FOR_INCLUDE);
        return $including && static::isTargeted($file);
    }
}
