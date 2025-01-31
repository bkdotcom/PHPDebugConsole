<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.0
 */

namespace bdk\Debug\Dump\Html;

use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\Object\Abstraction as ObjectAbstraction;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\Dump\Base\Value as BaseValue;
use bdk\Debug\Dump\Html as Dumper;
use bdk\Debug\Dump\Html\HtmlArray;
use bdk\Debug\Dump\Html\HtmlObject;
use bdk\Debug\Dump\Html\HtmlString;

/**
 * Dump val as HTML
 *
 * @property HtmlObject $object lazy-loaded HtmlObject... only loaded if dumping an object
 */
class Value extends BaseValue
{
    /** @var HtmlArray array dumper */
    public $array;

    /** @var HtmlString string dumper */
    public $string;

    /** @var \bdk\Debug\Utility\Html */
    protected $html;

    /** @var HtmlObject */
    protected $lazyObject;

    /**
     * Constructor
     *
     * @param Dumper $dumper "parent" dump class
     */
    public function __construct(Dumper $dumper)
    {
        parent::__construct($dumper); // sets debug and dumper
        $this->html = $this->debug->html;
        $this->array = new HtmlArray($this);
        $this->string = new HtmlString($this);
        $this->optionStackPush(array(
            'charHighlight' => true,
            'sanitize' => true,
            'sanitizeFirst' => true,
            'visualWhiteSpace' => true,
        ));
    }

    /**
     * Is value a timestamp?
     * Add classname & title if so
     *
     * @param mixed            $val value to check
     * @param Abstraction|null $abs (optional) full abstraction
     *
     * @return string|false
     */
    public function checkTimestamp($val, $abs = null)
    {
        $this->debug->utility->assertType($abs, 'bdk\Debug\Abstraction\Abstraction');

        $date = parent::checkTimestamp($val, $abs);
        if ($date) {
            $this->optionSet('postDump', function ($dumped, $opts) use ($val, $date) {
                $attribsContainer = array(
                    'class' => ['timestamp', 'value-container'],
                    'title' => $date,
                );
                if ($opts['tagName'] === 'td') {
                    return $this->html->buildTag(
                        'td',
                        $attribsContainer,
                        $this->html->buildTag('span', $opts['attribs'], $val)
                    );
                }
                return $this->html->buildTag('span', $attribsContainer, $dumped);
            });
        }
        return $date;
    }

    /**
     * Dump value as html
     *
     * @param mixed $val  value to dump
     * @param array $opts options for string values
     *                      addQuotes, sanitize, visualWhitespace, etc
     *
     * @return string
     */
    public function dump($val, $opts = array())
    {
        $opts = $this->getPerValueOptions($val, $opts);
        $this->optionStackPush($opts);
        $val = $this->doDump($val);
        if ($this->optionsCurrent['type']) {
            $this->optionsCurrent['attribs']['class'][] = 't_' . $this->optionsCurrent['type'];
        }
        if (\in_array($this->optionsCurrent['typeMore'], [null, Type::TYPE_RAW], true) === false) {
            $this->optionsCurrent['attribs']['data-type-more'] = \trim($this->optionsCurrent['typeMore']);
        }
        $tagName = $this->optionsCurrent['tagName'];
        if ($tagName) {
            $val = $this->html->buildTag($tagName, $this->optionsCurrent['attribs'], $val);
        }
        if ($this->optionsCurrent['postDump']) {
            $val = \call_user_func($this->optionsCurrent['postDump'], $val, $this->optionsCurrent);
        }
        $this->optionStackPop();
        return $val;
    }

    /**
     * Wrap classname in span.classname
     *
     * if namespaced additionally wrap namespace in span.namespace
     *
     * @param string|array $val     classname or classname(::|->)name (method/property/const)
     * @param string       $what    ("className"), "const", or "function" - specify what we're marking if ambiguous
     * @param string       $tagName ("span") html tag to use
     * @param array        $attribs (optional) additional html attributes for classname span (such as title)
     * @param bool         $wbr     (false) whether to add a <wbr /> after the classname
     *
     * @return string html snippet
     */
    public function markupIdentifier($val, $what = 'className', $tagName = 'span', $attribs = array(), $wbr = false)
    {
        $parts = \array_map([$this->string, 'dump'], $this->parseIdentifier($val, $what));
        $class = 'classname';
        $classOrNamespace = $this->wrapNamespace($parts['classname']);
        if ($parts['namespace']) {
            $class = 'namespace';
            $classOrNamespace = $parts['namespace'];
        }
        $classOrNamespace = $this->html->buildTag(
            $tagName,
            $this->debug->arrayUtil->mergeDeep(array(
                'class' => [$class],
            ), (array) $attribs),
            $classOrNamespace
        ) . '<wbr />';
        $classOrNamespace = \preg_replace('#<' . $tagName . '[^>]*></' . $tagName . '><wbr />#', '', $classOrNamespace);
        $parts2 = \array_filter(\array_intersect_key($parts, \array_flip(['operator', 'name'])));
        foreach ($parts2 as $key => $value) {
            $parts[$key] = '<span class="t_' . $key . '">' . $value . '</span>';
        }
        $html = \implode($parts['operator'], \array_filter([$classOrNamespace, $parts['name']]));
        return $wbr === false
            ? \str_replace('<wbr />', '', $html)
            : $html;
    }

