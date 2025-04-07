<?php

namespace bdk\I18n;

use ErrorException;
use Exception;

/**
 * Load translation messages from file
 */
class FileLoader
{
    /** @var string */
    public $lastError = '';

    /** @var array<string,callable> extension parsers */
    private $extParsers = array();

    /**
     * Constructor
     */
    public function __construct()
    {
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
     * Get translation messages from given filepath
     *
     * If there is an error loading the file, $this->lastError will be set
     *
     * @param string $filepath file path
     *
     * @return array
     */
    public function load($filepath)
    {
        $this->lastError = false;
        if (\is_file($filepath) === false) {
            $this->lastError = $filepath . ' is not a file';
            return array();
        }
        $ext = \strtolower(\substr(\strrchr($filepath, '.'), 1));
        if (isset($this->extParsers[$ext]) === false) {
            $this->lastError = 'No parser defined for ' . $ext . ' files';
            return array();
        }
        \set_error_handler(static function ($errno, $errstr, $errfile, $errline) {
            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        });
        $data = array();
        try {
            $data = $this->extParsers[$ext]($filepath);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
        }
        \restore_error_handler();
        if (\is_array($data) === false) {
            $this->lastError = 'Load(' . $filepath . ') did not return an array';
            return array();
        }
        return $data;
    }

    /**
     * Register extension parser.
     *
     * Define a custom file-extension parser
     *
     * @param string   $ext      File extension (ie 'yml')
     * @param callable $callable A callable that returns key => value array
     *
     * @return void
     */
    public function registerExtParser($ext, $callable)
    {
        $this->extParsers[$ext] = $callable;
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
        $handle = \fopen($filepath, 'r');
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
}
