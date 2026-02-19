<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.0
 */

namespace bdk\Debug\Dump\Html;

use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Abstraction\Object\Abstraction as ObjectAbstraction;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\Dump\Base\Value as BaseValue;
use bdk\Debug\Dump\Html as Dumper;
use bdk\Debug\Dump\Html\HtmlArray;
use bdk\Debug\Dump\Html\HtmlObject;
use bdk\Debug\Dump\Html\HtmlString;
use bdk\Debug\Dump\Html\Table;

/**
 * Dump val as HTML
 *
 * @property HtmlObject $object lazy-loaded HtmlObject... only loaded if dumping an object
 * @property HtmlTable  $table  lazy-loaded HtmlTable... only loaded if outputting a table
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

    /** @var Table */
    protected $lazyTable;

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
            'charHighlightTrim' => false,
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
        \bdk\Debug\Utility\PhpType::assertType($abs, 'bdk\Debug\Abstraction\Abstraction|null', 'abs');

        $date = parent::checkTimestamp($val, $abs);
        if ($date) {
            $this->optionSet('postDump', function ($dumped, $opts) use ($val, $date) {
                $attribsContainer = array(
                    'class' => ['timestamp', 'value-container'],
                    'title' => $date,
                );
                if ($opts['tagName'] !== 'span') {
                    // if tagName is not 'span' or even if null, go ahead and wrap/rebuild in <span class="t_int" data-type-more="timestamp">
                    $dumped = $this->html->buildTag('span', $opts['attribs'], $val);
                }
                $this->optionSet('attribs', $attribsContainer); // replace attribs with new outer container attribs
                // dumped is now : <span class="t_int" data-type-more="timestamp">1767751464</span>
                return $opts['tagName'] !== null
                    ? $this->html->buildTag($opts['tagName'], $attribsContainer, $dumped)
                    : $dumped;
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
    public function dump($val, array $opts = array())
    {
        $opts = $this->getPerValueOptions($val, $opts);
        $this->optionStackPush($opts); // sets optionsCurrent
        $dumped = $this->doDump($val);
        if ($this->optionsCurrent['type'] && $this->optionsCurrent['dumpType']) {
            $this->optionsCurrent['attribs']['class'][] = 't_' . $this->optionsCurrent['type'];
        }
        if (\in_array($this->optionsCurrent['typeMore'], [null, Type::TYPE_RAW], true) === false) {
            $this->optionsCurrent['attribs']['data-type-more'] = \trim($this->optionsCurrent['typeMore']);
        }
        $tagName = $this->optionsCurrent['tagName'];
        if ($tagName) {
            $dumped = $this->html->buildTag($tagName, $this->optionsCurrent['attribs'], $dumped);
        }
        if ($this->optionsCurrent['postDump']) {
            $dumped = \call_user_func($this->optionsCurrent['postDump'], $dumped, $this->optionsCurrent);
        }
        $this->optionStackPop();
        return $dumped;
    }

    /**
     * Wrap classname in span.classname
     *
     * if namespaced additionally wrap namespace in span.namespace
     *
     * @param string|array $val     classname or classname(::|->)name (method/property/const)
     * @param string       $what    (Type::TYPE_IDENTIFIER_CLASSNAME), Type::TYPE_IDENTIFIER_CONST, or Type::TYPE_IDENTIFIER_METHOD
     *                                specify what we're marking if ambiguous
     * @param string       $tagName ("span") html tag to use
     * @param array|null   $attribs (optional) additional html attributes for classname span (such as title)
     * @param bool         $wbr     (false) whether to add a <wbr /> after the classname
     *
     * @return string html snippet
     */
    public function markupIdentifier($val, $what = Type::TYPE_IDENTIFIER_CLASSNAME, $tagName = 'span', $attribs = array(), $wbr = false)
    {
        $parts = \array_map([$this->string, 'dump'], $this->parseIdentifier($val, $what));
        $class = 'classname';
        $classOrNamespace = $this->wrapNamespace($parts['classname'], $wbr);
        if ($parts['namespace']) {
            $class = 'namespace';
            $classOrNamespace = $parts['namespace'];
        }
        if ($classOrNamespace) {
            $classOrNamespace = $this->html->buildTag(
                $tagName,
                $this->debug->arrayUtil->mergeDeep(array(
                    'class' => [$class],
                ), (array) $attribs),
                $classOrNamespace
            );
        }
        $parts2 = \array_filter(\array_intersect_key($parts, \array_flip(['operator', 'name'])));
        foreach ($parts2 as $key => $value) {
            $parts[$key] = '<span class="t_' . $key . '">' . $value . '</span>';
        }
        if ($wbr) {
            $parts['operator'] = '<wbr />' . $parts['operator'];
        }
        return \implode($parts['operator'], \array_filter([$classOrNamespace, $parts['name']]));
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
        $options = $this->optionGet();
        if ($val === true && isset($options['trueAs'])) {
            return $options['trueAs'];
        }
        if ($val === false && isset($options['falseAs'])) {
            return $options['falseAs'];
        }
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
            . $this->markupIdentifier($abs['value'], Type::TYPE_IDENTIFIER_METHOD)
            . '</span>';
    }

    /**
     * {@inheritDoc}
     */
    protected function dumpFloat($val, $abs = null)
    {
        \bdk\Debug\Utility\PhpType::assertType($abs, 'bdk\Debug\Abstraction\Abstraction|null', 'abs');

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
            $this->optionSet('attribs.title', $this->debug->i18n->trans('word.value') . ': ' . $valueAsString);
        }
        return $this->markupIdentifier($abs['value'], $abs['typeMore'], 'span', [], false);
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
        return '<span class="t_keyword">array</span> <span class="t_recursion">*' . $this->debug->i18n->trans('abs.recursion') . '*</span>';
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
        \bdk\Debug\Utility\PhpType::assertType($abs, 'bdk\Debug\Abstraction\Abstraction|null', 'abs');

        return $this->string->dump($val, $abs);
    }

    /**
     * Dump Table
     *
     * @param Abstraction $abs Table abstraction
     *
     * @return string
     */
    protected function dumpTable(Abstraction $abs)
    {
        $this->optionSet('tagName', null);
        return $this->getTable()->dump($abs);
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
     * Getter for this->table
     *
     * @return HtmlTable
     */
    protected function getTable()
    {
        if (!$this->lazyTable) {
            $this->lazyTable = new Table($this->dumper, $this->dumper->helper);
        }
        return $this->lazyTable;
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
        $parentOptions = parent::getPerValueOptions($val, $opts);
        $isAbstraction = $val instanceof Abstraction;
        return $this->debug->arrayUtil->mergeDeep(
            array(
                'attribs' => array(
                    'class' => [],
                ),
                'dumpType' => true,
                'postDump' => null,
                'tagName' => $parentOptions['type'] === Type::TYPE_OBJECT && (!$isAbstraction || !($val['cfgFlags'] & AbstractObject::BRIEF))
                    ? 'div'
                    : 'span',
            ),
            $parentOptions,
            array(
                'attribs' => $isAbstraction && \is_array($val['attribs'])
                    ? $val['attribs']
                    : [],
            )
        );
    }

    /**
     * Wrap the namespace portion of the identifier in a span.namespace
     *
     * @param string $identifier (class name or function name)
     * @param bool   $wbr        (false) whether to add a <wbr /> after each namespace segment
     *
     * @return string html snippet
     */
    private function wrapNamespace($identifier, $wbr = false)
    {
        $idx = \strrpos($identifier, '\\');
        $wbrTag = $wbr ? '<wbr />' : '';
        return $idx
            ? '<span class="namespace">' . \str_replace('\\', '\\' . $wbrTag, \substr($identifier, 0, $idx + 1)) . '</span>'
                . \substr($identifier, $idx + 1)
            : $identifier;
    }
}
