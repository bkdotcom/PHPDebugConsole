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

namespace bdk\Debug\Collector;

/*
    Try to avoid "Declaration should be compatible" notices
    Some 1.38.0 methods use namespaced hints..
    although should be considered equivalent PSR-9 alias
*/
$aliases = array(
    'Twig_ExtensionInterface' => 'Twig\\Extension\\ExtensionInterface',
    'Twig_LoaderInterface' => 'Twig\\Loader\\LoaderInterface',
    'Twig_NodeVisitorInterface' => 'Twig\\NodeVisitor\\NodeVisitorInterface',
    'Twig_TokenParserInterface' => 'Twig\\TokenParser\\TokenParserInterface',
    'Twig_TokenStream' => 'Twig\\TokenStream',
);
foreach ($aliases as $old => $new) {
    if (!\interface_exists($new) && !\class_exists($new)) {
        \class_alias($old, $new);
    }
}

use bdk\Debug;
use bdk\Debug\LogEntry;
use bdk\Debug\Collector\Twig\Template;
use bdk\PubSub\Event;
use Twig_CompilerInterface;
use Twig_Environment;
use Twig_LexerInterface;
use Twig_NodeInterface;
use Twig_ParserInterface;
use Twig\Extension\ExtensionInterface;
use Twig\Loader\LoaderInterface;
use Twig\NodeVisitor\NodeVisitorInterface;
use Twig\TokenParser\TokenParserInterface;
use Twig\TokenStream;

/**
 * A Twig proxy
 */
class Twig extends Twig_Environment
{

    protected $debug;
    protected $renderedTemplates = array();
    protected $twig;
    protected $icon = 'fa fa-file-text-o';

    /**
     * Constructor
     *
     * @param Twig_Environment $twig  Twig Env instance
     * @param Debug            $debug (optional) Specify PHPDebugConsole instance
     *                                  if not passed, will create PDO channnel on singleton instance
     *                                  if root channel is specified, will create a PDO channel
     */
    public function __construct(Twig_Environment $twig, Debug $debug = null)
    {
        if (!$debug) {
            $debug = Debug::_getChannel('Twig', array('channelIcon' => $this->icon));
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel('Twig', array('channelIcon' => $this->icon));
        }
        $this->twig = $twig;
        $this->debug = $debug;
        $this->debug->eventManager->subscribe('debug.output', array($this, 'onDebugOutput'), 1);
    }

    /**
     * Magic method... inaccessible method called.
     *
     * @param string $name method name
     * @param array  $args method arguments
     *
     * @return mixed
     */
    public function __call($name, $args)
    {
        return \call_user_func_array(array($this->twig, $name), $args);
    }

    /**
     * Store Render Stats
     *
     * @param array $info render stats (name/duration)
     *
     * @return void
     */
    public function addRenderedTemplate(array $info)
    {
        $this->renderedTemplates[] = $info;
        $this->debug->time('Rendered Template: ' . $info['name'], $info['duration'], $this->debug->meta(array(
            'icon' => $this->icon,
        )));
    }

    /**
     * debug.output subscriber
     *
     * @param Event $event event instance
     *
     * @return void
     */
    public function onDebugOutput(Event $event)
    {
        $debug = $event->getSubject();
        $debug->groupSummary(0);
        $debug->groupCollapsed(
            'Twig info',
            $debug->meta(array(
                'argsAsParams' => false,
                'icon' => $this->icon,
                'level' => 'info',
            ))
        );
        $debug->log('rendered templates: ', \count($this->renderedTemplates));
        $debug->time('total time', $this->getTimeSpent());
        $debug->groupEnd();
        $debug->groupEnd();
    }

    /**
     * Returns the accumulated rendering time of templates
     *
     * @return float
     */
    protected function getTimeSpent()
    {
        $totalTime = 0;
        foreach ($this->renderedTemplates as $info) {
            $totalTime += $info['duration'];
        }
        return $totalTime;
    }

    /*
        Twig\Environment methods
    */

    /**
     * {@inheritDoc}
     */
    public function addExtension(ExtensionInterface $extension)
    {
        $this->twig->addExtension($extension);
    }

    /**
     * {@inheritDoc}
     */
    public function addFilter($name, $filter = null)
    {
        $this->twig->addFilter($name, $filter);
    }

    /**
     * {@inheritDoc}
     */
    public function addFunction($name, $function = null)
    {
        $this->twig->addFunction($name, $function);
    }

