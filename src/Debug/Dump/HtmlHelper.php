<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2021 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Dump;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Dump\Html as Dumper;

/**
 * Html dump helper methods
 */
class HtmlHelper
{

    protected $debug;
    protected $dumper;

    /**
     * Constructor
     *
     * @param Dumper $dumper Dump\Html instance
     * @param Debug  $debug  Debug instance
     */
    public function __construct(Dumper $dumper, Debug $debug)
    {
        $this->debug = $debug;
        $this->dumper = $dumper;
    }

    /**
     * Convert all arguments to html and join them together.
     *
     * @param array $args arguments
     * @param array $meta meta values
     *
     * @return string html
     */
    public function buildArgString($args, $meta = array())
    {
        if (\count($args) === 0) {
            return '';
        }
        $glueDefault = ', ';
        $glueAfterFirst = true;
        if (\is_string($args[0])) {
            if (\preg_match('/[=:] ?$/', $args[0])) {
                // first arg ends with "=" or ":"
                $glueAfterFirst = false;
                $args[0] = \rtrim($args[0]) . ' ';
            } elseif (\count($args) === 2) {
                $glueDefault = ' = ';
            }
        }
        $glue = $meta['glue'] ?: $glueDefault;
        $args = $this->buildArgStringArgs($args, $meta);
        return $glueAfterFirst
            ? \implode($glue, $args)
            : $args[0] . \implode($glue, \array_slice($args, 1));
    }

    /**
     * Wrap classname in span.classname
     * if namespaced additionally wrap namespace in span.namespace
     * If callable, also wrap with .t_operator and .t_identifier
     *
     * @param mixed  $val     classname or classname(::|->)name (method/property/const)
     * @param string $tagName ("span") html tag to use
     * @param array  $attribs (optional) additional html attributes for classname span
     * @param bool   $wbr     (false)
     *
     * @return string
     */
    public function markupIdentifier($val, $tagName = 'span', $attribs = array(), $wbr = false)
    {
        if ($val instanceof Abstraction) {
            $val = $val['value'];
        }
        $parts = $this->parseIdentifier($val);
        $classname = '';
        $operator = '<span class="t_operator">' . \htmlspecialchars($parts['operator']) . '</span>';
        $identifier = '';
        if ($parts['classname']) {
            $classname = $parts['classname'];
            $idx = \strrpos($classname, '\\');
            if ($idx) {
                $classname = '<span class="namespace">' . \str_replace('\\', '\\<wbr />', \substr($classname, 0, $idx + 1)) . '</span>'
                    . \substr($classname, $idx + 1);
            }
            $classname = $this->debug->html->buildTag(
                $tagName,
                $this->debug->arrayUtil->mergeDeep(array(
                    'class' => array('classname'),
                ), (array) $attribs),
                $classname
            ) . '<wbr />';
        }
        if ($parts['identifier']) {
            $identifier = '<span class="t_identifier">' . $parts['identifier'] . '</span>';
        }
        $parts = \array_filter(array($classname, $identifier), 'strlen');
        $html = \implode($operator, $parts);
        if ($wbr === false) {
            $html = \str_replace('<wbr />', '', $html);
        }
        return $html;
    }

    /**
     * Markup type-hint / type declaration
     *
     * @param string $type    type declaration
     * @param array  $attribs (optional) additional html attributes
     *
     * @return string
     */
    public function markupType($type, $attribs = array())
    {
        $phpPrimatives = array(
            // scalar
            Abstracter::TYPE_BOOL, Abstracter::TYPE_FLOAT, Abstracter::TYPE_INT, Abstracter::TYPE_STRING,
            // compound
            Abstracter::TYPE_ARRAY, Abstracter::TYPE_CALLABLE, Abstracter::TYPE_OBJECT, 'iterable',
            // "special"
            Abstracter::TYPE_NULL, Abstracter::TYPE_RESOURCE,
        );
        $typesOther = array(
            '$this','false','mixed','static','self','true','void',
        );
        $typesPrimative = \array_merge($phpPrimatives, $typesOther);
        $types = \preg_split('/\s*\|\s*/', $type);
        foreach ($types as $i => $type) {
            $isArray = false;
            if (\substr($type, -2) === '[]') {
                $isArray = true;
                $type = \substr($type, 0, -2);
            }
            if (!\in_array($type, $typesPrimative)) {
                $type = $this->markupIdentifier($type);
            }
            if ($isArray) {
                $type .= '<span class="t_punct">[]</span>';
            }
            $types[$i] = '<span class="t_type">' . $type . '</span>';
        }
        $types = \implode('<span class="t_punct">|</span>', $types);
        $attribs = \array_filter($attribs);
        if ($attribs) {
            $type = $this->debug->html->buildtag('span', $attribs, $types);
        }
        return $types;
    }

