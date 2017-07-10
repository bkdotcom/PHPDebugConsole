<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2017 Brad Kent
 * @version   v1.4.0
 *
 * @see https://craig.is/writing/chrome-logger/techspecs
 */

namespace bdk\Debug;

/**
 * Output log as via ChromeLogger headers
 *
 * ChromeLogger supports the following methods/log-types:
 * log, warn, error, info, group, groupEnd, groupCollapsed, and table
 */
class OutputChromeLogger extends OutputBase
{

    const HEADER_NAME = 'X-ChromeLogger-Data';

    /**
     * @var array header data
     */
    protected $json = array(
        'version' => \bdk\Debug::VERSION,
        'columns' => array('log', 'backtrace', 'type'),
        'rows' => array()
    );

    /**
     * Output the log as text
     *
     * @param Event $event event object
     *
     * @return void
     */
    public function output(Event $event)
    {
        $data = $this->debug->getData();
        foreach ($data['log'] as $args) {
            $method = array_shift($args);
            $this->processEntry($method, $args);
        }
        if ($this->json['rows']) {
            $encoded = $this->encode($this->json);
            if (headers_sent($file, $line)) {
            } elseif (strlen($encoded) > 250000) {
            } else {
                header(self::HEADER_NAME . ': ' . $encoded);
            }
        }
    }

    /**
     * Encode data for header
     *
     * @param array $data log data
     *
     * @return string encoded data for header
     */
    protected function encode($data)
    {
        return base64_encode(utf8_encode(json_encode($data)));
    }

    /**
     * Build table rows
     *
     * @param array $array array to debug
     *
     * @return array
     */
    protected function methodTable($array)
    {
        $keys = $this->debug->utilities->arrayColkeys($array);
        $table = array();
        $classnames = array();
        foreach ($array as $k => $row) {
            $values = $this->debug->abstracter->keyValues($row, $keys, $objInfo);
            $values = array_map(function ($val) {
                if ($val === $this->debug->abstracter->UNDEFINED) {
                    return null;
                } elseif (is_array($val)) {
                    return $this->debug->output->outputText->dump($val, false);
                } else {
                    return $val;
                }
            }, $values);
            $values = array_combine($keys, $values);
            $classnames[$k] = $objInfo
                ? $objInfo['className']
                : '';
            $table[$k] = $values;
        }
        if (array_filter($classnames)) {
            foreach ($classnames as $k => $classname) {
                $table[$k] = array_merge(
                    array('___class_name' => $classname),
                    $table[$k]
                );
            }
        }
        return $table;
    }

    /**
     * Process log entry
     *
     * Transmogrify log entry to chromlogger format
     *
     * @param string $method method
     * @param array  $args   arguments
     *
     * @return void
     */
    protected function processEntry($method, $args)
    {
        $metaStr = null;
        if (in_array($method, array('error','warn'))) {
            $meta = $this->debug->output->getMetaArg($args);
            if (isset($meta['file'])) {
                $metaStr = $meta['file'].': '.$meta['line'];
            }
        } elseif ($method == 'table') {
            $args = array($this->methodTable($args[0]));
        }
        foreach ($args as $i => $arg) {
            $args[$i] = $this->dump($arg);
        }
        if ($method === 'log') {
            $method = '';
        }
        $this->json['rows'][] = array($args, $metaStr, $method);
    }
}
