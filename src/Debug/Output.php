<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2018 Brad Kent
 * @version   v2.1.0
 */

namespace bdk\Debug;

use bdk\PubSub\SubscriberInterface;

/**
 * General Output methods
 */
class Output
{

    private $cfg = array();
    private $debug;
    private $subscribers = array();

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
            'displayListKeys' => true,
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
        if (\strpos($prop, 'output') === 0) {
            $classname = __NAMESPACE__.'\\Output\\'.\substr($prop, 6);
            if (\class_exists($classname)) {
                $this->{$prop} = new $classname($this->debug);
                // note: we don't add as plugin / subscriberInterface here
                return $this->{$prop};
            }
        }
    }

    /**
     * Get config val
     *
     * @param string $path what to get
     *
     * @return mixed
     */
    public function getCfg($path = null)
    {
        if ($path == 'outputAs') {
            $ret = $this->cfg['outputAs'];
            if (empty($ret)) {
                $ret = $this->getDefaultOutputAs();
            } elseif (\is_object($ret)) {
                $ret = \get_class($ret);
                $ret = \preg_replace('/^'.\preg_quote(__NAMESPACE__.'\\Output\\').'/', '', $ret);
                $ret = \lcfirst($ret);
            }
        } elseif ($path == 'css') {
            $ret = $this->getCss();
        } else {
            $path = \array_filter(\preg_split('#[\./]#', $path), 'strlen');
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
        $return = \file_get_contents($this->cfg['filepathCss']);
        $return .= "\n";
        if (!empty($this->cfg['css'])) {
            $return .= $this->cfg['css']."\n";
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
        if (\is_string($mixed)) {
            $key = $mixed;
            $ret = isset($this->cfg[$key])
                ? $this->cfg[$key]
                : null;
            $values = array(
                $key => $newVal,
            );
        } elseif (\is_array($mixed)) {
            $values = $mixed;
        }
        if (isset($values['outputAs'])) {
            $outputAs = $values['outputAs'];
            if (\is_string($outputAs)) {
                $prop = 'output'.\ucfirst($outputAs);
                $classname = __NAMESPACE__.'\\Output\\'.\ucfirst($outputAs);
                if (\class_exists($classname)) {
                    if (!\property_exists($this, $prop)) {
                        $this->{$prop} = new $classname($this->debug);
                    }
                    if (!\in_array($prop, $this->subscribers)) {
                        $this->subscribers[] = $prop;
                        $this->debug->addPlugin($this->{$prop});
                    }
                }
            } elseif ($outputAs instanceof SubscriberInterface) {
                $classname = \get_class($outputAs);
                $prefix = __NAMESPACE__.'\\Output\\';
                if (\strpos($classname, $prefix) == 0) {
                    $prop = 'output'.\substr($classname, \strlen($prefix));
                    $this->{$prop} = $outputAs;
                    $this->subscribers[] = $prop;
                }
                $this->debug->addPlugin($outputAs);
            }
        }
        if (isset($values['onOutput'])) {
            /*
                Replace - not append - subscriber set via setCfg
            */
            if (isset($this->cfg['onOutput'])) {
                $this->debug->eventManager->unsubscribe('debug.output', $this->cfg['onOutput']);
            }
            $this->debug->eventManager->subscribe('debug.output', $values['onOutput']);
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
