<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2017 Brad Kent
 * @version   v2.0.0
 */

namespace bdk\Debug;

use bdk\Debug\Utf8;
use bdk\PubSub\Event;

/**
 * Output log as plain-text
 */
class OutputText extends OutputBase
{

    protected $depth = 0;   // for keeping track of indentation

    /**
     * Output the log as text
     *
     * @param Event $event event object
     *
     * @return string|void
     */
    public function onOutput(Event $event = null)
    {
        $this->data = $this->debug->getData();
        $str = '';
        $str .= $this->processAlerts();
        $str .= $this->processSummary();
        $str .= $this->processLog();
        $this->data = array();
        if ($event) {
            $event['output'] .= $str;
        } else {
            return $str;
        }
    }

    /**
     * Dump array as text
     *
     * @param array $array array
     * @param array $path  {@internal}
     *
     * @return string
     */
    protected function dumpArray($array, $path = array())
    {
        $array = parent::dumpArray($array, $path);
        $str = trim(print_r($array, true));
        $str = preg_replace('/Array\s+\(\s+\)/s', 'Array()', $str); // single-lineify empty arrays
        $str = str_replace("Array\n(", 'Array(', $str);
        if (count($path)) {
            $str = str_replace("\n", "\n    ", $str);
        }
        return $str;
    }

    /**
     * Dump boolean
     *
     * @param boolean $val boolean value
     *
     * @return string
     */
    protected function dumpBool($val)
    {
        return $val ? 'true' : 'false';
    }

    /**
     * Dump float value
     *
     * @param float $val float value
     *
     * @return float|string
     */
    protected function dumpFloat($val)
    {
        $date = $this->checkTimestamp($val);
        return $date
            ? '📅 '.$val.' ('.$date.')'
            : $val;
    }

    /**
     * Dump null value
     *
     * @return string
     */
    protected function dumpNull()
    {
        return 'null';
    }

    /**
     * Dump object as text
     *
     * @param array $abs  object "abstraction"
     * @param array $path {@internal}
     *
     * @return string
     */
    protected function dumpObject($abs, $path = array())
    {
        $pathCount = count($path);
        if ($abs['isRecursion']) {
            $str = '(object) '.$abs['className'].' *RECURSION*';
        } elseif ($abs['isExcluded']) {
            $str = '(object) '.$abs['className'].' (not inspected)';
        } else {
            $str = '(object) '.$abs['className']."\n";
            $str .= $this->dumpProperties($abs, $path);
            if ($abs['collectMethods'] && $this->debug->output->getCfg('outputMethods')) {
                $str .= $this->dumpMethods($abs['methods']);
            }
        }
        $str = trim($str);
        if ($pathCount) {
            $str = str_replace("\n", "\n    ", $str);
        }
        return $str;
    }

    /**
     * Dump object properties as text
     *
     * @param array $abs  object abstraction
     * @param array $path {@internal}
     *
     * @return string
     */
    protected function dumpProperties($abs, $path = array())
    {
        $str = '';
        $propHeader = '';
        $pathCount = count($path);
        if (isset($abs['methods']['__get'])) {
            $str .= '    ✨ This object has a __get() method'."\n";
        }
        foreach ($abs['properties'] as $name => $info) {
            $path[$pathCount] = $name;
            $vis = $info['visibility'];
            if (in_array($vis, array('magic','magic-read','magic-write'))) {
                $vis = '✨ '.$vis;    // "sparkles" there is no magic-wand unicode char
            }
            if ($vis == 'private' && $info['inheritedFrom']) {
                $vis = '🔒 '.$vis;
            }
            $str .= '    ('.$vis.') '.$name.' = '.$this->dump($info['value'], $path)."\n";
        }
        $propHeader = $str
            ? 'Properties:'
            : 'Properties: none!';
        $str = '  '.$propHeader."\n".$str;
        return $str;
    }

    /**
     * Dump object methods as text
     *
     * @param array $methods methods as returned from getMethods
     *
     * @return string html
     */
    protected function dumpMethods($methods)
    {
        $str = '';
        if (!empty($methods)) {
            $counts = array(
                'public' => 0,
                'protected' => 0,
                'private' => 0,
                'magic' => 0,
            );
            foreach ($methods as $info) {
                $counts[ $info['visibility'] ] ++;
            }
            $str .= '  Methods:'."\n";
            foreach ($counts as $vis => $count) {
                if ($count) {
                    $str .= '    '.$vis.': '.$count."\n";
                }
            }
        } else {
            $str .= '  Methods: none!'."\n";
        }
        return $str;
    }

    /**
     * Dump string
     *
     * @param string $val string value
     *
     * @return string
     */
    protected function dumpString($val)
    {
        if (is_numeric($val)) {
            $date = $this->checkTimestamp($val);
            return $date
                ? '📅 "'.$val.'" ('.$date.')'
                : '"'.$val.'"';
        } else {
            return '"'.$this->debug->utf8->dump($val).'"';
        }
    }

    /**
     * Dump undefined
     *
     * @return null
     */
    protected function dumpUndefined()
    {
        return 'undefined';
    }

    /**
     * Return log entry as text
     *
     * @param string $method method
     * @param array  $args   arguments
     * @param array  $meta   meta values
     *
     * @return string
     */
    protected function processEntry($method, $args = array(), $meta = array())
    {
        $numArgs = count($args);
        $hasSubs = false;
        $prefixes = array(
            'error' => '⦻ ',
            'info' => 'ℹ ',
            'log' => '',
            'warn' => '⚠ ',
            'assert' => '≠ ',
            'count' => '✚ ',
            'time' => '⏲ ',
            'group' => '▸ ',
            'groupCollapsed' => '▸ ',
        );
        $prefix = isset($prefixes[$method])
            ? $prefixes[$method]
            : '';
        if (in_array($method, array('error','info','log','warn'))) {
            if (is_string($args[0]) && $numArgs > 1) {
                $args = $this->processSubstitutions($args, $hasSubs);
            }
        } elseif ($method == 'alert') {
            $class = $args['class'];
            $prefix = '[Alert '.$class.'] ';
            $args = array($args['message']);
        } elseif ($method == 'table') {
            $caption = $args[1];
            $args = array($this->methodTable($args[0], $args[2]));
            if ($caption) {
                array_unshift($args, $caption);
            }
            $numArgs = count($args);
        }
        if ($hasSubs) {
            $glue = '';
        } else {
            if (count($args) == 1 && is_string($args[0])) {
                $args[0] = strip_tags($args[0]);
            }
            foreach ($args as $k => $v) {
                if ($k > 0 || !is_string($v)) {
                    $args[$k] = $this->dump($v);
                }
            }
            $glue = ', ';
            if ($numArgs == 2) {
                $glue = preg_match('/[=:] ?$/', $args[0])   // ends with "=" or ":"
                    ? ''
                    : ' = ';
            }
        }
        $strIndent = str_repeat('    ', $this->depth);
        $str = $prefix.implode($glue, $args);
        $str = $strIndent.str_replace("\n", "\n".$strIndent, $str);
        if (in_array($method, array('group','groupCollapsed'))) {
            $this->depth ++;
        } elseif ($method == 'groupEnd' && $this->depth > 0) {
            $this->depth --;
        }
        $str .= "\n";
        return $str;
    }
}
