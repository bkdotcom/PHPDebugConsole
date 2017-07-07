<?php
/**
 * Output log as <script> tag
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2017 Brad Kent
 * @version   v1.4.0
 */

namespace bdk\Debug;

/**
 * Output methods
 */
class OutputScript implements PluginInterface
{

    private $debug;

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
    public function debugListeners(\bdk\Debug $debug)
    {
        return array(
            'debug.output' => 'output',
        );
    }

    /**
     * Dump a value for script
     *
     * @param mixed $val  value to dump
     * @param array $path {@internal}
     *
     * @return [type]  [description]
     */
    public function dump($val, $path = array())
    {
        $typeMore = null;
        $type = $this->debug->abstracter->getType($val, $typeMore);
        if ($typeMore == 'raw') {
            $val = $this->debug->abstracter->getAbstraction($val);
            $typeMore = 'abstraction';
        }
		if ($type == 'array') {
            $val = $this->dumpArray($val, $path);
        } elseif ($type == 'callable') {
            $val = $this->dumpCallable($val);
        } elseif ($type == 'object') {
            $val = $this->dumpObject($val, $path);
        } elseif ($typeMore == 'abstraction') {
            // resource
            $val = $val['value'];
        } elseif ($type == 'recursion') {
            $val = 'Array *RECURSION*';
        } elseif ($type == 'string') {
            if ($typeMore !== 'numeric') {
                $val = $this->debug->utf8->dump($val);
            }
        } elseif ($type == 'undefined') {
            $val = 'undefined';
        }
        // bool, float, int, null : no modification required
        if (in_array($type, array('float','int')) || $typeMore == 'numeric') {
            $tsNow = time();
            $secs = 86400 * 90; // 90 days worth o seconds
            if ($val > $tsNow - $secs && $val < $tsNow + $secs) {
                $val = $val.' ('.date('Y-m-d H:i:s', $val).')';
            }
        }
        if (empty($path)) {
            $val = json_encode($val);
        }
        return $val;
    }

    /**
     * output the log as javascript
     *    which outputs the log to the console
     *
     * @param Event $event event object
     *
     * @return string
     */
    public function output(Event $event)
    {
        // $this->data = &$data;
        $data = $this->debug->getData();
        $this->debug->internal->uncollapseErrors();
        $label = 'PHP';
        $errorStats = $this->debug->output->errorStats();
        if ($errorStats['inConsole']) {
            $label .= ' - Errors (';
            foreach ($errorStats['counts'] as $category => $vals) {
                $label .= $vals['inConsole'].' '.$category.', ';
            }
            $label = substr($label, 0, -2);
            $label .= ')';
        }
        $str = '<script type="text/javascript">'."\n";
        $str .= 'console.groupCollapsed("'.$label.'");'."\n";
        foreach ($data['log'] as $args) {
            $method = array_shift($args);
            if ($method == 'assert') {
                array_unshift($args, false);
            } elseif ($method == 'count' || $method == 'time') {
                $method = 'log';
            } elseif ($method == 'table') {
                foreach ($args as $i => $v) {
                    if (!is_array($v)) {
                        unset($args[$i]);
                    }
                }
            } elseif (in_array($method, array('error','warn'))) {
                $meta = $this->debug->output->getMetaArg($args);
                if (isset($meta['file'])) {
                    $args[] = $meta['file'].': line '.$meta['line'];
                }
            }
            foreach ($args as $k => $arg) {
                $args[$k] = $this->dump($arg);
            }
            $str .= 'console.'.$method.'('.implode(',', $args).");\n";
        }
        $str .= 'console.groupEnd();';
        $str .= '</script>';
        return $str;
    }

    /**
     * [dumpAbstraction description]
     *
     * @param array $abs  abstraction array
     * @param array $path path
     *
     * @return string
     */
    /*
    protected function dumpAbstraction($abs, $path)
    {
        $type = $abs['type'];
        if ($type == 'callable') {
            return $this->dumpCallable($abs);
        } elseif ($type == 'object') {
            return $this->dumpObject($abs, $path); // @todo
        } else {
            return $abs['value'];
        }
    }
    */

    /**
     * [dumpArray description]
     *
     * @param array $array array to dump
     * @param array $path  path
     *
     * @return array
     */
    protected function dumpArray($array, $path)
    {
        $pathCount = count($path);
        foreach ($array as $key => $val) {
            $path[$pathCount] = $key;
            $array[$key] = $this->dump($val, $path);
        }
        return $array;
    }

    /**
     * [dumpCallable description]
     *
     * @param array $abs abstraction
     *
     * @return string
     */
    protected function dumpCallable($abs)
    {
        return 'callable: '.$abs['values'][0].'::'.$abs['values'][1];
    }

    /**
     * Get the minimal amount of object info to be useful
     *
     * @param array $abs  object abstraction
     * @param array $path path
     *
     * @return array
     */
    protected function dumpObject($abs, $path)
    {
        $return = array(
            '___class_name' => $abs['className'],   // we'll just borrow this from chromelogger
        );
        $pathCount = count($path);
        foreach ($abs['properties'] as $name => $info) {
            $path[$pathCount] = $name;
            $return[$name] = $this->dump($info['value'], $path);
        }
        return $return;
    }
}
