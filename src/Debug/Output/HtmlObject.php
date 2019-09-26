<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v2.3
 */

namespace bdk\Debug\Output;

use bdk\Debug;

/**
 * Output object as HTML
 */
class HtmlObject
{

    protected $debug;

	/**
     * Constructor
     *
     * @param \bdk\Debug $debug Debug instance
     */
	public function __construct(Debug $debug)
	{
		$this->debug = $debug;
	}

    /**
     * Dump object as html
     *
     * @param array $abs object abstraction
     *
     * @return string
     */
	public function dump($abs)
	{
        $title = \trim($abs['phpDoc']['summary']."\n\n".$abs['phpDoc']['desc']);
        $strClassName = $this->debug->output->html->markupClassname($abs['className'], 'span', array(
            'title' => $title ?: null,
        ));
        if ($abs['isRecursion']) {
            return $strClassName
                .' <span class="t_recursion">*RECURSION*</span>';
        }
        if ($abs['isExcluded']) {
            return $this->dumpToString($abs)
                .$strClassName."\n"
                .'<span class="excluded">(not inspected)</span>';
        }
        $html = $this->dumpToString($abs)
            .$strClassName."\n"
            .'<dl class="object-inner">'."\n"
                .'<dt>extends</dt>'."\n"
                    .'<dd class="extends">'.\implode('</dd>'."\n".'<dd class="extends">', $abs['extends']).'</dd>'."\n"
                .'<dt>implements</dt>'."\n"
                    .'<dd class="interface">'.\implode('</dd>'."\n".'<dd class="interface">', $abs['implements']).'</dd>'."\n"
                .$this->dumpConstants($abs['constants'])
                .$this->dumpProperties($abs)
                .($abs['collectMethods'] && $this->debug->output->getCfg('outputMethods')
                    ? $this->dumpMethods($abs['methods'])
                    : '')
                .$this->dumpPhpDoc($abs['phpDoc'])
            .'</dl>'."\n";
        // remove <dt>'s with empty <dd>'
        $html = \preg_replace('#<dt[^>]*>\w+</dt>\s*<dd[^>]*></dd>\s*#', '', $html);
        $html = \str_replace(' title=""', '', $html);
        return $html;
    }

    /**
     * Dump object's __toString or stringified value
     *
     * @param array $abs object abstraction
     *
     * @return string html
     */
    protected function dumpToString($abs)
    {
        $val = '';
        if ($abs['stringified']) {
            $val = $abs['stringified'];
        } elseif (isset($abs['methods']['__toString']['returnValue'])) {
            $val = $abs['methods']['__toString']['returnValue'];
        }
        if (!$val) {
            return '';
        }
        $valAppend = '';
        $len = \strlen($val);
        if ($len > 100) {
            $val = \substr($val, 0, 100);
            $valAppend = '&hellip; <i>('.($len - 100).' more chars)</i>';
        }
        $toStringDump = $this->debug->output->html->dump($val);
        $parsed = $this->debug->utilities->parseTag($toStringDump);
        $classArray = \explode(' ', $parsed['attribs']['class']);
        $classArray[] = 't_stringified';
        if ($len > 100) {
            $classArray[] = 't_string_trunc';   // truncated
        }
        $attribs = array(
            'class' => $classArray,
            'title' => isset($parsed['attribs']['title'])
                // ie a timestamp will have a human readable date in title
                ? (!$abs['stringified'] ? '__toString() : ' : '').$parsed['attribs']['title']
                : (!$abs['stringified'] ? '__toString()' : null),
        );
        return $this->debug->utilities->buildTag(
            'span',
            $attribs,
            $parsed['innerhtml'].$valAppend
        )."\n";
    }

    /**
     * dump object constants as html
     *
     * @param array $constants array of name=>value
     *
     * @return string html
     */
    protected function dumpConstants($constants)
    {
        $str = '';
        if ($constants && $this->debug->output->getCfg('outputConstants')) {
            $str = '<dt class="constants">constants</dt>'."\n";
            foreach ($constants as $k => $value) {
                $str .= '<dd class="constant">'
                    .'<span class="constant-name">'.$k.'</span>'
                    .' <span class="t_operator">=</span> '
                    .$this->debug->output->html->dump($value)
                    .'</dd>'."\n";
            }
        }
        return $str;
    }

