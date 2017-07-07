<?php
/**
 * Output log as via ChromeLogger headers
 *
 * ChromeLogger supports the following methods/log-types:
 * log, warn, error, info, group, groupEnd, groupCollapsed, and table
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
 * Output methods
 */
class OutputChromeLogger implements PluginInterface
{

    private $debug;

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
                // echo 'headers already sent ('.$file.', '.$line.')';
            } elseif (strlen($encoded) > 250000) {

            } else {
                header(self::HEADER_NAME . ': ' . $encoded);
            }
        }
    }

    /**
     * Dump value as text
     *
     * @param mixed $val value to dump
     *
     * @return string
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

    /*
    protected function dumpAbstraction($val)
    {
        $type = $val['type'];
        if ($type == 'callable') {
            $val = $this->dumpCallable($val);
        } elseif ($type == 'object') {
            $val = $this->dumpObject($val);
        } else {
            $val = $val['value'];
        }
        return $val;
    }
    */

    protected function dumpArray($array)
    {
        // $pathCount = count($path);
        foreach ($array as $key => $val) {
            // $path[$pathCount] = $key;
            $array[$key] = $this->dump($val);
        }
        return $array;
    }

    /**
     * Dump "Callable" as html
     *
     * @param array $abs array/callable abstraction
     *
     * @return string
     */
    protected function dumpCallable($abs)
    {
        return 'Callable: '.$abs['values'][0].'::'.$abs['values'][1];
    }

    /**
     * Dump object
     *
     * @param array $abs object abstraction
     *
     * @return array
     */
    protected function dumpObject($abs)
    {
        $return = array(
            '___class_name' => $abs['className'],
        );
        foreach ($abs['properties'] as $name => $info) {
            $return[$name] = $this->dump($info['value']);
        }
        return $return;
    }

    /**
     * encode data for header
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
            $args = array($this->processTable($args[0]));
        }
        foreach ($args as $i => $arg) {
            $args[$i] = $this->dump($arg);
        }
        if ($method === 'log') {
            $method = '';
        }
        $this->json['rows'][] = array($args, $metaStr, $method);
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
            // array_unshift($values, $k);
            $table[$k] = $values;
        }
        if (array_filter($classnames)) {
            // array_unshift($table[0], '');
            foreach ($classnames as $k => $classname) {
                $table[$k] = array_merge(
                    array('___class_name' => $classname),
                    $table[$k]
                );
            }
        }
        return $table;
    }
}