    /**
     * Set one or more "options" for value being dumped
     *
     * @param array|string $what name of value to set (or key/value array)
     * @param mixed        $val  value
     *
     * @return void
     */
    public function optionSet($what, $val = null)
    {
        if ($what === 'attribs' && empty($val['class'])) {
            // make sure class is set
            $val['class'] = [];
        }
        parent::optionSet($what, $val);
    }

    /**
     * {@inheritDoc}
     */
    protected function dumpArray(array $array, $abs = null)
    {
        return $this->array->dump($array, $abs);
    }

    /**
     * Dump boolean
     *
     * @param bool $val boolean value
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
     * @param Abstraction $abs callable abstraction
     *
     * @return string
     */
    protected function dumpCallable(Abstraction $abs)
    {
        $this->optionSet('type', null); // don't output t_callable class
        if ($this->optionGet('tagName') !== 'td') {
            $this->optionSet('tagName', null);
        }
        return '<span class="t_type">callable</span> '
            . '<span class="t_identifier" data-type-more="callable">'
            . $this->markupIdentifier($abs['value'], 'callable')
            . '</span>';
    }

    /**
     * {@inheritDoc}
     */
    protected function dumpFloat($val, $abs = null)
    {
        $this->debug->utility->assertType($abs, 'bdk\Debug\Abstraction\Abstraction');

        if ($val === Type::TYPE_FLOAT_INF) {
            return 'INF';
        }
        if ($val === Type::TYPE_FLOAT_NAN) {
            return 'NaN';
        }
        $this->checkTimestamp($val, $abs);
        return $val;
    }

    /**
     * Dump identifier (constant, classname, method, property)
     *
     * @param Abstraction $abs const abstraction
     *
     * @return string
     */
    protected function dumpIdentifier(Abstraction $abs)
    {
        if (isset($abs['backedValue']) && \in_array($this->optionGet('attribs.title'), [null, ''], true)) {
            $valueAsString = $this->debug->getDump('text')->valDumper->dump($abs['backedValue']);
            $this->optionSet('attribs.title', 'value: ' . $valueAsString);
        }
        return $this->markupIdentifier($abs['value'], $abs['typeMore']);
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
     * @param ObjectAbstraction $abs Object Abstraction instance
     *
     * @return string
     */
    protected function dumpObject(ObjectAbstraction $abs)
    {
        return $this->object->dump($abs);
    }

    /**
     * Dump recursion (array recursion)
     *
     * @return string
     */
    protected function dumpRecursion()
    {
        $this->optionSet('type', Type::TYPE_ARRAY);
        return '<span class="t_keyword">array</span> <span class="t_recursion">*RECURSION*</span>';
    }

    /**
     * Dump string
     *
     * @param string           $val string value
     * @param Abstraction|null $abs (optional) full abstraction
     *
     * @return string
     */
    protected function dumpString($val, $abs = null)
    {
        $this->debug->utility->assertType($abs, 'bdk\Debug\Abstraction\Abstraction');

        return $this->string->dump($val, $abs);
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
     * Dump Type::TYPE_UNKNOWN
     *
     * @param Abstraction $abs resource abstraction
     *
     * @return string
     */
    protected function dumpUnknown(Abstraction $abs)
    {
        return 'unknown type';
    }

    /**
     * Getter for this->object
     *
     * @return HtmlObject
     */
    protected function getObject()
    {
        if (!$this->lazyObject) {
            $this->lazyObject = new HtmlObject($this, $this->dumper->helper, $this->html);
        }
        return $this->lazyObject;
    }

    /**
     * Get dump options
     *
     * @param mixed $val  value being dumped
     * @param array $opts options for string values
     *                      addQuotes, sanitize, visualWhitespace, etc
     *
     * @return array<string,mixed>
     */
    protected function getPerValueOptions($val, $opts)
    {
        $attribs = array(
            'class' => [],
        );
        if ($val instanceof Abstraction && \is_array($val['attribs'])) {
            $attribs = \array_merge(
                $attribs,
                $val['attribs']
            );
        }
        $parentOptions = parent::getPerValueOptions($val, $opts);
        return \array_merge(
            $parentOptions,
            array(
                'attribs' => $attribs,
                'postDump' => null,
                'tagName' => $parentOptions['type'] === Type::TYPE_OBJECT
                    ? 'div'
                    : 'span',
            ),
            $opts
        );
    }

    /**
     * Wrap the namespace portion of the identifier in a span.namespace
     *
     * @param string $identifier (class name or function name)
     *
     * @return string html snippet
     */
    private function wrapNamespace($identifier)
    {
        $idx = \strrpos($identifier, '\\');
        return $idx
            ? '<span class="namespace">' . \str_replace('\\', '\\<wbr />', \substr($identifier, 0, $idx + 1)) . '</span>'
                . \substr($identifier, $idx + 1)
            : $identifier;
    }
}
