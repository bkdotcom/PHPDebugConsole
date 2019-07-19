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

namespace bdk\Debug\Route;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\AssetProviderInterface;
use bdk\Debug\LogEntry;
use bdk\PubSub\Event;

/**
 * Output log as HTML
 *
 * @property HtmlObject $object lazy-loaded HtmlObject... only loaded if dumping an object
 * @property HtmlTable  $table  lazy-loaded HtmlTable... only loaded if outputing a table
 */
class Html extends Base
{

    protected $errorSummary;
    protected $argAttribs = array();
    protected $logEntryAttribs = array();
    protected $channels = array();
    protected $detectFiles = false;
    protected $argStringOpts = array();     // per-argument string options
    protected $cfg = array();
    private $assets = array(
        'css' => array(),
        'script' => array(),
    );

    /**
     * Constructor
     *
     * @param Debug $debug debug instance
     */
    public function __construct(Debug $debug)
    {
        $this->errorSummary = new HtmlErrorSummary($this, $debug->errorHandler);
        $this->cfg = array(
            'addBR' => false,
            'css' => '',                    // additional "override" css
            'drawer' => true,
            'filepathCss' => __DIR__.'/../css/Debug.css',
            'filepathScript' => __DIR__.'/../js/Debug.jquery.min.js',
            'jqueryUrl' => '//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js',
            'outputCss' => true,
            'outputScript' => true,
            'sidebar' => true,
        );
        parent::__construct($debug);
    }

    /**
     * Add/register css or javascript
     *
     * @param string $what  "css" or "script"
     * @param string $mixed css, javascript, or filepath
     *
     * @return void
     */
    public function addAsset($what, $mixed)
    {
        if ($what == 'css') {
            $this->assets['css'][] = $mixed;
        } elseif ($what == 'script') {
            $this->assets['script'][] = $mixed;
        }
    }

    /**
     * Get and register assets from passed provider
     *
     * @param AssetProviderInterface $assetProvider Asset provider
     *
     * @return void
     */
    public function addAssetProvider(AssetProviderInterface $assetProvider)
    {
        $assets = \array_merge(array(
            'css' => array(),
            'script' => array(),
        ), $assetProvider->getAssets());
        foreach ((array) $assets['css'] as $css) {
            $this->addAsset('css', $css);
        }
        foreach ((array) $assets['script'] as $script) {
            $this->addAsset('script', $script);
        }
    }

