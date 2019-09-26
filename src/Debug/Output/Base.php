<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v2.3
 */

namespace bdk\Debug\Output;

use bdk\Debug;
use bdk\Debug\MethodTable;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;

/**
 * Base output plugin
 */
abstract class Base implements OutputInterface
{

    public $debug;
    protected $data = array();
    protected $channelName = null;    // should be set by onOutput
    protected $channelNameRoot = null;
    protected $channelRegex;
    protected $isRootInstance = false;
    protected $dumpType;
    protected $dumpTypeMore;
    protected $name = '';

    /**
     * Constructor
     *
     * @param \bdk\Debug $debug debug instance
     */
    public function __construct(Debug $debug)
    {
        $this->debug = $debug;
        if (!$this->name) {
            $name = \get_called_class();
            $idx = \strrpos($name, '\\');
            if ($idx) {
                $name = \substr($name, $idx + 1);
                $name = \lcfirst($name);
            }
            $this->name = $name;
        }
        $this->channelName = $this->debug->getCfg('channelName');
        $this->channelNameRoot = $this->debug->rootInstance->getCfg('channelName');
        $this->channelRegex = '#^'.\preg_quote($this->channelName, '#').'(\.|$)#';
        $this->isRootInstance = $this->debug->rootInstance === $this->debug;
    }

