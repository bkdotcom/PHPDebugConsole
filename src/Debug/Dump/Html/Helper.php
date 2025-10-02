<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0b1
 */

namespace bdk\Debug\Dump\Html;

use bdk\Debug;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\Dump\Html as Dumper;

/**
 * Html dump helper methods
 */
class Helper
{
    /** @var Debug */
    protected $debug;

    /** @var Dumper */
    protected $dumper;

    /** @var \bdk\Debug\Utility\Html */
    protected $html;

    /**
     * Constructor
     *
     * @param Dumper $dumper Dump\Html instance
     */
    public function __construct(Dumper $dumper)
    {
        $this->debug = $dumper->debug;
        $this->dumper = $dumper;
        $this->html = $dumper->debug->html;
    }

    /**
     * Convert all arguments to html and join them together.
     *
     * @param array $args arguments
     * @param array $meta meta values
     *
     * @return string html
     */
    public function buildArgString(array $args, array $meta = array())
    {
        if (\count($args) === 0) {
            return '';
        }

        list($glue, $glueAfterFirst) = $this->getArgGlue($args, $meta['glue']);

        if ($glueAfterFirst === false && \is_string($args[0])) {
            // first arg is not glued / don't trim Abstractions
            $args[0] = \rtrim($args[0]) . ' ';
        }

        $args = $this->buildArgStringArgs($args, $meta);
        return $glueAfterFirst
            ? \implode($glue, $args)
            : $args[0] . \implode($glue, \array_slice($args, 1));
    }

    /**
     * build php code snippet / context
     *
     * @param string[] $lines      lines of code
     * @param int      $lineNumber line number to highlight
     *
     * @return string
     */
    public function buildContext(array $lines, $lineNumber)
    {
        return $this->html->buildTag(
            'pre',
            array(
                'class' => 'highlight line-numbers',
                'data-line' => $lineNumber,
                'data-line-offset' => \key($lines), // needed for line-highlight
                'data-start' => \key($lines),
            ),
            '<code class="language-php">'
                . \htmlspecialchars(\implode($lines))
                . '</code>'
        );
    }

    /**
     * Build context + arguments cell data
     *
     * @param array $rowInfo    row meta information
     * @param int   $lineNumber line number to highlight
     *
     * @return Abstraction
     */
    public function buildContextCell(array $rowInfo, $lineNumber)
    {
        $innerHtml = $this->buildContext($rowInfo['context'], $lineNumber)
            . $this->buildContextArguments($rowInfo['args']);
        return $this->debug->abstracter->crateWithVals($innerHtml, array(
            'dumpType' => false, // don't add t_string css class
            'sanitize' => false,
            'visualWhiteSpace' => false,
        ));
    }

    /**
     * Dump phpDoc string
     *
     * @param string|null $markdown summary, description, or other text gathered from phpDoc
     * @param array       $opts     dump options
     *
     * @link https://github.github.com/gfm/#disallowed-raw-html-extension-
     * @link https://github.com/github/markup/issues/245
     * @link https://gist.github.com/seanh/13a93686bf4c2cb16e658b3cf96807f2
     *
     * @return string
     */
    public function dumpPhpDoc($markdown, array $opts = array())
    {
        $markdown = \trim((string) $markdown);
        if ($markdown === '') {
            return '';
        }
        // run through valDumper to highlight chars
        return $this->dumper->valDumper->dump(
            $markdown,
            \array_merge(array(
                'sanitize' => false, // phpDoc parser sanitized by removing non-whitelisted html tags & attributes
                'tagName' => null,
                'type' => Type::TYPE_STRING,
                'visualWhiteSpace' => false,
            ), $opts)
        );
    }

