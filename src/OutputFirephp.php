<?php
/**
 * Output log via FirePHP
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
class OutputFirephp implements PluginInterface
{

    private $debug;
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

    const FIREPHP_PROTO_VER = '0.3';
    const MESSAGE_LIMIT = 99999;

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
     * Output the log via FirePHP headers
     *
     * @param Event $event debug.log event object
     *
     * @return void
     */
    public function output(Event $event = null)
    {
        if (headers_sent($file, $line)) {
            trigger_error('Unable to FirePHP: headers already sent. ('.$file.' line '.$line.')', E_USER_NOTICE);
            return;
        }
        $this->debug->internal->uncollapseErrors();
        $data = $this->debug->getData();
        $this->setHeader('X-Wf-Protocol-1', 'http://meta.wildfirehq.org/Protocol/JsonStream/0.2');
        $this->setHeader('X-Wf-1-Plugin-1', 'http://meta.firephp.org/Wildfire/Plugin/FirePHP/Library-FirePHPCore/'.self::FIREPHP_PROTO_VER);
        $this->setHeader('X-Wf-1-Structure-1', 'http://meta.firephp.org/Wildfire/Structure/FirePHP/FirebugConsole/0.1');
        $this->processAlerts($data['alerts']);
        foreach ($data['log'] as $args) {
            $method = array_shift($args);
            $this->processEntry($method, $args);
        }
        $this->setHeader('X-Wf-1-Index', $this->messageIndex);
        return;
    }

    /**
     * Dump a value
     *
     * @param mixed $val value
     *
     * @return mixed
     */
    protected function dump($val)
    {
        $typeMore = null;
        $type = $this->debug->abstracter->getType($val, $typeMore);
        if ($type == 'array') {
            $val = $this->dumpArray($val);
        } elseif ($type == 'callable') {
            $val = $this->dumpCallable($val);
        } elseif ($type == 'object') {
            $val = $this->dumpObject($val);
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
        return $val;
    }

    /**
     * [dumpAbstraction description]
     *
     * @param array $abs abstraction array
     *
     * @return string
     */
    /*
    protected function dumpAbstraction($abs)
    {
        $type = $abs['type'];
        if ($type == 'callable') {
            return $this->dumpCallable($abs);
        } elseif ($type == 'object') {
            return $this->dumpObject($abs);
        } else {
            return $abs['value'];
        }
    }
    */

    /**
     * dump array
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
     * @param array $abs object abstraction
     *
     * @return array
     */
    protected function dumpObject($abs)
    {
        $return = array(
            '___class_name' => $abs['className'],   // we'll just borrow this from chromelogger
        );
        foreach ($abs['properties'] as $name => $info) {
            $return[$name] = $this->dump($info['value']);
        }
        return $return;
    }

    /**
     * process alerts
     *
     * @param array $alerts alerts data
     *
     * @return void
     */
    protected function processAlerts($alerts = array())
    {
        $trans = array(
            'danger' => 'error',
            'success' => 'info',
            'warning' => 'warn',
        );
        foreach ($alerts as $alert) {
            $str = str_replace('<br />', ", \n", $alert['message']);
            $method = $alert['class'];
            if (isset($trans[$method])) {
                $method = $trans[$method];
            }
            $this->processEntry($method, array($str));
        }
    }

    /**
     * output a log entry to Firephp
     *
     * @param string $method method
     * @param array  $args   args
     *
     * @return void
     */
    protected function processEntry($method, $args)
    {
        // $opts = array();
        $meta = array(
            'Type' => isset($this->firephpMethods[$method])
                ? $this->firephpMethods[$method]
                : $this->firephpMethods['log'],
            // Label
            // File
            // Line
            // Collapsed  (for group)
        );
        $value = null;
        if (in_array($method, array('error','warn'))) {
            $argsMeta = $this->debug->output->getMetaArg($args);
            if (isset($argsMeta['file'])) {
                $meta['File'] = $argsMeta['file'];
                $meta['Line'] = $argsMeta['line'];
            }
        }
        foreach ($args as $i => $arg) {
            $args[$i] = $this->dump($arg);
        }
        if (in_array($method, array('group','groupCollapsed'))) {
            $meta['Label'] = $args[0];
            if ($method == 'groupCollapsed') {
                $meta['Collapsed'] = 'true'; // yes, a string
            }
        } elseif ($method == 'table') {
            $value = $this->processTable($args[0]);
            if (isset($args[1])) {
                $meta['Label'] = $args[1];
            }
        } elseif (count($args)) {
            if (count($args) == 1) {
                $value = $args[0];
                // no label;
            } else {
                $meta['Label'] = array_shift($args);
                $value = count($args) > 1
                    ? $args // firephp only supports label/value...  we'll pass multiple values as an array
                    : $args[0];
            }
        }
        if ($this->messageIndex < self::MESSAGE_LIMIT) {
            $this->setFirephpHeader($meta, $value);
        } elseif ($this->messageIndex === self::MESSAGE_LIMIT) {
            $this->setFirephpHeader(
                array('Type'=>$this->firephpMethods['warn']),
                'Limit of '.number_format(self::MESSAGE_LIMIT).' firePhp messages reached!'
            );
        }
        return;
    }

    /**
     * Build table rows
     *
     * @param array $array array to debug
     *
     * @return array
     */
    protected function processTable($array)
    {
        $keys = $this->debug->utilities->arrayColkeys($array);
        $table = array();
        $table[] = $keys;
        $classNames = array();
        array_unshift($table[0], '');
        foreach ($array as $k => $row) {
            $values = $this->debug->abstracter->keyValues($row, $keys, $objInfo);
            $values = array_map(function ($val) {
                return $val === $this->debug->abstracter->UNDEFINED
                    ? null
                    : $val;
            }, $values);
            $classNames[] = $objInfo
                ? $objInfo['className']
                : '';
            array_unshift($values, $k);
            $table[] = $values;
        }
        if (array_filter($classNames)) {
            array_unshift($table[0], '');
            foreach ($classNames as $i => $className) {
                array_splice($table[$i+1], 1, 0, $className);
            }
        }
        return $table;
    }

    /**
     * [setFirephpHeader description]
     *
     * @param array $meta  meta information
     * @param mixed $value value
     *
     * @return void
     */
    private function setFirephpHeader($meta, $value = null)
    {
        $msg = json_encode(array(
            $meta,
            $value,
        ));
        $structureIndex = 1;    // refers to X-Wf-1-Structure-1
        $parts = explode("\n", rtrim(chunk_split($msg, 5000, "\n")));
        $numParts = count($parts);
        for ($i=0; $i<$numParts; $i++) {
            $part = $parts[$i];
            $this->messageIndex++;
            $headerName = 'X-Wf-1-'.$structureIndex.'-1-'.$this->messageIndex;
            $headerValue = ( $i==0 ? strlen($msg) : '')
                . '|' . $part . '|'
                . ( $i<$numParts-1 ? '\\' : '' );
            $this->setHeader($headerName, $headerValue);
        }
    }

    /**
     * Tis but a simple wrapper for php's header() func
     *
     * @param string $name  header name
     * @param string $value header value
     *
     * @return void
     */
    private function setHeader($name, $value)
    {
        header($name.': '.$value);
    }
}