    /**
     * Dump object methods as html
     *
     * @param array $methods methods as returned from getMethods
     *
     * @return string html
     */
    protected function dumpMethods($methods)
    {
        $label = \count($methods)
            ? 'methods'
            : 'no methods';
        $str = '<dt class="methods">'.$label.'</dt>'."\n";
        $magicMethods = \array_intersect(array('__call','__callStatic'), \array_keys($methods));
        $str .= $this->magicMethodInfo($magicMethods);
        foreach ($methods as $methodName => $info) {
            if (!isset($info['phpDoc']['return'])) {
                $info['phpDoc']['return'] = array(
                    'desc' => null,
                    'type' => null,
                );
            }
            $classes = \array_keys(\array_filter(array(
                'method' => true,
                'deprecated' => $info['isDeprecated'],
            )));
            $modifiers = \array_keys(\array_filter(array(
                'final' => $info['isFinal'],
                $info['visibility'] => true,
                'static' => $info['isStatic'],
            )));
            $str .= $this->debug->utilities->buildTag(
                'dd',
                array(
                    'class' => \array_merge($classes, $modifiers),
                    'data-implements' => $info['implements'],
                ),
                \implode(' ', \array_map(function ($modifier) {
                    return '<span class="t_modifier_'.$modifier.'">'.$modifier.'</span>';
                }, $modifiers))
                .' '.$this->debug->utilities->buildTag(
                    'span',
                    array(
                        'class' => 't_type',
                        'title' => $info['phpDoc']['return']['desc'],
                    ),
                    $info['phpDoc']['return']['type']
                )
                .' '.$this->debug->utilities->buildTag(
                    'span',
                    array(
                        'class' => 'method-name',
                        'title' => \trim($info['phpDoc']['summary']
                            .($this->debug->output->getCfg('outputMethodDescription')
                                ? "\n\n".$info['phpDoc']['desc']
                                : '')),
                    ),
                    $methodName
                )
                .'<span class="t_punct">(</span>'
                .$this->dumpMethodParams($info['params'])
                .'<span class="t_punct">)</span>'
                .($methodName == '__toString'
                    ? '<br />'.$this->debug->output->html->dump($info['returnValue'])
                    : '')
            )."\n";
        }
        $str = \str_replace(' data-implements="null"', '', $str);
        $str = \str_replace(' <span class="t_type"></span>', '', $str);
        return $str;
    }

    /**
     * Dump method parameters as HTML
     *
     * @param array $params params as returned from getParams()
     *
     * @return string html
     */
    protected function dumpMethodParams($params)
    {
        $paramStr = '';
        foreach ($params as $info) {
            $paramStr .= '<span class="parameter">';
            if (!empty($info['type'])) {
                $paramStr .= '<span class="t_type">'.$info['type'].'</span> ';
            }
            $paramStr .= '<span class="t_parameter-name"'
                .' title="'.\htmlspecialchars($info['desc']).'"'
                .'>'.\htmlspecialchars($info['name']).'</span>';
            if ($info['defaultValue'] !== $this->debug->abstracter->UNDEFINED) {
                $defaultValue = $info['defaultValue'];
                $paramStr .= ' <span class="t_operator">=</span> ';
                if ($info['constantName']) {
                    /*
                        only php >= 5.4.6 supports this...
                        or via @method phpDoc

                        show the constant name / hover for value
                    */
                    $title = '';
                    $type = $this->debug->abstracter->getType($defaultValue);
                    if (!\in_array($type, array('array','resource'))) {
                        $title = $this->debug->output->text->dump($defaultValue);
                        $title = \htmlspecialchars('value: '.$title);
                    }
                    $paramStr .= '<span class="t_parameter-default t_const"'
                        .' title="'.$title.'"'
                        .'>'.$info['constantName'].'</span>';
                } else {
                    /*
                        The constant's value is shown
                    */
                    if (\is_string($defaultValue)) {
                        $defaultValue = \str_replace("\n", ' ', $defaultValue);
                    }
                    $parsed = $this->debug->utilities->parseTag($this->debug->output->html->dump($defaultValue));
                    $class = \trim('t_parameter-default '.$parsed['attribs']['class']);
                    $paramStr .= '<span class="'.$class.'">'.$parsed['innerhtml'].'</span>';
                }
            }
            $paramStr .= '</span>, '; // end .parameter
        }
        $paramStr = \trim($paramStr, ', ');
        return $paramStr;
    }

