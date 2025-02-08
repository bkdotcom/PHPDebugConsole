<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0b1
 */

namespace bdk\Debug\Dump\Html;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
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

    /**
     * Constructor
     *
     * @param Dumper $dumper Dump\Html instance
     */
    public function __construct(Dumper $dumper)
    {
        $this->debug = $dumper->debug;
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
    public function buildArgString(array $args, array $meta = array())
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
     * build php code snippet / context
     *
     * @param string[] $lines   lines of code
     * @param int      $lineNum line number to highlight
     *
     * @return string
     */
    public function buildContext(array $lines, $lineNum)
    {
        return $this->debug->html->buildTag(
            'pre',
            array(
                'class' => 'highlight line-numbers',
                'data-line' => $lineNum,
                'data-line-offset' => \key($lines), // needed for line-highlight
                'data-start' => \key($lines),
            ),
            '<code class="language-php">'
                . \htmlspecialchars(\implode($lines))
                . '</code>'
        );
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
     * Markup filepath
     *
     * Wrap directory components
     *
     * @param string $filePath     filepath (ie /var/www/html/index.php)
     * @param string $commonPrefix prefix shared by current group of files
     *
     * @return string
     */
    public function markupFilePath($filePath, $commonPrefix = '')
    {
        if ($filePath === 'eval()\'d code') {
            return \htmlspecialchars($filePath);
        }
        $fileParts = $this->parseFilePath($filePath, $commonPrefix);
        $dumpOpts = array(
            'tagName' => null,
        );
        return ($fileParts['docRoot'] ? '<span class="file-docroot">DOCUMENT_ROOT</span>' : '')
            . ($fileParts['relPathCommon']
                ? '<span class="file-basepath">' . $this->dumper->valDumper->dump($fileParts['relPathCommon'], $dumpOpts) . '</span>'
                : '')
            . ($fileParts['relPath']
                ? '<span class="file-relpath">' . $this->dumper->valDumper->dump($fileParts['relPath'], $dumpOpts) . '</span>'
                : '')
            . '<span class="file-basename">' . $this->dumper->valDumper->dump($fileParts['baseName'], $dumpOpts) . '</span>';
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
                : '<span class="t_punct">' . \htmlspecialchars($matches[2]) . '</span>';
        }, $type);
        $attribs = \array_filter($attribs);
        if ($attribs) {
            $type = $this->debug->html->buildTag('span', $attribs, $type);
        }
        return $type;
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
    public function tableAddContextRow($html, array $row, array $rowInfo, $index)
    {
        if (empty($rowInfo['context']) || $rowInfo['context'] === Abstracter::UNDEFINED) {
            return $html;
        }
        $html = \preg_replace_callback('/^<tr([^>]*)>/', function ($matches) use ($index) {
            $attribs = $this->debug->html->parseAttribString($matches[1]);
            $attribs['class']['expanded'] = $index === 0;
            $attribs['data-toggle'] = 'next';
            return '<tr' . $this->debug->html->buildAttribString($attribs) . '>';
        }, $html);
        $html .= '<tr class="context" ' . ($index === 0 ? 'style="display:table-row;"' : '' ) . '>'
            . '<td colspan="4">'
                . $this->buildContext($rowInfo['context'], $row['line'])
                . $this->buildContextArguments($rowInfo['args'])
            . '</td>' . "\n"
            . '</tr>' . "\n";
        return $html;
    }

    /**
     * Format trace table's filepath & function columns
     *
     * @param string $html    <tr>...</tr>
     * @param array  $row     Row values
     * @param array  $rowInfo Row info / meta
     *
     * @return string
     */
    public function tableTraceRow($html, array $row, array $rowInfo)
    {
        \preg_match_all('|
            <(?P<tagname>t[hd])(?P<attribs>[^>]*)>
            (?P<innerHtml>.*?)
            </t[hd]>
            |xs', $html, $cells, PREG_SET_ORDER);

        $cells[1]['innerHtml'] = $this->markupFilePath($row['file'], $rowInfo['commonFilePrefix']);
        if (isset($cells[3]) && $row['function'] !== Abstracter::UNDEFINED) {
            $cells[3]['innerHtml'] = $this->dumper->valDumper->markupIdentifier($row['function'], 'method', 'span', array(), true);
        }
        $trAttribs = \strpos($cells[1]['innerHtml'], 'DOCUMENT_ROOT') !== false
            ? ' data-file="' . \htmlspecialchars($row['file']) . '"'
            : '';
        return '<tr' . $trAttribs . '>' . \join('', \array_map(static function ($parts) {
            return '<' . $parts['tagname'] . $parts['attribs'] . '>' . $parts['innerHtml'] . '</' . $parts['tagname'] . '>';
        }, $cells)) . '</tr>';
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
        foreach ($args as $i => $v) {
            list($type, $typeMore) = $this->debug->abstracter->type->getType($v);
            $isNumericString = $type === Type::TYPE_STRING
                && \in_array($typeMore, [Type::TYPE_STRING_NUMERIC, Type::TYPE_TIMESTAMP], true);
            $args[$i] = $this->dumper->valDumper->dump($v, array(
                'addQuotes' => $i !== 0 || $isNumericString || $type !== Type::TYPE_STRING, // $this->dumper->valDumper->string->isEncoded($v) ||
                'sanitize' => $i === 0
                    ? $meta['sanitizeFirst']
                    : $meta['sanitize'],
                'type' => $type,
                'typeMore' => $typeMore,
                'visualWhiteSpace' => $i !== 0 || $type !== Type::TYPE_STRING,
            ));
        }
        return $args;
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
            return '<span class="t_type">' . $type . '</span>';
        }
        if (\substr($type, -1) === ':') {
            // array "shape" key
            $type = \trim($type, ':\'"');
            return '<span class="t_string">' . $type . '</span><span class="t_punct">:</span>';
        }
        if (\preg_match('/^[\'"]/', $type)) {
            $type = \trim($type, '\'"');
            return '<span class="t_string t_type">' . $type . '</span>';
        }
        if (\in_array($type, $this->debug->phpDoc->type->types, true) === false) {
            $type = $this->dumper->valDumper->markupIdentifier($type);
        }
        if ($arrayCount > 0) {
            $type .= '<span class="t_punct">' . \str_repeat('[]', $arrayCount) . '</span>';
        }
        return '<span class="t_type">' . $type . '</span>';
    }

    /**
     * Parse file path into parts
     *
     * @param string $filePath     filepath (ie /var/www/html/index.php)
     * @param string $commonPrefix prefix shared by current group of files
     *
     * @return array
     */
    private function parseFilePath($filePath, $commonPrefix)
    {
        $docRoot = $this->debug->serverRequest->getServerParam('DOCUMENT_ROOT');
        $baseName = \basename($filePath);
        $containsDocRoot = \strpos($filePath, $docRoot) === 0;
        $basePath = '';
        $relPath = \substr($filePath, 0, 0 - \strlen($baseName));
        if ($commonPrefix || $containsDocRoot) {
            $strLengths = \array_intersect_key(
                [\strlen($commonPrefix), \strlen($docRoot)],
                \array_filter([$commonPrefix, $containsDocRoot])
            );
            $maxLen = \max($strLengths);
            $basePath = \substr($relPath, 0, $maxLen);
            $relPath = \substr($relPath, $maxLen);
            if ($containsDocRoot) {
                $basePath = \substr($basePath, \strlen($docRoot));
            }
        }
        // phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
        return array(
            'docRoot' => $containsDocRoot ? $docRoot : '',
            'relPathCommon' => $basePath,
            'relPath' => $relPath,
            'baseName' => $baseName,
        );
    }
}