    /**
     * Insert a row containing code snip & arguments after the given row
     *
     * @param string $html    <tr>...</tr>
     * @param array  $row     Row values
     * @param array  $rowInfo Row info / meta
     * @param int    $index   Row index
     *
     * @return string
     */
    public function tableAddContextRow($html, $row, $rowInfo, $index)
    {
        if (!$rowInfo['context']) {
            return $html;
        }
        $html = \str_replace('<tr>', '<tr' . ($index === 0 ? ' class="expanded"' : '') . ' data-toggle="next">', $html);
        $html .= '<tr class="context" ' . ($index === 0 ? 'style="display:table-row;"' : '' ) . '>'
            . '<td colspan="4">'
                . $this->buildContext($rowInfo['context'], $row['line'])
                . '{{arguments}}'
            . '</td>' . "\n"
            . '</tr>' . "\n";
        $crateRawWas = $this->dumper->crateRaw;
        $this->dumper->crateRaw = true;
        $args = $rowInfo['args']
            ? '<hr />Arguments = ' . $this->dumper->dump($rowInfo['args'])
            : '';
        $this->dumper->crateRaw = $crateRawWas;
        return \str_replace('{{arguments}}', $args, $html);
    }

    /**
     * Format trace table's function column
     *
     * @param string $html <tr>...</tr>
     * @param array  $row  row values
     *
     * @return string
     */
    public function tableMarkupFunction($html, $row)
    {
        if (isset($row['function'])) {
            $regex = '/^(.+)(::|->)(.+)$/';
            $replace = \preg_match($regex, $row['function']) || \strpos($row['function'], '{closure}')
                ? $this->markupIdentifier($row['function'], 'span', array(), true)
                : '<span class="t_identifier">' . \htmlspecialchars($row['function']) . '</span>';
            $replace = '<td class="col-function no-quotes t_string">' . $replace . '</td>';
            $html = \str_replace(
                '<td class="t_string">' . \htmlspecialchars($row['function']) . '</td>',
                $replace,
                $html
            );
        }
        return $html;
    }

    /**
     * Return array of dumped arguments
     *
     * @param array $args arguments
     * @param array $meta meta values
     *
     * @return array
     */
    private function buildArgStringArgs($args, $meta)
    {
        foreach ($args as $i => $v) {
            list($type, $typeMore) = $this->debug->abstracter->getType($v);
            $typeMore2 = $typeMore === Abstracter::TYPE_ABSTRACTION
                ? $v['typeMore']
                : $typeMore;
            $args[$i] = $this->dumper->dump($v, array(
                'addQuotes' => $i !== 0 || $typeMore2 === Abstracter::TYPE_STRING_NUMERIC,
                'sanitize' => $i === 0
                    ? $meta['sanitizeFirst']
                    : $meta['sanitize'],
                'type' => $type,
                'typeMore' => $typeMore,
                'visualWhiteSpace' => $i !== 0,
            ));
        }
        return $args;
    }

    /**
     * build php code snippet / context
     *
     * @param string[] $lines   lines of code
     * @param int      $lineNum line number to highlight
     *
     * @return string
     */
    private function buildContext($lines, $lineNum)
    {
        return $this->debug->html->buildTag(
            'pre',
            array(
                'class' => 'highlight line-numbers',
                'data-line' => $lineNum,
                'data-start' => \key($lines),
            ),
            '<code class="language-php">' . \htmlspecialchars(\implode($lines)) . '</code>'
        );
    }

    /**
     * Split identifier into classname, operator, & identifier
     *
     * @param mixed $val classname or classname(::|->)name (method/property/const)
     *
     * @return array
     */
    private function parseIdentifier($val)
    {
        $parsed = array(
            'classname' => $val,
            'operator' => '::',
            'identifier' => '',
        );
        $regex = '/^(.+)(::|->)(.+)$/';
        $matches = array();
        if (\is_array($val)) {
            $parsed['classname'] = $val[0];
            $parsed['identifier'] = $val[1];
        } elseif (\preg_match($regex, $val, $matches)) {
            $parsed['classname'] = $matches[1];
            $parsed['operator'] = $matches[2];
            $parsed['identifier'] = $matches[3];
        } elseif (\preg_match('/^(.+)(\\\\\{closure\})$/', $val, $matches)) {
            $parsed['classname'] = $matches[1];
            $parsed['operator'] = '';
            $parsed['identifier'] = $matches[2];
        }
        return $parsed;
    }
}
