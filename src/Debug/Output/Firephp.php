<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v2.1.1
 */

namespace bdk\Debug\Output;

use bdk\Debug\MethodTable;
use bdk\PubSub\Event;

/**
 * Output log via FirePHP
 */
class Firephp extends Base
{

    protected $firephpMethods = array(
        'log' => 'LOG',
        'info' => 'INFO',
        'warn' => 'WARN',
        'error' => 'ERROR',
        'table' => 'TABLE',
        'group' => 'GROUP_START',
        'groupCollapsed' => 'GROUP_START',
        'groupEnd' => 'GROUP_END',
    );
    protected $messageIndex = 0;
    protected $outputEvent;

    const FIREPHP_PROTO_VER = '0.3';
    const MESSAGE_LIMIT = 99999;

    /**
     * Output the log via FirePHP headers
     *
     * @param Event $event debug.output event object
     *
     * @return void
     */
    public function onOutput(Event $event)
    {
        $this->outputEvent = $event;
        $this->channelName = $this->debug->getCfg('channelName');
        $this->data = $this->debug->getData();
        $event['headers'][] = array('X-Wf-Protocol-1', 'http://meta.wildfirehq.org/Protocol/JsonStream/0.2');
        $event['headers'][] = array('X-Wf-1-Plugin-1', 'http://meta.firephp.org/Wildfire/Plugin/FirePHP/Library-FirePHPCore/'.self::FIREPHP_PROTO_VER);
        $event['headers'][] = array('X-Wf-1-Structure-1', 'http://meta.firephp.org/Wildfire/Structure/FirePHP/FirebugConsole/0.1');
        $heading = isset($_SERVER['REQUEST_METHOD'])
            ? $_SERVER['REQUEST_METHOD'].' '.$_SERVER['REQUEST_URI']
            : '$: '. \implode(' ', $_SERVER['argv']);
        $this->processLogEntryWEvent('groupCollapsed', array('PHP: '.$heading));
        $this->processAlerts();
        $this->processSummary();
        $this->processLog();
        $this->processLogEntryWEvent('groupEnd');
        $event['headers'][] = array('X-Wf-1-Index', $this->messageIndex);
        $this->data = array();
        return;
    }

    /**
     * Output a log entry to Firephp
     *
     * @param string $method method
     * @param array  $args   args
     * @param array  $meta   meta values
     *
     * @return void
     */
    public function processLogEntry($method, $args = array(), $meta = array())
    {
        $value = null;
        $firePhpMeta = $this->getMeta($method, $meta);
        if ($method == 'alert') {
            list($method, $args) = $this->methodAlert($args, $meta);
            $value = $args[0];
        } elseif (\in_array($method, array('group','groupCollapsed'))) {
            $firePhpMeta['Label'] = $args[0];
        } elseif (\in_array($method, array('profileEnd','table'))) {
            $firePhpMeta['Type'] = \is_array($args[0])
                ? $this->firephpMethods['table']
                : $this->firephpMethods['log'];
            $value = $this->methodTable($args[0], $meta['columns']);
            if ($meta['caption']) {
                $firePhpMeta['Label'] = $meta['caption'];
            }
        } elseif ($method == 'trace') {
            $firePhpMeta['Type'] = $this->firephpMethods['table'];
            $value = $this->methodTable($args[0], array('function','file','line'));
            $firePhpMeta['Label'] = 'trace';
        } elseif (\count($args)) {
            if (\count($args) == 1) {
                $value = $args[0];
                // no label;
            } else {
                $firePhpMeta['Label'] = \array_shift($args);
                $value = \count($args) > 1
                    ? $args // firephp only supports label/value...  we'll pass multiple values as an array
                    : $args[0];
            }
        }
        $value = $this->dump($value);
        if ($this->messageIndex < self::MESSAGE_LIMIT) {
            $this->setFirephpHeader($firePhpMeta, $value);
        } elseif ($this->messageIndex === self::MESSAGE_LIMIT) {
            $this->setFirephpHeader(
                array('Type'=>$this->firephpMethods['warn']),
                'FirePhp\'s limit of '.\number_format(self::MESSAGE_LIMIT).' messages reached!'
            );
        }
        return;
    }

    /**
     * Initialize firephp's meta array
     *
     * @param string $method PHPDebugConsole method
     * @param array  $meta   PHPDebugConsole meta values
     *
     * @return array
     */
    private function getMeta($method, $meta)
    {
        $firePhpMeta = array(
            'Type' => isset($this->firephpMethods[$method])
                ? $this->firephpMethods[$method]
                : $this->firephpMethods['log'],
            // Label
            // File
            // Line
            // Collapsed  (for group)
        );
        if (isset($meta['file'])) {
            $firePhpMeta['File'] = $meta['file'];
            $firePhpMeta['Line'] = $meta['line'];
        }
        if (\in_array($method, array('group','groupCollapsed'))) {
            $firePhpMeta['Collapsed'] = $method == 'groupCollapsed'
                // yes, strings
                ? 'true'
                : 'false';
        }
        return $firePhpMeta;
    }

    /**
     * Build table rows
     *
     * @param array $array   array to debug
     * @param array $columns columns to display
     *
     * @return array
     */
    protected function methodTable($array, $columns = array())
    {
        if (!\is_array($array)) {
            return $this->dump($array);
        }
        $table = array();
        $keys = $columns ?: $this->debug->methodTable->colKeys($array);
        $headerVals = $keys;
        foreach ($headerVals as $i => $val) {
            if ($val === MethodTable::SCALAR) {
                $headerVals[$i] = 'value';
            }
        }
        \array_unshift($headerVals, '');
        $table[] = $headerVals;
        $classNames = array();
        if ($this->debug->abstracter->isAbstraction($array) && $array['traverseValues']) {
            $array = $array['traverseValues'];
        }
        foreach ($array as $k => $row) {
            $values = $this->debug->methodTable->keyValues($row, $keys, $objInfo);
            foreach ($values as $k2 => $val) {
                if ($val === $this->debug->abstracter->UNDEFINED) {
                    $values[$k2] = null;
                }
            }
            $classNames[] = $objInfo['row']
                ? $objInfo['row']['className']
                : '';
            \array_unshift($values, $k);
            $table[] = \array_values($values);
        }
        if (\array_filter($classNames)) {
            \array_unshift($table[0], '');
            // first col is row key.
            // insert classname after key
            foreach ($classNames as $i => $className) {
                \array_splice($table[$i+1], 1, 0, $className);
            }
        }
        return $table;
    }

    /**
     * set FirePHP log entry header(s)
     *
     * @param array $meta  meta information
     * @param mixed $value value
     *
     * @return void
     */
    private function setFirephpHeader($meta, $value = null)
    {
        $msg = \json_encode(array(
            $meta,
            $value,
        ));
        $structureIndex = 1;    // refers to X-Wf-1-Structure-1
        $parts = \explode("\n", \rtrim(\chunk_split($msg, 5000, "\n")));
        $numParts = \count($parts);
        for ($i=0; $i<$numParts; $i++) {
            $part = $parts[$i];
            $this->messageIndex++;
            $headerName = 'X-Wf-1-'.$structureIndex.'-1-'.$this->messageIndex;
            $headerValue = ( $i==0 ? \strlen($msg) : '')
                . '|' . $part . '|'
                . ( $i<$numParts-1 ? '\\' : '' );
            $this->outputEvent['headers'][] = array($headerName, $headerValue);
        }
    }
}
