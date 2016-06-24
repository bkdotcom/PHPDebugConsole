<?php
/**
 * FirePHP Output methods
 *
 * @package PHPDebugConsole
 * @author  Brad Kent <bkfake-github@yahoo.com>
 * @license http://opensource.org/licenses/MIT MIT
 * @version v1.3.3
 */

namespace bdk\Debug;

/**
 * Output methods
 */
class OutputFirephp
{

    private $data = array();
    private $debug = null;
    protected $firephpMethods;
    public $firephp;

    /**
     * Constructor
     *
     * @param object $debug debug instance
     * @param array  $data data
     */
    public function __construct($debug, &$data = array())
    {
        $this->debug = $debug;
        $this->data = &$data;
        $firephpInc = $this->debug->output->get('firephpInc');
        if (file_exists($firephpInc)) {
            require_once $firephpInc;
            $this->firephp = \FirePHP::getInstance(true);
            $this->firephp->setOptions($this->debug->output->get('firephpOptions'));
            $this->firephpMethods = get_class_methods($this->firephp);
        }
    }

    /**
     * Pass the log to FirePHP methods
     *
     * @return void
     */
    public function output()
    {
        if (!$this->firephp) {
        	return;
        }
        if (!empty($this->data['alert'])) {
            $alert = str_replace('<br />', ", \n", $this->data['alert']);
            array_unshift($this->data['log'], array('error', $alert));
        }
        foreach ($this->data['log'] as $args) {
            $method = array_shift($args);
            $this->outputFirephpLogEntry($method, $args);
        }
        while ($this->data['groupDepth'] > 0) {
            $this->firephp->groupEnd();
            $this->data['groupDepth']--;
        }
        return;
    }

    /**
     * output a log entry to Firephp
     *
     * @param string $method method
     * @param array  $args   args
     *
     * @return void
     */
    protected function outputFirephpLogEntry($method, $args)
    {
        $opts = array();
        if (in_array($method, array('error','warn'))) {
            $meta = $this->debug->output->getMetaArg($args);
            if ($meta && isset($meta['file'])) {
                $opts = array(
                    'File' => $meta['file'],
                    'Line' => $meta['line'],
                );
            }
        }
        foreach ($args as $k => $arg) {
            $args[$k] = $this->debug->varDump->dump($arg, 'firephp');
        }
        if (in_array($method, array('group','groupCollapsed','groupEnd'))) {
            list($method, $args, $opts) = $this->outputFirephpGroupMethod($method, $args);
        } elseif ($method == 'table' && is_array($args[0])) {
            $label = isset($args[1])
                ? $args[1]
                : 'table';
            $keys = $this->debug->utilities->arrayColkeys($args[0]);
            $table = array();
            $table[] = $keys;
            array_unshift($table[0], '');
            foreach ($args[0] as $k => $row) {
                $values = array($k);
                foreach ($keys as $key) {
                    $values[] = isset($row[$key])
                        ? $row[$key]
                        : null;
                }
                $table[] = $values;
            }
            $args = array($label, $table);
        } elseif ($method == 'table') {
            $method = 'log';
        } else {
            if (count($args) > 1) {
                $label = array_shift($args);
                if (count($args) > 1) {
                    $args = array( implode(', ', $args) );
                }
                $args[] = $label;
            } elseif (is_string($args[0])) {
                $args[0] = strip_tags($args[0]);
            }
        }
        if (!in_array($method, $this->firephpMethods)) {
            $method = 'log';
        }
        if ($opts) {
            // opts array needs to be 2nd arg for group method, 3rd arg for all others
            if ($method !== 'group' && count($args) == 1) {
                $args[] = null;
            }
            $args[] = $opts;
        }
        call_user_func_array(array($this->firephp,$method), $args);
        return;
    }

    /**
     * handle firephp output of group, groupCollapsed, & groupEnd
     *
     * @param string $method method
     * @param array  $args   args passed to method
     *
     * @return array [$method, $args, $opts]
     */
    protected function outputFirephpGroupMethod($method, $args = array())
    {
        $opts = array();
        if (in_array($method, array('group','groupCollapsed'))) {
            $firephpMethod = 'group';
            $this->data['groupDepth']++;
            $opts = array(
                'Collapsed' => $method == 'groupCollapsed',    // collapse both group and groupCollapsed
            );
            if (empty($args)) {
                $args[] = 'group';
            } elseif (count($args) > 1) {
                $more = array_splice($args, 1);
                $args[0] .= ' - '.implode(', ', $more);
            }
        } elseif ($method == 'groupEnd') {
            $firephpMethod = 'groupEnd';
            $this->data['groupDepth']--;
        }
        return array($firephpMethod, $args, $opts);
    }
}