    /**
     * Markup type-hint / type declaration
     *
     * @param string $type    type declaration
     * @param array  $attribs (optional) additional html attributes
     *
     * @return string
     */
    public function markupType($type, array $attribs = array())
    {
        $regex = '/(?:
            (\$this|[-\w\[\]\'"\\\\]+:?)
            |
            ([\(\)<>\{\},\|&])
            )/x';
        $type = \preg_replace_callback($regex, function ($matches) {
            return $matches[1] !== ''
                ? $this->markupTypePart($matches[1])
                : $this->html->buildTag('span', array('class' => 't_punct'), \htmlspecialchars($matches[2]));
        }, $type);
        $attribs = \array_filter($attribs);
        if ($attribs) {
            $type = $this->html->buildTag('span', $attribs, $type);
        }
        return $type;
    }

    /**
     * Return array of dumped arguments
     *
     * @param array $args arguments
     * @param array $meta meta values
     *
     * @return array
     */
    private function buildArgStringArgs(array $args, array $meta)
    {
        return \array_map(function ($arg, $i) use ($meta) {
            list($type, $typeMore) = $this->debug->abstracter->type->getType($arg);
            $isNumericString = $type === Type::TYPE_STRING
                && \in_array($typeMore, [Type::TYPE_STRING_NUMERIC, Type::TYPE_TIMESTAMP], true);
            return $this->dumper->valDumper->dump($arg, array(
                'addQuotes' => $i !== 0 || $isNumericString || $type !== Type::TYPE_STRING,
                'sanitize' => $i === 0
                    ? $meta['sanitizeFirst']
                    : $meta['sanitize'],
                'type' => $type,
                'typeMore' => $typeMore,
                'visualWhiteSpace' => $i !== 0 || $type !== Type::TYPE_STRING,
            ));
        }, $args, \array_keys($args));
    }

    /**
     * Dump context arguments
     *
     * @param string|array $args Arguments from backtrace
     *
     * @return string
     */
    private function buildContextArguments($args)
    {
        if (\is_array($args) === false || \count($args) === 0) {
            return '';
        }
        $crateRawWas = $this->dumper->crateRaw;
        $this->dumper->crateRaw = true;
        // set maxDepth for args
        $maxDepthBak = $this->debug->getCfg('maxDepth');
        if ($maxDepthBak > 0) {
            $this->debug->setCfg('maxDepth', $maxDepthBak + 1, Debug::CONFIG_NO_PUBLISH);
        }
        $args = '<hr />Arguments = ' . $this->dumper->valDumper->dump($args);
        $this->debug->setCfg('maxDepth', $maxDepthBak, Debug::CONFIG_NO_PUBLISH | Debug::CONFIG_NO_RETURN);
        $this->dumper->crateRaw = $crateRawWas;
        return $args;
    }

    /**
     * Get argument "glue" and whether to glue after first arg
     *
     * @param array       $args     arguments
     * @param string|null $metaGlue glue specified in meta values
     *
     * @return [string, bool] glue, glueAfterFirst
     */
    private function getArgGlue(array $args, $metaGlue)
    {
        $glueDefault = ', ';
        $glueAfterFirst = true;
        $firstArgIsString = $this->debug->abstracter->type->getType($args[0])[0] === Type::TYPE_STRING;
        if ($firstArgIsString === false) {
            return [$glueDefault, $glueAfterFirst];
        }
        if (\preg_match('/[=:] ?$/', $args[0])) {
            // first arg ends with "=" or ":"
            $glueAfterFirst = false;
        } elseif (\count($args) === 2) {
            $glueDefault = ' = ';
        }
        $glue = $metaGlue ?: $glueDefault;
        return [$glue, $glueAfterFirst];
    }

    /**
     * Markup a single type-hint / type declaration
     *
     * @param string $type type declaration
     *
     * @return string
     */
    private function markupTypePart($type)
    {
        $arrayCount = 0; // how many "[]" at end..
        if (\preg_match('/(\[\])+$/', $type, $matches)) {
            $strlen = \strlen($matches[0]);
            $arrayCount = $strlen / 2;
            $type = \substr($type, 0, 0 - $strlen);
        }
        if (\is_numeric($type)) {
            return $this->html->buildTag('span', array('class' => 't_type'), $type);
        }
        if (\substr($type, -1) === ':') {
            // array "shape" key
            $type = \trim($type, ':\'"');
            return $this->html->buildTag('span', array('class' => 't_string'), $type)
                . $this->html->buildTag('span', array('class' => 't_punct'), ':');
        }
        if (\preg_match('/^[\'"]/', $type)) {
            $type = \trim($type, '\'"');
            return $this->html->buildTag('span', array('class' => 't_string t_type'), $type);
        }
        if (\in_array($type, $this->debug->phpDoc->type->types, true) === false) {
            $type = $this->dumper->valDumper->markupIdentifier($type);
        }
        if ($arrayCount > 0) {
            $type .= $this->html->buildTag('span', array('class' => 't_punct'), \str_repeat('[]', $arrayCount));
        }
        return $this->html->buildTag('span', array('class' => 't_type'), $type);
    }
}
