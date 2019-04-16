<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug;

use bdk\Debug;
use bdk\Debug\Output\OutputInterface;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;

/**
 * General Output methods
 */
class Output implements SubscriberInterface
{

    private $cfg = array();
    private $debug;

    /**
     * Constructor
     *
     * @param \bdk\Debug $debug debug instance
     * @param array      $cfg   configuration
     */
    public function __construct(Debug $debug, $cfg = array())
    {
        $this->debug = $debug;
        $this->cfg = array(
            'displayListKeys' => true,
            'onOutput'  => null,            // set to something callable
            'outputAs'  => null,            // 'chromeLogger', 'html', 'script', 'text', or Object, if null, will be determined automatically
            'outputAsDefaultNonHtml' => 'chromeLogger',
            'outputConstants' => true,
            'outputHeaders' => true,        // ie, ChromeLogger and/or firePHP headers
            'outputMethodDescription' => true, // (or just summary)
            'outputMethods' => true,
            // Html options
            'addBR' => false,
            'css' => '',                    // additional "override" css
            'drawer' => true,
            'filepathCss' => __DIR__.'/css/Debug.css',
            'filepathScript' => __DIR__.'/js/Debug.jquery.min.js',
            'jqueryUrl' => '//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js',
            'outputCss' => true,            // applies when outputAs = 'html'
            'outputScript' => true,         // applies when outputAs = 'html'
            'sidebar' => true,
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
        $classname = __NAMESPACE__.'\\Output\\'.\ucfirst($prop);
        if (\class_exists($classname)) {
            $this->{$prop} = new $classname($this->debug);
            // note: we don't add as plugin / OutputInterface here
            return $this->{$prop};
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
            if (!$ret) {
                $ret = $this->getDefaultOutputAs();
            }
        } elseif ($path == 'css') {
            $ret = $this->getCss();
        } else {
            $ret = $this->debug->utilities->arrayPathGet($this->cfg, $path);
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
        $return = '';
        if ($this->cfg['filepathCss']) {
            $return = \file_get_contents($this->cfg['filepathCss']);
            if ($return === false) {
                $return = '/* Unable to read filepathCss */';
                $this->debug->alert('unable to read filepathCss');
            }
        }
        if (!empty($this->cfg['css'])) {
            $return .= "\n";
            $return .= $this->cfg['css']."\n";
        }
        return $return;
    }

    /**
     * Return the log's javascript
     *
     * @return string
     */
    public function getScript()
    {
        $return = '';
        if ($this->cfg['filepathScript']) {
            $return = \file_get_contents($this->cfg['filepathScript']);
            if ($return === false) {
                $return = 'console.warn("PHPDebugConsole: unable to read filepathScript");';
                $this->debug->alert('unable to read filepathScript');
            }
        }
        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscriptions()
    {
        return array(
            'debug.output' => array('onOutput', 1),
        );
    }

    /**
     * debug.output subscriber
     *
     * @param Event $event debug.output event object
     *
     * @return void
     */
    public function onOutput(Event $event)
    {
        if (!$event['isTarget']) {
            /*
                All channels share the same data.
                We only need to do this via the channel that called output
            */
            return;
        }
        $this->data = $this->debug->getData();
        $this->closeOpenGroups();
        $this->removeHideIfEmptyGroups($this->data['log']);
        $this->uncollapseErrors($this->data['log']);
        foreach ($this->data['logSummary'] as &$log) {
            $this->removeHideIfEmptyGroups($log);
            $this->uncollapseErrors($log);
        }
        $this->debug->setData($this->data);
    }

    /**
     * Set one or more config values
     *
     *    setCfg('key', 'value')
     *    setCfg(array('k1'=>'v1', 'k2'=>'v2'))
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
            $ret = isset($this->cfg[$mixed])
                ? $this->cfg[$mixed]
                : null;
            $values = array(
                $mixed => $newVal,
            );
        } elseif (\is_array($mixed)) {
            $ret = \array_intersect_key($this->cfg, $mixed);
            $values = $mixed;
        }
        if (isset($values['filepathCss'])) {
            $values['filepathCss'] = \preg_replace('#^\./?#', __DIR__.'/', $values['filepathCss']);
        }
        if (isset($values['filepathScript'])) {
            $values['filepathScript'] = \preg_replace('#^\./?#', __DIR__.'/', $values['filepathScript']);
        }
        if (isset($values['outputAs'])) {
            $this->setOutputAs($values['outputAs']);
            unset($values['outputAs']); // setOutputAs does the setting
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
     * Close any unclosed groups
     *
     * We may have forgotten to end a group or the script may have exited
     *
     * @return void
     */
    private function closeOpenGroups()
    {
        $this->data['groupPriorityStack'][] = 'main';
        while ($this->data['groupPriorityStack']) {
            $priority = \array_pop($this->data['groupPriorityStack']);
            foreach ($this->data['groupStacks'][$priority] as $i => $info) {
                if ($info['collect']) {
                    unset($this->data['groupStacks'][$priority][$i]);
                    $logEntry = new LogEntry(
                        $this->debug,
                        'groupEnd',
                        array(),
                        array(
                            'channel' => $info['channel'],
                        )
                    );
                    if ($priority === 'main') {
                        $this->data['log'][] = $logEntry;
                    } else {
                        $this->data['logSummary'][$priority][] = $logEntry;
                    }
                }
            }
        }
    }

    /**
     * Determine default outputAs
     *
     * @return string
     */
    private function getDefaultOutputAs()
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

    /**
     * Remove empty groups with 'hideIfEmpty' meta value
     *
     * @param array $log log or summary
     *
     * @return void
     */
    private function removeHideIfEmptyGroups(&$log)
    {
        $groupStack = array();
        $groupStackCount = 0;
        $removed = false;
        for ($i = 0, $count = \count($log); $i < $count; $i++) {
            $logEntry = $log[$i];
            $method = $logEntry['method'];
            if (\in_array($method, array('group', 'groupCollapsed'))) {
                $groupStack[] = array(
                    'i' => $i,
                    'meta' => $logEntry['meta'],
                    'hasEntries' => false,
                );
                $groupStackCount ++;
            } elseif ($method == 'groupEnd') {
                $group = \end($groupStack);
                if (!$group['hasEntries'] && !empty($group['meta']['hideIfEmpty'])) {
                    // make it go away
                    unset($log[$group['i']]);
                    unset($log[$i]);
                    $removed = true;
                }
                \array_pop($groupStack);
                $groupStackCount--;
            } elseif ($groupStack) {
                $groupStack[$groupStackCount - 1]['hasEntries'] = true;
            }
        }
        if ($removed) {
            $log = \array_values($log);
        }
    }

    /**
     * Set outputAs value
     * instantiate object if necessary & addPlugin if not already subscribed
     *
     * @param OutputInterface|string $outputAs OutputInterface instance, or (short) classname
     *
     * @return void
     */
    private function setOutputAs($outputAs)
    {
        if (\is_object($this->cfg['outputAs'])) {
            /*
                unsubscribe current OutputInterface
                there can only be one 'outputAs' at a time
                if multiple output routes are desired, use debug->addPlugin()
            */
            $this->debug->removePlugin($this->cfg['outputAs']);
            $this->cfg['outputAs'] = null;
        }
        $prop = null;
        $obj = null;
        if (\is_string($outputAs)) {
            $prop = $outputAs;
            $classname = __NAMESPACE__.'\\Output\\'.\ucfirst($outputAs);
            if (\property_exists($this, $prop)) {
                $obj = $this->{$prop};
            } elseif (\class_exists($classname)) {
                $obj = new $classname($this->debug);
            }
        } elseif ($outputAs instanceof OutputInterface) {
            $classname = \get_class($outputAs);
            $prefix = __NAMESPACE__.'\\Output\\';
            if (\strpos($classname, $prefix) == 0) {
                $prop = \substr($classname, \strlen($prefix));
                $prop = \lcfirst($prop);
            }
            $obj = $outputAs;
        }
        if ($obj) {
            $this->debug->addPlugin($obj);
            $this->cfg['outputAs'] = $obj;
            if ($prop) {
                $this->{$prop} = $obj;
            }
        }
    }

    /**
     * Uncollapse groups containing errors.
     *
     * @param array $log log or summary
     *
     * @return void
     */
    private function uncollapseErrors(&$log)
    {
        $groupStack = array();
        for ($i = 0, $count = \count($log); $i < $count; $i++) {
            $method = $log[$i]['method'];
            if (\in_array($method, array('group', 'groupCollapsed'))) {
                $groupStack[] = $i;
            } elseif ($method == 'groupEnd') {
                \array_pop($groupStack);
            } elseif (\in_array($method, array('error', 'warn'))) {
                foreach ($groupStack as $i2) {
                    $log[$i2]['method'] = 'group';
                }
            }
        }
    }
}