    /**
     * {@inheritDoc}
     */
    public function addGlobal($name, $value)
    {
        $this->twig->addGlobal($name, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function addNodeVisitor(NodeVisitorInterface $visitor)
    {
        $this->twig->addNodeVisitor($visitor);
    }

    /**
     * {@inheritDoc}
     */
    public function addTest($name, $test = null)
    {
        $this->twig->addTest($name, $test);
    }

    /**
     * {@inheritDoc}
     */
    public function addTokenParser(TokenParserInterface $parser)
    {
        $this->twig->addTokenParser($parser);
    }

    /**
     * {@inheritDoc}
     */
    public function clearCacheFiles()
    {
        $this->twig->clearCacheFiles();
    }

    /**
     * {@inheritDoc}
     */
    public function clearTemplateCache()
    {
        $this->twig->clearTemplateCache();
    }

    /**
     * {@inheritDoc}
     */
    public function compile(Twig_NodeInterface $node)
    {
        return $this->twig->compile($node);
    }

    /**
     * {@inheritDoc}
     */
    public function compileSource($source, $name = null)
    {
        return $this->twig->compileSource($source, $name);
    }

    /**
     * {@inheritDoc}
     */
    public function computeAlternatives($name, $items)
    {
        return $this->twig->computeAlternatives($name, $items);
    }

    /**
     * {@inheritDoc}
     */
    public function disableAutoReload()
    {
        $this->twig->disableAutoReload();
    }

    /**
     * {@inheritDoc}
     */
    public function disableDebug()
    {
        $this->twig->disableDebug();
    }

    /**
     * {@inheritDoc}
     */
    public function disableStrictVariables()
    {
        $this->twig->disableStrictVariables();
    }

    /**
     * {@inheritDoc}
     */
    public function display($name, array $context = array())
    {
        $this->loadTemplate($name)->display($context);
    }

    /**
     * {@inheritDoc}
     */
    public function enableAutoReload()
    {
        $this->twig->enableAutoReload();
    }

    /**
     * {@inheritDoc}
     */
    public function enableDebug()
    {
        $this->twig->enableDebug();
    }

    /**
     * {@inheritDoc}
     */
    public function enableStrictVariables()
    {
        $this->twig->enableStrictVariables();
    }

    /**
     * {@inheritDoc}
     */
    public function getBaseTemplateClass()
    {
        return $this->twig->getBaseTemplateClass();
    }

    /**
     * {@inheritDoc}
     */
    public function getBinaryOperators()
    {
        return $this->twig->getBinaryOperators();
    }

    /**
     * {@inheritDoc}
     */
    public function getCache($original = true)
    {
        return $this->twig->getCache($original);
    }

    /**
     * {@inheritDoc}
     */
    public function getCacheFilename($name)
    {
        return $this->twig->getCacheFilename($name);
    }

    /**
     * {@inheritDoc}
     */
    public function getCharset()
    {
        return $this->twig->getCharset();
    }

    /**
     * {@inheritDoc}
     */
    public function getCompiler()
    {
        return $this->twig->getCompiler();
    }

    /**
     * {@inheritDoc}
     */
    public function getExtension($name)
    {
        return $this->twig->getExtension($name);
    }

    /**
     * {@inheritDoc}
     */
    public function getExtensions()
    {
        return $this->twig->getExtensions();
    }

    /**
     * {@inheritDoc}
     */
    public function getFilter($name)
    {
        return $this->twig->getFilter($name);
    }

    /**
     * {@inheritDoc}
     */
    public function getFilters()
    {
        return $this->twig->getFilters();
    }

    /**
     * {@inheritDoc}
     */
    public function getFunction($name)
    {
        return $this->twig->getFunction($name);
    }

    /**
     * {@inheritDoc}
     */
    public function getFunctions()
    {
        return $this->twig->getFunctions();
    }

    /**
     * {@inheritDoc}
     */
    public function getGlobals()
    {
        return $this->twig->getGlobals();
    }

    /**
     * {@inheritDoc}
     */
    public function getLexer()
    {
        return $this->twig->getLexer();
    }

    /**
     * {@inheritDoc}
     */
    public function getLoader()
    {
        return $this->twig->getLoader();
    }

    /**
     * {@inheritDoc}
     */
    public function getNodeVisitors()
    {
        return $this->twig->getNodeVisitors();
    }

    /**
     * {@inheritDoc}
     */
    public function getParser()
    {
        return $this->twig->getParser();
    }

    /**
     * {@inheritDoc}
     */
    public function getTags()
    {
        return $this->twig->getTags();
    }

    /**
     * {@inheritDoc}
     */
    public function getTemplateClass($name, $index = null)
    {
        return $this->twig->getTemplateClass($name, $index);
    }

    /**
     * {@inheritDoc}
     */
    public function getTemplateClassPrefix()
    {
        return $this->twig->getTemplateClassPrefix();
    }

    /**
     * {@inheritDoc}
     */
    public function getTest($name)
    {
        return $this->twig->getTest($name);
    }

    /**
     * {@inheritDoc}
     */
    public function getTests()
    {
        return $this->twig->getTests();
    }

    /**
     * {@inheritDoc}
     */
    public function getTokenParsers()
    {
        return $this->twig->getTokenParsers();
    }

    /**
     * {@inheritDoc}
     */
    public function getUnaryOperators()
    {
        return $this->twig->getUnaryOperators();
    }

    /**
     * {@inheritDoc}
     */
    public function hasExtension($name)
    {
        return $this->twig->hasExtension($name);
    }

    /**
     * {@inheritDoc}
     */
    public function initRuntime()
    {
        $this->twig->initRuntime();
    }

    /**
     * {@inheritDoc}
     */
    public function isAutoReload()
    {
        return $this->twig->isAutoReload();
    }

    /**
     * {@inheritDoc}
     */
    public function isDebug()
    {
        return $this->twig->isDebug();
    }

    /**
     * {@inheritDoc}
     */
    public function isStrictVariables()
    {
        return $this->twig->isStrictVariables();
    }

    /**
     * {@inheritDoc}
     */
    public function isTemplateFresh($name, $time)
    {
        return $this->twig->isTemplateFresh($name, $time);
    }

    /**
     * {@inheritDoc}
     */
    public function loadTemplate($name, $index = null)
    {
        $template = $this->twig->loadTemplate($name, $index);
        if ($template instanceof Template) {
            return $template;
        }
        $cls = \get_class($template);
        return $this->twig->loadedTemplates[$cls] = new Template($this, new $cls($this));
    }

    /**
     * {@inheritDoc} foo  Twig\TokenStream
     */
    public function parse(TokenStream $tokens)
    {
        return $this->twig->parse($tokens);
    }

    /**
     * {@inheritDoc}
     */
    public function mergeGlobals(array $context)
    {
        return $this->twig->mergeGlobals($context);
    }

    /**
     * {@inheritDoc}
     */
    public function registerUndefinedFunctionCallback($callable)
    {
        $this->twig->registerUndefinedFunctionCallback($callable);
    }

    /**
     * {@inheritDoc}
     */
    public function removeExtension($name)
    {
        $this->twig->removeExtension($name);
    }

    /**
     * {@inheritDoc}
     */
    public function render($name, array $context = array())
    {
        return $this->loadTemplate($name)->render($context);
    }

    /**
     * {@inheritDoc}
     */
    public function resolveTemplate($names)
    {
        return $this->twig->resolveTemplate($names);
    }

    /**
     * {@inheritDoc}
     */
    public function registerUndefinedFilterCallback($callable)
    {
        $this->twig->registerUndefinedFilterCallback($callable);
    }

    /**
     * {@inheritDoc}
     */
    public function setBaseTemplateClass($class)
    {
        $this->twig->setBaseTemplateClass($class);
    }

    /**
     * {@inheritDoc}
     */
    public function setCache($cache)
    {
        $this->twig->setCache($cache);
    }

    /**
     * {@inheritDoc}
     */
    public function setCharset($charset)
    {
        $this->twig->setCharset($charset);
    }

    /**
     * {@inheritDoc}
     */
    public function setCompiler(Twig_CompilerInterface $compiler)
    {
        $this->twig->setCompiler($compiler);
    }

    /**
     * {@inheritDoc}
     */
    public function setExtensions(array $extensions)
    {
        $this->twig->setExtensions($extensions);
    }

    /**
     * {@inheritDoc}
     */
    public function setLexer(Twig_LexerInterface $lexer)
    {
        $this->twig->setLexer($lexer);
    }

    /**
     * {@inheritDoc} foo
     */
    public function setLoader(LoaderInterface $loader)
    {
        $this->twig->setLoader($loader);
    }

    /**
     * {@inheritDoc}
     */
    public function setParser(Twig_ParserInterface $parser)
    {
        $this->twig->setParser($parser);
    }

    /**
     * {@inheritDoc}
     */
    public function tokenize($source, $name = null)
    {
        return $this->twig->tokenize($source, $name);
    }
}