    /**
     * Dump object's phpDoc info as html
     *
     * @param array $phpDoc parsed phpDoc
     *
     * @return string html
     */
    protected function dumpPhpDoc($phpDoc)
    {
        $str = '';
        foreach ($phpDoc as $k => $values) {
            if (!\is_array($values)) {
                continue;
            }
            foreach ($values as $value) {
                if ($k == 'link') {
                    $value = '<a href="'.$value['uri'].'" target="_blank">'
                        .\htmlspecialchars($value['desc'] ?: $value['uri'])
                        .'</a>';
                } elseif ($k == 'see' && $value['uri']) {
                    $value = '<a href="'.$value['uri'].'" target="_blank">'
                        .\htmlspecialchars($value['desc'] ?: $value['uri'])
                        .'</a>';
                } else {
                    $value = \htmlspecialchars(\implode(' ', $value));
                }
                $str .= '<dd class="phpdoc phpdoc-'.$k.'">'
                    .'<span class="phpdoc-tag">'.$k.'</span>'
                    .'<span class="t_operator">:</span> '
                    .$value
                    .'</dd>'."\n";
            }
        }
        if ($str) {
            $str = '<dt class="phpDoc">phpDoc</dt>'."\n"
                .$str;
        }
        return $str;
    }

    /**
     * Dump object properties as HTML
     *
     * @param array $abs object abstraction
     *
     * @return string
     */
    protected function dumpProperties($abs)
    {
        $label = \count($abs['properties'])
            ? 'properties'
            : 'no properties';
        if ($abs['viaDebugInfo']) {
            $label .= ' <span class="text-muted">(via __debugInfo)</span>';
        }
        $str = '<dt class="properties">'.$label.'</dt>'."\n";
        $magicMethods = \array_intersect(array('__get','__set'), \array_keys($abs['methods']));
        $str .= $this->magicMethodInfo($magicMethods);
        foreach ($abs['properties'] as $k => $info) {
            $vis = (array) $info['visibility'];
            $isPrivateAncestor = \in_array('private', $vis) && $info['inheritedFrom'];
            $classes = \array_keys(\array_filter(array(
                'debuginfo-value' => $info['valueFrom'] == 'debugInfo',
                'excluded' => $info['isExcluded'],
                'forceShow' => $info['forceShow'],
                'debug-value' => $info['valueFrom'] == 'debug',
                'private-ancestor' => $isPrivateAncestor,
                'property' => true,
                \implode(' ', $vis) => $info['visibility'] !== 'debug',
            )));
            $modifiers = $vis;
            if ($info['isStatic']) {
                $modifiers[] = 'static';
            }
            $str .= '<dd class="'.\implode(' ', $classes).'">'
                .\implode(' ', \array_map(function ($modifier) {
                    return '<span class="t_modifier_'.$modifier.'">'.$modifier.'</span>';
                }, $modifiers))
                .($isPrivateAncestor
                    ? ' (<i>'.$info['inheritedFrom'].'</i>)'
                    : '')
                .($info['type']
                    ? ' <span class="t_type">'.$info['type'].'</span>'
                    : '')
                .' <span class="property-name"'
                    .' title="'.\htmlspecialchars($info['desc']).'"'
                    .'>'.$k.'</span>'
                .($info['value'] !== $this->debug->abstracter->UNDEFINED
                    ? ' <span class="t_operator">=</span> '
                        .$this->debug->output->html->dump($info['value'])
                    : '')
                .'</dd>'."\n";
        }
        return $str;
    }

    /**
     * Generate some info regarding the given method names
     *
     * @param string[] $methods method names
     *
     * @return string
     */
    private function magicMethodInfo($methods)
    {
        if (!$methods) {
            return '';
        }
        foreach ($methods as $i => $method) {
            $methods[$i] = '<code>'.$method.'</code>';
        }
        $methods = $i == 0
            ? 'a '.$methods[0].' method'
            : \implode(' and ', $methods).' methods';
        return '<dd class="magic info">This object has '.$methods.'</dd>'."\n";
    }
}
