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

/**
 * General Output methods
 */
class Output
{

    private $cfg = array();
    private $data = array();
    private $debug;

    /**
     * Constructor
     *
     * @param object $debug debug instance
     * @param array  $cfg   configuration
     */
    public function __construct($debug, $cfg = array())
    {
        $this->debug = $debug;
        $this->cfg = array(
            'addBR' => false,
            'css' => '',                    // additional "override" css
            'filepathCss' => __DIR__.'/css/Debug.css',
            'filepathScript' => __DIR__.'/js/Debug.jquery.min.js',
            'onOutput'  => null,            // set to something callable
            'outputAs'  => null,            // 'chromeLogger', 'html', 'script', 'text', or Object, if null, will be determined automatically
            'outputAsDefaultNonHtml' => 'chromeLogger',
            'outputConstants' => true,
            'outputCss' => true,            // applies when outputAs = 'html'
            'outputMethodDescription' => true, // (or just summary)
            'outputMethods' => true,
            'outputScript' => true,         // applies when outputAs = 'html'
        );
        $this->setCfg($cfg);
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
        if (strpos($prop, 'output') === 0 && file_exists(__DIR__.'/'.ucfirst($prop).'.php')) {
            $classname = __NAMESPACE__.'\\'.ucfirst($prop);
            $this->{$prop} = new $classname($this->debug);
            return $this->{$prop};
        }
    }

    /**
     * Serializes and emails log
     *
     * @return void
     */
    public function emailLog()
    {
        $body = '';
        $unsuppressedError = false;
        /*
            List errors that occured
        */
        $errors = $this->debug->errorHandler->get('errors');
        uasort($errors, function ($a1, $a2) {
            return strcmp($a1['file'].$a1['line'], $a2['file'].$a2['line']);
        });
        $lastFile = '';
        foreach ($errors as $error) {
            if ($error['suppressed']) {
                continue;
            }
            if ($error['file'] !== $lastFile) {
                $body .= $error['file'].':'."\n";
                $lastFile = $error['file'];
            }
            $body .= '  Line '.$error['line'].': '.$error['message']."\n";
            $unsuppressedError = true;
        }
        $subject = 'Debug Log: '.$_SERVER['HTTP_HOST'].( $unsuppressedError ? ' (Error)' : '' );
        /*
            "attach" "serialized" log
        */
        $body .= 'Request: '.$_SERVER['REQUEST_METHOD'].': '.$_SERVER['REQUEST_URI']."\n\n";
        $body .= $this->debug->utilities->serializeLog($this->data['log']);
        /*
            Now email
        */
        $this->debug->internal->email($this->debug->getCfg('emailTo'), $subject, $body);
        return;
    }

    /**
     * get error statistics from errorHandler
     * how many errors were captured in/out of console
     * breakdown per error category
     *
     * @return array
     */
    public function errorStats()
    {
        $errors = $this->debug->errorHandler->get('errors');
        $stats = array(
            'inConsole' => 0,
            'inConsoleCategories' => 0,
            'notInConsole' => 0,
            'counts' => array(),
        );
        foreach ($errors as $error) {
            if ($error['suppressed']) {
                continue;
            }
            $category = $error['category'];
            if (!isset($stats['counts'][$category])) {
                $stats['counts'][$category] = array(
                    'inConsole' => 0,
                    'notInConsole' => 0,
                );
            }
            $k = $error['inConsole'] ? 'inConsole' : 'notInConsole';
            $stats['counts'][$category][$k]++;
        }
        foreach ($stats['counts'] as $a) {
            $stats['inConsole'] += $a['inConsole'];
            $stats['notInConsole'] += $a['notInConsole'];
            if ($a['inConsole']) {
                $stats['inConsoleCategories']++;
            }
        }
        ksort($stats['counts']);
        return $stats;
    }

    /**
     * Get config val
     *
     * @param string $path what to get
     *
     * @return mixed
     */
    public function getCfg($path)
    {
        if ($path == 'outputAs') {
            $ret = $this->cfg['outputAs'];
            if (empty($ret)) {
                $ret = $this->getDefaultOutputAs();
            } elseif (is_object($ret)) {
                $ret = get_class($ret);
                $ret = preg_replace('/^'.preg_quote(__NAMESPACE__.'\\Output').'/', '', $ret);
                $ret = lcfirst($ret);
            }
        } elseif ($path == 'css') {
            $ret = $this->getCss();
        } else {
            $path = array_filter(preg_split('#[\./]#', $path), 'strlen');
            $ret = $this->cfg;
            foreach ($path as $k) {
                if (isset($ret[$k])) {
                    $ret = $ret[$k];
                } else {
                    $ret = null;
                    break;
                }
            }
        }
        return $ret;
    }

    /**
     * Return the log's CSS
     *
     * @return string
     */
    public function getCss()
    {
        $return = file_get_contents($this->cfg['filepathCss']);
        if (!empty($this->cfg['css'])) {
            $return .=  "\n".$this->cfg['css']."\n";
        }
        return $return;
    }

    /**
     * Set one or more config values
     *
     * If setting a single value, old value is returned
     *
     * @param string $mixed  key=>value array or key
     * @param mixed  $newVal value
     *
     * @return mixed returns previous value
     */
    public function setCfg($mixed, $newVal = null)
    {
        $ret = null;
        $values = array();
        if (is_string($mixed)) {
            $key = $mixed;
            $ret = isset($this->cfg[$key])
                ? $this->cfg[$key]
                : null;
            $values = array(
                $key => $newVal,
            );
        } elseif (is_array($mixed)) {
            $values = $mixed;
        }
        if (isset($values['outputAs']) && is_string($values['outputAs'])) {
            $prop = 'output'.ucfirst($values['outputAs']);
            if (!property_exists($this, $prop) && file_exists(__DIR__.'/'.ucfirst($prop).'.php')) {
                $classname = __NAMESPACE__.'\\'.ucfirst($prop);
                $this->{$prop} = new $classname($this->debug);
            }
            if (property_exists($this, $prop)) {
                $this->debug->addPlugin($this->{$prop});
            }
        }
        if (isset($values['onOutput'])) {
            $this->debug->eventManager->subscribe('debug.output', $values['onOutput']);
            unset($values['onOutput']);
        }
        $this->cfg = $this->debug->utilities->arrayMergeDeep($this->cfg, $values);
        return $ret;
    }

    /**
     * Determine default outputAs
     *
     * @return string
     */
    protected function getDefaultOutputAs()
    {
        $ret = 'html';
        $interface = $this->debug->utilities->getInterface();
        if ($interface == 'ajax') {
            $ret = $this->cfg['outputAsDefaultNonHtml'];
        } elseif ($interface == 'http') {
            $contentType = $this->debug->utilities->getResponseHeader();
            if ($contentType && $contentType !== 'text/html') {
                $ret = $this->cfg['outputAsDefaultNonHtml'];
            }
        } else {
            $ret = 'text';
        }
        return $ret;
    }
}
