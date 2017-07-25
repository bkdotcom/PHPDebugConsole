<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2017 Brad Kent
 * @version   v1.4.0
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
        $data = $this->debug->getData();
        $str = '';
        $str .= $this->processAlerts($data['alerts']);
        $str .= $this->processSummary();
        foreach ($data['log'] as $args) {
            $method = array_shift($args);
            $str .= $this->processEntry($method, $args);
        }
        if ($event) {
            $event['output'] .= $str;
        } else {
            return $str;
        }
    }

    /**
     * Return log entry as text
     *
     * @param string  $method method
     * @param array   $args   arguments
     * @param integer $depth  specify nested depth (otherwise depth maintained internally)
     *
     * @return string
     */
    public function processEntry($method, $args = array(), $depth = null)
    {
        if ($method == 'table' && count($args) == 2) {
            $caption = array_pop($args);
            array_unshift($args, $caption);
        }
        if (count($args) == 1 && is_string($args[0])) {
            $args[0] = strip_tags($args[0]);
        }
        foreach ($args as $k => $v) {
            if ($k > 0 || !is_string($v)) {
                $args[$k] = $this->dump($v);
            }
        }
        $num_args = count($args);
        $glue = ', ';
        if ($num_args == 2) {
            $glue = preg_match('/[=:] ?$/', $args[0])   // ends with "=" or ":"
                ? ''
                : ' = ';
        }
        $strIndent = str_repeat('    ', $depth === null ? $this->depth : $depth);
        $str = implode($glue, $args);
        $str = $strIndent.str_replace("\n", "\n".$strIndent, $str);
        if ($depth === null) {
            if (in_array($method, array('group','groupCollapsed'))) {
                $this->depth ++;
            } elseif ($method == 'groupEnd' && $this->depth > 0) {
                $this->depth --;
            }
        }
        $str .= "\n";
        return $str;
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
            $str .= $this->dumpProperties($abs['properties'], $path);
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
     * @param array $properties properties as returned from getProperties()
     * @param array $path       {@internal}
     *
     * @return string
     */
    protected function dumpProperties($properties, $path = array())
    {
        $str = '';
        $propHeader = '';
        $pathCount = count($path);
        foreach ($properties as $name => $info) {
            $path[$pathCount] = $name;
            $vis = $info['visibility'];
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
                ? '"'.$val.'" ('.$date.')'
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
}