    /**
     * Magic getter
     *
     * @param string $prop property to get
     *
     * @return mixed
     */
    public function __get($prop)
    {
        $getter = 'get'.\ucfirst($prop);
        if (\method_exists($this, $getter)) {
            return $this->{$getter}();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function dump($val)
    {
        $typeMore = null;
        $type = $this->debug->abstracter->getType($val, $typeMore);
        if ($typeMore == 'raw') {
            $val = $this->debug->abstracter->getAbstraction($val);
            $typeMore = null;
        } elseif ($typeMore == 'abstraction') {
            $typeMore = null;
        }
        $method = 'dump'.\ucfirst($type);
        $return = $this->{$method}($val);
        $this->dumpType = $type;
        $this->dumpTypeMore = $typeMore;
        return $return;
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
     * debug.output subscriber
     *
     * @param Event $event debug.output event object
     *
     * @return void
     */
    abstract public function onOutput(Event $event);

    /**
     * Process log entry without publishing `debug.outputLogEntry` event
     *
     * @param string $method method
     * @param array  $args   args
     * @param array  $meta   meta values
     *
     * @return mixed
     */
    abstract public function processLogEntry($method, $args = array(), $meta = array());

    /**
     * Test channel for inclussion
     *
     * @param string $channel channel name to test against
     *
     * @return boolean
     */
    protected function channelTest($channel)
    {
        return $this->isRootInstance || \preg_match($this->channelRegex, $channel);
    }

    /**
     * Get name property
     *
     * @return string
     */
    protected function getName()
    {
        return $this->name;
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
        $tsNow = \time();
        if ($val > $tsNow - $secs && $val < $tsNow + $secs) {
            return \date('Y-m-d H:i:s', $val);
        }
        return false;
    }

    /**
     * Dump array
     *
     * @param array $array array to dump
     *
     * @return array
     */
    protected function dumpArray($array)
    {
        foreach ($array as $key => $val) {
            $array[$key] = $this->dump($val);
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
     * @param array $abs object abstraction
     *
     * @return mixed
     */
    protected function dumpObject($abs)
    {
        if ($abs['isRecursion']) {
            $return = '(object) '.$abs['className'].' *RECURSION*';
        } elseif ($abs['isExcluded']) {
            $return = '(object) '.$abs['className'].' (not inspected)';
        } else {
            $return = array(
                '___class_name' => $abs['className'],
            );
            foreach ($abs['properties'] as $name => $info) {
                $vis = (array) $info['visibility'];
                foreach ($vis as $i => $v) {
                    if (\in_array($v, array('magic','magic-read','magic-write'))) {
                        $vis[$i] = '✨ '.$v;    // "sparkles": there is no magic-want unicode char
                    } elseif ($v == 'private' && $info['inheritedFrom']) {
                        $vis[$i] = '🔒 '.$v;
                    }
                }
                if ($info['isExcluded']) {
                    $vis[] = 'excluded';
                }
                $name = '('.\implode(' ', $vis).') '.$name;
                $return[$name] = $this->dump($info['value']);
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
        return 'array *RECURSION*';
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
        if (\is_numeric($val)) {
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
     * Handle alert method
     *
     * @param array $args arguments
     * @param array $meta meta info
     *
     * @return array array($method, $args)
     */
    protected function methodAlert($args, $meta)
    {
        $classToMethod = array(
            'danger' => 'error',
            'info' => 'info',
            'success' => 'info',
            'warning' => 'warn',
        );
        $msg = \str_replace('<br />', ", \n", $args[0]);
        $method = $meta['class'];
        if (isset($classToMethod[$method])) {
            $method = $classToMethod[$method];
        }
        return array($method, array($msg));
    }

    /**
     * Normalize table data
     *
     * Ensures each row has all key/values and that they're in the same order
     * if any row is an object, each row will get a ___class_name value
     *
     * This builds table rows usable by ChromeLogger, Text, and <script>
     *
     * @param array $array   array to debug
     * @param array $columns columns to output
     *
     * @return array
     */
    protected function methodTable($array, $columns = array())
    {
        if (!\is_array($array)) {
            return $this->dump($array);
        }
        $keys = $columns ?: $this->debug->methodTable->colKeys($array);
        $table = array();
        $classnames = array();
        if ($this->debug->abstracter->isAbstraction($array) && $array['traverseValues']) {
            $array = $array['traverseValues'];
        }
        foreach ($array as $k => $row) {
            $values = $this->debug->methodTable->keyValues($row, $keys, $objInfo);
            $values = $this->methodTableCleanValues($values);
            $table[$k] = $values;
            $classnames[$k] = $objInfo['row']
                ? $objInfo['row']['className']
                : '';
        }
        if (\array_filter($classnames)) {
            foreach ($classnames as $k => $classname) {
                $table[$k] = \array_merge(
                    array('___class_name' => $classname),
                    $table[$k]
                );
            }
        }
        return $table;
    }

    /**
     * Ready row value(s)
     *
     * @param array $values row values
     *
     * @return row values
     */
    private function methodTableCleanValues($values)
    {
        foreach ($values as $k2 => $val) {
            if ($val === $this->debug->abstracter->UNDEFINED) {
                unset($values[$k2]);
            } elseif (\is_array($val)) {
                $values[$k2] = $this->debug->output->text->dump($val);
            }
        }
        if (\count($values) == 1 && $k2 == MethodTable::SCALAR) {
            $values = $val;
        }
        return $values;
    }

    /**
     * Process alerts
     *
     * By default we just output alerts like error(), info(), and warn() calls
     *
     * @return string
     */
    protected function processAlerts()
    {
        $str = '';
        foreach ($this->data['alerts'] as $entry) {
            $channel = isset($entry[2]['channel']) ? $entry[2]['channel'] : null;
            if ($this->channelTest($channel)) {
                $str .= $this->processLogEntryWEvent($entry[0], $entry[1], $entry[2]);
            }
        }
        return $str;
    }

    /**
     * Process log entries
     *
     * @return string
     */
    protected function processLog()
    {
        $str = '';
        foreach ($this->data['log'] as $entry) {
            $channel = isset($entry[2]['channel']) ? $entry[2]['channel'] : null;
            if ($this->channelTest($channel)) {
                $str .= $this->processLogEntryWEvent($entry[0], $entry[1], $entry[2]);
            }
        }
        return $str;
    }

    /**
     * Publish debug.outputLogEntry.
     * Return event['return'] if not empty
     * Otherwise, propagation not stopped, return result of processLogEntry()
     *
     * @param string $method method
     * @param array  $args   args
     * @param array  $meta   meta values
     *
     * @return mixed
     */
    protected function processLogEntryWEvent($method, $args = array(), $meta = array())
    {
        if (!isset($meta['channel'])) {
            $meta['channel'] = $this->channelNameRoot;
        }
        $event = new Event($this, array(
            'method' => $method,
            'args' => $args,
            'meta' => $meta,
            'return' => null,
        ));
        $this->debug->internal->publishBubbleEvent('debug.outputLogEntry', $event, $this->debug);
        if ($event['return'] !== null) {
            return $event['return'];
        }
        return $this->processLogEntry($event['method'], $event['args'], $event['meta']);
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
     * @see https://developer.mozilla.org/en-US/docs/Web/API/console#Using_string_substitutions
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
        if (!\is_string($args[0])) {
            return $args;
        }
        $index = 0;
        $indexes = array(
            'c' => array(),
        );
        $hasSubs = false;
        $args[0] = \preg_replace_callback($subRegex, function ($matches) use (
            &$args,
            &$hasSubs,
            &$index,
            &$indexes
        ) {
            $hasSubs = true;
            $index++;
            $replacement = $matches[0];
            $type = \substr($matches[0], -1);
            if (\strpos('difs', $type) !== false) {
                $format = $matches[0];
                $sub = $args[$index];
                if ($type == 'i') {
                    $format = \substr_replace($format, 'd', -1, 1);
                } elseif ($type === 's') {
                    $sub = $this->substitutionAsString($sub);
                }
                $replacement = \sprintf($format, $sub);
            } elseif ($type === 'c') {
                $asHtml = \get_called_class() == __NAMESPACE__.'\\Html';
                if (!$asHtml) {
                    return '';
                }
                $replacement = '';
                if ($indexes['c']) {
                    // close prev
                    $replacement = '</span>';
                }
                $replacement .= '<span'.$this->debug->utilities->buildAttribString(array(
                    'style' => $args[$index],
                )).'>';
                $indexes['c'][] = $index;
            } elseif (\strpos('oO', $type) !== false) {
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
        if ($summaryData) {
            \krsort($summaryData);
            $summaryData = \call_user_func_array('array_merge', $summaryData);
        }
        foreach ($summaryData as $entry) {
            $channel = isset($entry[2]['channel']) ? $entry[2]['channel'] : null;
            if ($this->channelTest($channel)) {
                $str .= $this->processLogEntryWEvent($entry[0], $entry[1], $entry[2]);
            }
        }
        return $str;
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
            $count = \count($val);
            $val = 'array('.$count.')';
        } elseif ($type == 'object') {
            $val = $val['className'];
        } else {
            $val = $this->dump($val);
        }
        return $val;
    }
}
