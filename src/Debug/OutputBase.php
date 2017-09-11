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

use bdk\PubSub\SubscriberInterface;

/**
 * Base output plugin
 */
class OutputBase implements SubscriberInterface
{

    protected $debug;
    protected $dumpType;
    protected $dumpTypeMore;
    protected $data = array();

    /**
     * Constructor
     *
     * @param object $debug debug instance
     */
    public function __construct($debug)
    {
        $this->debug = $debug;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscriptions()
    {
        return array(
            'debug.output' => 'onOutput',
        );
    }

    /**
     * Dump value

     * @param mixed $val  value to dump
     * @param array $path {@internal}
     *
     * @return string
     */
    public function dump($val, $path = array())
    {
        $typeMore = null;
        $type = $this->debug->abstracter->getType($val, $typeMore);
        if ($typeMore == 'raw') {
            $val = $this->debug->abstracter->getAbstraction($val);
            $typeMore = null;
        } elseif ($typeMore == 'abstraction') {
            $typeMore = null;
        }
        $method = 'dump'.ucfirst($type);
        $return = in_array($type, array('array', 'object'))
            ? $this->{$method}($val, $path)
            : $this->{$method}($val);
        $this->dumpType = $type;
        $this->dumpTypeMore = $typeMore;
        return $return;
    }

    /**
     * Is value a timestamp?
     *
     * @param mixed $val value to check
     *
     * @return string|false
     */
    protected function checkTimestamp($val)
    {
        $secs = 86400 * 90; // 90 days worth o seconds
        $tsNow = time();
        if ($val > $tsNow - $secs && $val < $tsNow + $secs) {
            return date('Y-m-d H:i:s', $val);
        }
        return false;
    }

    /**
     * Dump array
     *
     * @param array $array array to dump
     * @param array $path  {@internal}
     *
     * @return array
     */
    protected function dumpArray($array, $path = array())
    {
        $pathCount = count($path);
        foreach ($array as $key => $val) {
            $path[$pathCount] = $key;
            $array[$key] = $this->dump($val, $path);
        }
        return $array;
    }

    /**
     * Dump boolean
     *
     * @param boolean $val boolean value
     *
     * @return boolean
     */
    protected function dumpBool($val)
    {
        return (bool) $val;
    }

    /**
     * Dump callable
     *
     * @param array $abs array/callable abstraction
     *
     * @return string
     */
    protected function dumpCallable($abs)
    {
        return 'callable: '.$abs['values'][0].'::'.$abs['values'][1];
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
            ? $val.' ('.$date.')'
            : $val;
    }

    /**
     * Dump integer value
     *
     * @param integer $val integer value
     *
     * @return integer|string
     */
    protected function dumpInt($val)
    {
        return $this->dumpFloat($val);
    }

    /**
     * Dump null value
     *
     * @return null
     */
    protected function dumpNull()
    {
        return null;
    }

    /**
     * Dump object
     *
     * @param array $abs  object abstraction
     * @param array $path {@internal}
     *
     * @return mixed
     */
    protected function dumpObject($abs, $path = array())
    {
        if ($abs['isRecursion']) {
            $return = '(object) '.$abs['className'].' *RECURSION*';
        } elseif ($abs['isExcluded']) {
            $return = '(object) '.$abs['className'].' (not inspected)';
        } else {
            $return = array(
                '___class_name' => $abs['className'],
            );
            $pathCount = count($path);
            foreach ($abs['properties'] as $name => $info) {
                $path[$pathCount] = $name;
                $vis = $info['visibility'];
                if ($vis == 'private' && $info['inheritedFrom']) {
                    $vis = '🔒 '.$vis;
                }
                $name = '('.$vis.') '.$name;
                $return[$name] = $this->dump($info['value'], $path);
            }
        }
        return $return;
    }

    /**
     * Dump recursion (array recursion)
     *
     * @return string
     */
    protected function dumpRecursion()
    {
        return 'Array *RECURSION*';
    }

    /**
     * Dump resource
     *
     * @param array $abs resource abstraction
     *
     * @return string
     */
    protected function dumpResource($abs)
    {
        return $abs['value'];
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
                ? $val.' ('.$date.')'
                : $val;
        } else {
            return $this->debug->utf8->dump($val);
        }
    }

    /**
     * Dump undefined
     *
     * @return null
     */
    protected function dumpUndefined()
    {
        return null;
    }