    /**
     * Dump value as html
     *
     * @param mixed        $val     value to dump
     * @param array        $opts    options for string values
     * @param string|false $tagName (span) tag to wrap value in (or false)
     *
     * @return string
     */
    public function dump($val, $opts = array(), $tagName = 'span')
    {
        $this->argAttribs = array(
            'class' => array(),
            'title' => null,
        );
        $optsDefault = array(
            'addQuotes' => true,
            'sanitize' => true,
            'visualWhiteSpace' => true,
        );
        if (\is_bool($opts)) {
            $keys = \array_keys($optsDefault);
            $opts = \array_fill_keys($keys, $opts);
        } else {
            $opts = \array_merge($optsDefault, $opts);
        }
        $absAttribs = array();
        if ($val instanceof Abstraction) {
            $absAttribs = $val['attribs'];
            foreach (\array_keys($opts) as $k) {
                if ($val[$k] !== null) {
                    $opts[$k] = $val[$k];
                }
            }
        }
        $this->argStringOpts = $opts;
        $val = parent::dump($val);
        if ($tagName && !\in_array($this->dumpType, array('recursion'))) {
            $argAttribs = $this->debug->utilities->arrayMergeDeep(
                array(
                    'class' => array(
                        't_'.$this->dumpType,
                        $this->dumpTypeMore,
                    ),
                ),
                $this->argAttribs
            );
            if ($absAttribs) {
                $absAttribs['class'] = isset($absAttribs['class'])
                    ? (array) $absAttribs['class']
                    : array();
                $argAttribs = $this->debug->utilities->arrayMergeDeep(
                    $argAttribs,
                    $absAttribs
                );
            }
            $val = $this->debug->utilities->buildTag($tagName, $argAttribs, $val);
        }
        $this->argAttribs = array();
        return $val;
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
        /*
            add "plugin" css  (ie prism.css)
        */
        $return .= $this->buildAssetOutput($this->assets['css']);
        if (!empty($this->cfg['css'])) {
            $return .= $this->cfg['css'];
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
        /*
            add "plugin" scripts  (ie prism.js)
        */
        $return .= $this->buildAssetOutput($this->assets['script']);
        return $return;
    }

    /**
     * Wrap classname in span.classname
     * if namespaced additionally wrap namespace in span.namespace
     * If callable, also wrap with .t_operator and .t_identifier
     *
     * @param string $str     classname or classname(::|->)name (method/property/const)
     * @param string $tagName ("span") html tag to use
     * @param array  $attribs (optional) additional html attributes
     *
     * @return string
     */
    public function markupIdentifier($str, $tagName = 'span', $attribs = array())
    {
        if (\preg_match('/^(.+)(::|->)(.+)$/', $str, $matches)) {
            $classname = $matches[1];
            $opIdentifier = '<span class="t_operator">'.\htmlspecialchars($matches[2]).'</span>'
                    . '<span class="t_identifier">'.$matches[3].'</span>';
        } else {
            $classname = $str;
            $opIdentifier = '';
        }
        $idx = \strrpos($classname, '\\');
        if ($idx) {
            $classname = '<span class="namespace">'.\substr($classname, 0, $idx + 1).'</span>'
                . \substr($classname, $idx + 1);
        }
        $attribs = \array_merge(array(
            'class' => 'classname',
        ), $attribs);
        return $this->debug->utilities->buildTag($tagName, $attribs, $classname)
            .$opIdentifier;
    }

    /**
     * Return the log as HTML
     *
     * @param Event $event event object
     *
     * @return string|void
     */
    public function onOutput(Event $event)
    {
        $this->data = $this->debug->getData();
        $this->channels = array();
        $lftDefault = \strtr(\ini_get('xdebug.file_link_format'), array('%f'=>'%file','%l'=>'%line'));
        $str = '<div'.$this->debug->utilities->buildAttribString(array(
            'class' => 'debug',
            'data-options' => array(
                'drawer' => $this->cfg['drawer'],
                'sidebar' => $this->cfg['sidebar'],
                'linkFilesTemplateDefault' => $lftDefault ?: null,
            ),
            // channel list gets built as log processed...  we'll str_replace this...
            'data-channels' => '{{channels}}',
            'data-channel-root' => $this->channelNameRoot,
        )).">\n";
        if ($this->cfg['outputCss']) {
            $str .= '<style type="text/css">'."\n"
                    .$this->getCss()."\n"
                .'</style>'."\n";
        }
        if ($this->cfg['outputScript']) {
            $str .= '<script>window.jQuery || document.write(\'<script src="'.$this->cfg['jqueryUrl'].'"><\/script>\')</script>'."\n";
            $str .= '<script type="text/javascript">'
                    .$this->getScript()."\n"
                .'</script>'."\n";
        }
        $str .= '<header class="debug-menu-bar">PHPDebugConsole</header>'."\n";
        $str .= '<div class="debug-body">'."\n";
        $str .= $this->processAlerts();
        /*
            If outputing script, initially hide the output..
            this will help page load performance (fewer redraws)... by magnitudes
        */
        $style = null;
        if ($this->cfg['outputScript']) {
            $str .= '<div class="loading">Loading <i class="fa fa-spinner fa-pulse fa-2x fa-fw" aria-hidden="true"></i></div>'."\n";
            $style = 'display:none;';
        }
        $str .= '<ul'.$this->debug->utilities->buildAttribString(array(
            'class' => 'debug-log-summary group-body',
            'style' => $style,
        )).">\n".$this->processSummary().'</ul>'."\n";
        $str .= '<ul'.$this->debug->utilities->buildAttribString(array(
            'class' => 'debug-log group-body',
            'style' => $style,
        )).">\n".$this->processLog().'</ul>'."\n";
        $str .= '</div>'."\n";  // close .debug-body
        $str .= '</div>'."\n";  // close .debug
        $str = \strtr($str, array(
            '{{channels}}' => \htmlspecialchars(\json_encode($this->buildChannelTree(), JSON_FORCE_OBJECT)),
        ));
        $this->data = array();
        $event['return'] .= $str;
    }

    /**
     * Return a log entry as HTML
     *
     * @param LogEntry $logEntry log entry instance
     *
     * @return string|void
     */
    public function processLogEntry(LogEntry $logEntry)
    {
        $str = '';
        $method = $logEntry['method'];
        $meta = \array_merge(array(
            'attribs' => array(),
            'detectFiles' => null,
            'icon' => null,
            'sanitize' => true,         // apply htmlspecialchars (to non-first arg)?
            'sanitizeFirst' => null,    // if null, use meta.sanitize
        ), $logEntry['meta']);
        if ($meta['sanitizeFirst'] === null) {
            $meta['sanitizeFirst'] = $meta['sanitize'];
        }
        $logEntry->setMeta($meta);
        $channelName = $logEntry->getChannel();
        // phpError channel is handled separately
        if (!isset($this->channels[$channelName]) && $channelName !== 'phpError') {
            $this->channels[$channelName] = $logEntry->getSubject();
        }
        $this->detectFiles = $meta['detectFiles'];
        $this->logEntryAttribs = \array_merge(array(
            'class' => '',
            'data-channel' => $channelName !== $this->channelNameRoot
                ? $channelName
                : null,
            'data-detect-files' => $meta['detectFiles'],
            'data-icon' => $meta['icon'],
        ), $meta['attribs']);
        $this->logEntryAttribs['class'] .= ' m_'.$method;
        if ($method == 'alert') {
            $str = $this->buildMethodAlert($logEntry);
        } elseif (\in_array($method, array('group', 'groupCollapsed', 'groupEnd'))) {
            $str = $this->buildMethodGroup($logEntry);
        } elseif (\in_array($method, array('profileEnd','table','trace'))) {
            $str = $this->buildMethodTabular($logEntry);
        } else {
            $str = $this->buildMethodDefault($logEntry);
        }
        $str = \strtr($str, array(
            ' data-channel="null"' => '',
            ' data-detect-files="null"' => '',
            ' data-icon="null"' => '',
        ));
        $str .= "\n";
        return $str;
    }

    /**
     * Set one or more config values
     *
     *    setCfg('key', 'value')
     *    setCfg(array('k1'=>'v1', 'k2'=>'v2'))
     *
     * @param string $mixed key=>value array or key
     * @param mixed  $val   new value
     *
     * @return mixed returns previous value(s)
     */
    public function setCfg($mixed, $val = null)
    {
        $ret = parent::setCfg($mixed, $val);
        foreach (array('filepathCss', 'filepathScript') as $k) {
            $this->cfg[$k] = \preg_replace('#^\./?#', __DIR__.'/../', $this->cfg[$k]);
        }
        return $ret;
    }

    /**
     * Convert all arguments to html and join them together.
     *
     * @param array $args arguments
     * @param array $meta meta values
     *
     * @return string html
     */
    protected function buildArgString($args, $meta = array())
    {
        $glue = ', ';
        $glueAfterFirst = true;
        if (\is_string($args[0])) {
            if (\preg_match('/[=:] ?$/', $args[0])) {
                // first arg ends with "=" or ":"
                $glueAfterFirst = false;
                $args[0] = \rtrim($args[0]).' ';
            } elseif (\count($args) == 2) {
                $glue = ' = ';
            }
        }
        foreach ($args as $i => $v) {
            $args[$i] = $this->dump($v, array(
                'sanitize' => $i === 0
                    ? $meta['sanitizeFirst']
                    : $meta['sanitize'],
                'addQuotes' => $i !== 0,
                'visualWhiteSpace' => $i !== 0,
            ));
        }
        if (!$glueAfterFirst) {
            return $args[0].\implode($glue, \array_slice($args, 1));
        } else {
            return \implode($glue, $args);
        }
    }

    /**
     * Combine css or script assets into a single string
     *
     * @param array $assets array of assets (filepaths / strings)
     *
     * @return string
     */
    private function buildAssetOutput(array $assets)
    {
        $return = '';
        $hashes = array();
        foreach ($assets as $asset) {
            if (!\preg_match('#[\r\n]#', $asset)) {
                // single line... potential filepath
                $asset = \preg_replace('#^\./?#', __DIR__.'/../', $asset);
                if (\file_exists($asset)) {
                    $asset = \file_get_contents($asset);
                }
            }
            $hash = \md5($asset);
            if (!\in_array($hash, $hashes)) {
                $return .= $asset."\n";
                $hashes[] = $hash;
            }
        }
        return $return;
    }

    /**
     * Build a tree of all channels that have been output
     *
     * @return array
     */
    protected function buildChannelTree()
    {
        if (\array_keys($this->channels) == array($this->channelNameRoot)) {
            return array();
        }
        \ksort($this->channels);
        // move root to the top
        if (isset($this->channels[$this->channelNameRoot])) {
            // move root to the top
            $this->channels = array($this->channelNameRoot => $this->channels[$this->channelNameRoot]) + $this->channels;
        }
        $tree = array();
        foreach ($this->channels as $channelName => $channel) {
            $ref = &$tree;
            $path = \explode('.', $channelName);
            foreach ($path as $k) {
                if (!isset($ref[$k])) {
                    $ref[$k] = array(
                        'options' => array(
                            'icon' => $channel->getCfg('channelIcon'),
                            'show' => $channel->getCfg('channelShow'),
                        ),
                        'channels' => array(),
                    );
                }
                $ref = &$ref[$k]['channels'];
            }
        }
        return $tree;
    }

    /**
     * Handle alert method
     *
     * @param LogEntry $logEntry logEntry instance
     *
     * @return array array($method, $args)
     */
    protected function buildMethodAlert(LogEntry $logEntry)
    {
        $meta = $logEntry['meta'];
        $attribs = \array_merge($this->logEntryAttribs, array(
            'class' => 'alert-'.$meta['level'].' '.$this->logEntryAttribs['class'],
            'role' => 'alert',
        ));
        $html = $this->dump(
            $logEntry['args'][0],
            array(
                'sanitize' => $meta['sanitizeFirst'],
                'visualWhiteSpace' => false,
            ),
            false // don't wrap in span
        );
        if ($meta['dismissible']) {
            $attribs['class'] .= ' alert-dismissible';
            $html = '<button type="button" class="close" data-dismiss="alert" aria-label="Close">'
                .'<span aria-hidden="true">&times;</span>'
                .'</button>'
                .$html;
        }
        return $this->debug->utilities->buildTag('div', $attribs, $html);
    }

    /**
     * Handle html output of default/standard methods
     *
     * @param LogEntry $logEntry logEntry instance
     *
     * @return string
     */
    protected function buildMethodDefault(LogEntry $logEntry)
    {
        $method = $logEntry['method'];
        $args = $logEntry['args'];
        $meta = \array_merge(array(
            'errorCat' => null,  //  should only be applicable for error & warn methods
        ), $logEntry['meta']);
        $attribs = $this->logEntryAttribs;
        if (isset($meta['file']) && $logEntry->getChannel() !== 'phpError') {
            // PHP errors will have file & line as one of the arguments
            //    so no need to store file & line as data args
            $attribs = \array_merge(array(
                'data-file' => $meta['file'],
                'data-line' => $meta['line'],
            ), $attribs);
        }
        if (\in_array($method, array('assert','clear','error','info','log','warn'))) {
            if ($meta['errorCat']) {
                $attribs['class'] .= ' error-'.$meta['errorCat'];
            }
            if (\count($args) > 1 && \is_string($args[0])) {
                $hasSubs = false;
                $args = $this->processSubstitutions($args, $hasSubs);
                if ($hasSubs) {
                    $meta['sanitizeFirst'] = false;
                    $args = array( \implode('', $args) );
                }
            }
        }
        return $this->debug->utilities->buildTag(
            'li',
            $attribs,
            $this->buildArgString($args, $meta)
        );
    }

    /**
     * Handle html output of group, groupCollapsed, & groupEnd
     *
     * @param LogEntry $logEntry logEntry instance
     *
     * @return string
     */
    protected function buildMethodGroup(LogEntry $logEntry)
    {
        $method = $logEntry['method'];
        $args = $logEntry['args'];
        $meta = \array_merge(array(
            'argsAsParams' => true,
            'boldLabel' => true,
            'isFuncName' => false,
            'level' => null,
        ), $logEntry['meta']);
        $str = '';
        if (\in_array($method, array('group','groupCollapsed'))) {
            $label = \array_shift($args);
            if ($meta['isFuncName']) {
                $label = $this->markupIdentifier($label);
            }
            $labelClasses = \implode(' ', \array_keys(\array_filter(array(
                'group-label' => true,
                'group-label-bold' => $meta['boldLabel'],
            ))));
            $levelClass = $meta['level']
                ? 'level-'.$meta['level']
                : null;
            foreach ($args as $k => $v) {
                $args[$k] = $this->dump($v);
            }
            $argStr = \implode(', ', $args);
            if (!$argStr) {
                $headerStr = '<span class="'.$labelClasses.'">'.$label.'</span>';
            } elseif ($meta['argsAsParams']) {
                $headerStr = '<span class="'.$labelClasses.'">'.$label.'(</span>'
                    .$argStr
                    .'<span class="'.$labelClasses.'">)</span>';
            } else {
                $headerStr = '<span class="'.$labelClasses.'">'.$label.':</span> '
                    .$argStr;
            }
            $this->logEntryAttribs['class'] = \str_replace('m_'.$method, 'm_group', $this->logEntryAttribs['class']);
            $str = '<li'.$this->debug->utilities->buildAttribString($this->logEntryAttribs).'>'."\n";
            /*
                Header / label / toggle
            */
            $str .= $this->debug->utilities->buildTag(
                'div',
                array(
                    'class' => array(
                        'group-header',
                        $method == 'groupCollapsed'
                            ? 'collapsed'
                            : 'expanded',
                        $levelClass,
                    ),
                ),
                $headerStr
            )."\n";
            /*
                Group open
            */
            $str .= '<ul'.$this->debug->utilities->buildAttribString(array(
                'class' => array(
                    'group-body',
                    $levelClass,
                ),
            )).'>';
        } elseif ($method == 'groupEnd') {
            $str = '</ul>'."\n".'</li>';
        }
        return $str;
    }

    /**
     * Handle profile(End), table, & trace methods
     *
     * @param LogEntry $logEntry logEntry instance
     *
     * @return string
     */
    protected function buildMethodTabular(LogEntry $logEntry)
    {
        $args = $logEntry['args'];
        $meta = \array_merge(array(
            'caption' => null,
            'columns' => array(),
            'sortable' => false,
            'totalCols' => array(),
        ), $logEntry['meta']);
        $asTable = false;
        if (\is_array($args[0])) {
            $asTable = (bool) $args[0];
        } elseif ($this->debug->abstracter->isAbstraction($args[0], 'object')) {
            $asTable = true;
        }
        if (!$asTable && $meta['caption']) {
            \array_unshift($args, $meta['caption']);
        }
        return $this->debug->utilities->buildTag(
            'li',
            $this->logEntryAttribs,
            $asTable
                ? "\n"
                    .$this->table->build(
                        $args[0],
                        array(
                            'attribs' => array(
                                'class' => array(
                                    'table-bordered',
                                    $meta['sortable'] ? 'sortable' : null,
                                ),
                            ),
                            'caption' => $meta['caption'],
                            'columns' => $meta['columns'],
                            'totalCols' => $meta['totalCols'],
                        )
                    )."\n"
                : $this->buildArgString($args, $meta)
        );
    }

    /**
     * Dump array as html
     *
     * @param array $array array
     *
     * @return string html
     */
    protected function dumpArray($array)
    {
        if (empty($array)) {
            $html = '<span class="t_keyword">array</span>'
                .'<span class="t_punct">()</span>';
        } else {
            $showKeys = $this->debug->getCfg('arrayShowListKeys') || !$this->debug->utilities->isList($array);
            $html = '<span class="t_keyword">array</span>'
                .'<span class="t_punct">(</span>'."\n";
            if ($showKeys) {
                $html .= '<span class="array-inner">'."\n";
                foreach ($array as $key => $val) {
                    $html .= "\t".'<span class="key-value">'
                            .'<span class="t_key'.(\is_int($key) ? ' t_int' : '').'">'
                                .$this->dump($key, true, false) // don't wrap it
                            .'</span>'
                            .'<span class="t_operator">=&gt;</span>'
                            .$this->dump($val)
                        .'</span>'."\n";
                }
                $html .= '</span>';
            } else {
                // display as list
                $html .= '<ul class="array-inner list-unstyled">'."\n";
                foreach ($array as $val) {
                    $html .= $this->dump($val, true, 'li');
                }
                $html .= '</ul>';
            }
            $html .= '<span class="t_punct">)</span>';
        }
        return $html;
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
     * Dump "Callable" as html
     *
     * @param Abstraction $abs array/callable abstraction
     *
     * @return string
     */
    protected function dumpCallable(Abstraction $abs)
    {
        return '<span class="t_type">callable</span> '
            .$this->markupIdentifier($abs['values'][0].'::'.$abs['values'][1]);
    }

    /**
     * Dump "const" abstration as html
     *
     * @param Abstraction $abs const abstraction
     *
     * @return string
     */
    protected function dumpConst(Abstraction $abs)
    {
        $this->argAttribs['title'] = $abs['value']
            ? 'value: '.$this->debug->routeText->dump($abs['value'])
            : null;
        return $this->markupIdentifier($abs['name']);
    }

    /**
     * Dump float value
     *
     * @param integer $val float value
     *
     * @return float
     */
    protected function dumpFloat($val)
    {
        $date = $this->checkTimestamp($val);
        if ($date) {
            $this->argAttribs['class'][] = 'timestamp';
            $this->argAttribs['title'] = $date;
        }
        return $val;
    }

    /**
     * Dump integer value
     *
     * @param integer $val integer value
     *
     * @return integer
     */
    protected function dumpInt($val)
    {
        return $this->dumpFloat($val);
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
     * Dump object as html
     *
     * @param Abstraction $abs object abstraction
     *
     * @return string
     */
    protected function dumpObject(Abstraction $abs)
    {
        /*
            Were we debugged from inside or outside of the object?
        */
        $dump = $this->object->dump($abs);
        $this->argAttribs['data-accessible'] = $abs['scopeClass'] == $abs['className']
            ? 'private'
            : 'public';
        return $dump;
    }

    /**
     * Dump recursion (array recursion)
     *
     * @return string
     */
    protected function dumpRecursion()
    {
        return '<span class="t_keyword">array</span> <span class="t_recursion">*RECURSION*</span>';
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
            if ($date) {
                $this->argAttribs['class'][] = 'timestamp';
                $this->argAttribs['title'] = $date;
            }
        } else {
            if ($this->detectFiles && !\preg_match('/[\r\n]/', $val) && \is_file($val)) {
                $this->argAttribs['class'][] = 'file';
            }
            if ($this->argStringOpts['sanitize']) {
                $val = $this->debug->utf8->dump($val, true, true);
            } else {
                $val = $this->debug->utf8->dump($val, true, false);
            }
            if ($this->argStringOpts['visualWhiteSpace']) {
                $val = $this->visualWhiteSpace($val);
            }
        }
        if (!$this->argStringOpts['addQuotes']) {
            $this->argAttribs['class'][] = 'no-quotes';
        }
        return $val;
    }

    /**
     * Dump undefined
     *
     * @return string
     */
    protected function dumpUndefined()
    {
        return '';
    }

    /**
     * Getter for this->object
     *
     * @return HtmlObject
     */
    protected function getObject()
    {
        $this->object = new HtmlObject($this);
        return $this->object;
    }

    /**
     * Getter for this->table
     *
     * @return HtmlObject
     */
    protected function getTable()
    {
        $this->table = new HtmlTable($this->debug);
        return $this->table;
    }

    /**
     * process alerts
     *
     * @return string
     */
    protected function processAlerts()
    {
        $errorSummary = $this->errorSummary->build($this->debug->internal->errorStats());
        if ($errorSummary) {
            \array_unshift($this->data['alerts'], new LogEntry(
                $this->debug,
                'alert',
                array(
                    $errorSummary
                ),
                array(
                    'attribs' => array(
                        'class' => 'error-summary',
                    ),
                    'dismissible' => false,
                    'level' => 'danger',
                    'sanitize' => false,
                )
            ));
        }
        return parent::processAlerts();
    }

    /**
     * Coerce value to string
     *
     * @param mixed $val value
     *
     * @return string
     */
    protected function substitutionAsString($val)
    {
        // function array dereferencing = php 5.4
        $type = $this->debug->abstracter->getType($val)[0];
        if ($type == 'string') {
            $val = $this->dump($val, true, false);
        } elseif ($type == 'array') {
            $count = \count($val);
            $val = '<span class="t_keyword">array</span>'
                .'<span class="t_punct">(</span>'.$count.'<span class="t_punct">)</span>';
        } elseif ($type == 'object') {
            $val = $this->markupIdentifier($val['className']);
        } else {
            $val = $this->dump($val);
        }
        return $val;
    }

    /**
     * Add whitespace markup
     *
     * @param string $str string which to add whitespace html markup
     *
     * @return string
     */
    protected function visualWhiteSpace($str)
    {
        // display \r, \n, & \t
        $str = \preg_replace_callback('/(\r\n|\r|\n)/', array($this, 'visualWhiteSpaceCallback'), $str);
        $str = \preg_replace('#(<br />)?\n$#', '', $str);
        $str = \str_replace("\t", '<span class="ws_t">'."\t".'</span>', $str);
        return $str;
    }

    /**
     * Adds whitespace markup
     *
     * @param array $matches passed from preg_replace_callback
     *
     * @return string
     */
    protected function visualWhiteSpaceCallback($matches)
    {
        $strBr = $this->cfg['addBR'] ? '<br />' : '';
        $search = array("\r","\n");
        $replace = array('<span class="ws_r"></span>','<span class="ws_n"></span>'.$strBr."\n");
        return \str_replace($search, $replace, $matches[1]);
    }
}