    /**
     * Build table rows
     *
     * This builds table rows usable by ChromeLogger and <script>
     *
     * @param array $array   array to debug
     * @param array $columns columns to output
     *
     * @return array
     */
    protected function methodTable($array, $columns = array())
    {
        $keys = $columns ?: $this->debug->utilities->arrayColKeys($array);
        $table = array();
        $classnames = array();
        foreach ($array as $k => $row) {
            $values = $this->debug->abstracter->keyValues($row, $keys, $objInfo);
            $values = array_map(function ($val) {
                if ($val === $this->debug->abstracter->UNDEFINED) {
                    return get_class($this) == __NAMESPACE__.'\\OutputScript'
                        ? $val
                        : null;
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
     * Process alerts
     *
     * @return string
     */
    protected function processAlerts()
    {
        $str = '';
        $trans = array(
            'danger' => 'error',
            'success' => 'info',
            'warning' => 'warn',
        );
        foreach ($this->data['alerts'] as $alert) {
            $msg = str_replace('<br />', ", \n", $alert['message']);
            $method = $alert['class'];
            if (isset($trans[$method])) {
                $method = $trans[$method];
            }
            $str .= $this->processEntry($method, array($msg));
        }
        return trim($str);
    }

    /**
     * Process log entries
     *
     * @return string
     */
    protected function processLog()
    {
        $str = '';
        foreach ($this->data['log'] as $args) {
            $method = array_shift($args);
            $meta = $this->debug->internal->getMetaArg($args);
            $str .= $this->processEntry($method, $args, $meta);
        }
        return $str;
    }

    /**
     * Handle the not-well documented substitutions
     *
     * @param array   $args    arguments
     * @param boolean $hasSubs set to true if substitutions/formatting applied
     *
     * @return array
     *
     * @see https://console.spec.whatwg.org/#formatter
     */
    protected function processSubstitutions($args, &$hasSubs)
    {
        $subRegex = '/%'
            .'(?:'
            .'[coO]|'               // c: css, o: obj with max info, O: obj w generic info
            .'[+-]?'                // sign specifier
            .'(?:[ 0]|\'.{1})?'     // padding specifier
            .'-?'                   // alignment specifier
            .'\d*'                  // width specifier
            .'(?:\.\d+)?'           // precision specifier
            .'[difs]'
            .')'
            .'/';
        if (!is_string($args[0])) {
            return $args;
        }
        $index = 0;
        $indexes = array(
            'c' => array(),
        );
        $hasSubs = false;
        $args[0] = preg_replace_callback($subRegex, function ($matches) use (
            &$args,
            &$hasSubs,
            &$index,
            &$indexes
        ) {
            $hasSubs = true;
            $index++;
            $replacement = $matches[0];
            $type = substr($matches[0], -1);
            if (strpos('difs', $type) !== false) {
                $format = $matches[0];
                $sub = $args[$index];
                if ($type == 'i') {
                    $format = substr_replace($format, 'd', -1, 1);
                } elseif ($type === 's') {
                    $sub = $this->substitutionAsString($sub);
                }
                $replacement = sprintf($format, $sub);
            } elseif ($type === 'c') {
                $asHtml = get_called_class() == __NAMESPACE__.'\\OutputHtml';
                if (!$asHtml) {
                    return '';
                }
                $replacement = '';
                if ($indexes['c']) {
                    // close prev
                    $replacement = '</span>';
                }
                $replacement .= '<span '.$this->debug->utilities->buildAttribString(array(
                    'style' => $args[$index],
                )).'>';
                $indexes['c'][] = $index;
            } elseif (strpos('oO', $type) !== false) {
                $replacement = $this->dump($args[$index]);
            }
            return $replacement;
        }, $args[0]);
        if ($indexes['c']) {
            $args[0] .= '</span>';
        }
        if ($hasSubs) {
            $args = array($args[0]);
        }
        return $args;
    }

    /**
     * Process summary
     *
     * @return string
     */
    protected function processSummary()
    {
        $str = '';
        $summaryData = $this->data['logSummary'];
        krsort($summaryData);
        $summaryData = call_user_func_array('array_merge', $summaryData);
        foreach ($summaryData as $args) {
            $method = array_shift($args);
            $str .= $this->processEntry($method, $args);
        }
        return trim($str);
    }

    /**
     * Remove empty groups with 'hideIfEmpty' meta value
     *
     * @return void
     */
    public function removeHideIfEmptyGroups()
    {
        $groupStack = array();
        $groupStackCount = 0;
        $removed = false;
        for ($i = 0, $count = count($this->data['log']); $i < $count; $i++) {
            $method = $this->data['log'][$i][0];
            if (in_array($method, array('group', 'groupCollapsed'))) {
                $args = array_slice($this->data['log'][$i], 1);
                $groupStack[] = array(
                    'i' => $i,
                    'meta' => $this->debug->internal->getMetaArg($args),
                    'hasEntries' => false,
                );
                $groupStackCount ++;
            } elseif ($method == 'groupEnd') {
                $group = end($groupStack);
                if (!$group['hasEntries'] && !empty($group['meta']['hideIfEmpty'])) {
                    // make it go away
                    unset($this->data['log'][$group['i']]);
                    unset($this->data['log'][$i]);
                    $removed = true;
                }
                array_pop($groupStack);
                $groupStackCount--;
            } elseif ($groupStack) {
                $groupStack[$groupStackCount - 1]['hasEntries'] = true;
            }
        }
        if ($removed) {
            $this->data['log'] = array_values($this->data['log']);
        }
    }

    /**
     * Cooerce value to string
     *
     * @param mixed $val value
     *
     * @return string
     */
    protected function substitutionAsString($val)
    {
        $type = $this->debug->abstracter->getType($val);
        if ($type == 'array') {
            $count = count($val);
            $val = 'Array('.$count.')';
        } elseif ($type == 'object') {
            $val = $val['className'];
        } else {
            $val = $this->dump($val);
        }
        return $val;
    }

    /**
     * Uncollapse groups containing errors.
     *
     * @return void
     */
    public function uncollapseErrors()
    {
        $groupStack = array();
        for ($i = 0, $count = count($this->data['log']); $i < $count; $i++) {
            $method = $this->data['log'][$i][0];
            if (in_array($method, array('group', 'groupCollapsed'))) {
                $groupStack[] = $i;
            } elseif ($method == 'groupEnd') {
                array_pop($groupStack);
            } elseif (in_array($method, array('error', 'warn'))) {
                foreach ($groupStack as $i2) {
                    $this->data['log'][$i2][0] = 'group';
                }
            }
        }
    }
}
