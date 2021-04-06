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

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Dump\Html;

/**
 * Output object as HTML
 */
class HtmlString
{

    public $detectFiles = false;

    protected $debug;
    protected $html;

	/**
     * Constructor
     *
     * @param Html $html Dump\Html instance
     */
	public function __construct(Html $html)
	{
		$this->debug = $html->debug;
        $this->html = $html;
	}

    /**
     * Dump string
     *
     * @param string      $val string value
     * @param Abstraction $abs (optional) full abstraction
     *
     * @return string
     */
    public function dump($val, Abstraction $abs = null)
    {
        if (\is_numeric($val)) {
            $this->html->checkTimestamp($val);
        }
        if ($this->detectFiles && $this->debug->utility->isFile($val)) {
            $this->html->setDumpOpt('attribs.data-file', true);
        }
        if (!$this->html->getDumpOpt('addQuotes')) {
            $this->html->setDumpOpt('attribs.class.__push__', 'no-quotes');
        }
        if ($abs) {
            return $this->dumpAbs($abs);
        }
        return $this->dumpHelper($val);
    }

    /**
     * Dump with min markup
     *
     * @param mixed $val  string value
     * @param array $opts $options passed to dump
     *
     * @return string
     */
    public function dumpAsSubstitution($val, $opts)
    {
        if ($val instanceof Abstraction) {
            if ($val['typeMore'] === Abstracter::TYPE_STRING_BINARY) {
                if (!$val['value']) {
                    return 'Binary data not collected';
                }
                $str = $this->debug->utf8->dump($val['value']);
                $diff = $val['strlen']
                    ? $val['strlen'] - \strlen($val['value'])
                    : 0;
                if ($diff) {
                    $str .= '[' . $diff . ' more bytes (not logged)]';
                }
                return $str;
            }
        }
        // we do NOT wrap in <span>...  log('<a href="%s">link</a>', $url);
        $opts['tagName'] = null;
        return $this->html->dump($val, $opts);
    }

    /**
     * Add whitespace markup
     *
     * @param string $str string which to add whitespace html markup
     *
     * @return string
     */
    public function visualWhiteSpace($str)
    {
        // display \r, \n, & \t
        $str = \preg_replace_callback('/(\r\n|\r|\n)/', function ($matches) {
            $search = array("\r","\n");
            $replace = array('<span class="ws_r"></span>','<span class="ws_n"></span>' . "\n");
            return \str_replace($search, $replace, $matches[1]);
        }, $str);
        $str = \str_replace("\t", '<span class="ws_t">' . "\t" . '</span>', $str);
        return $str;
    }

    /**
     * Dump string encapsulated by Abstraction
     *
     * @param Abstraction $abs String Abstraction
     *
     * @return string
     */
    private function dumpAbs(Abstraction $abs)
    {
        if ($abs['typeMore'] === Abstracter::TYPE_STRING_CLASSNAME) {
            $val = $this->html->markupIdentifier($abs['value']);
            $parsed = $this->debug->html->parseTag($val);
            $attribs = $this->html->getDumpOpt('attribs');
            $attribs = $this->debug->arrayUtil->mergeDeep($attribs, $parsed['attribs']);
            $this->html->setDumpOpt('attribs', $attribs);
            return $parsed['innerhtml'];
        }
        $strlenDumped = \strlen($abs['value']);
        $val = $this->dumpHelper($abs['value']);
        if (
            \in_array($abs['typeMore'], array(
                Abstracter::TYPE_STRING_BASE64,
                Abstracter::TYPE_STRING_JSON,
                Abstracter::TYPE_STRING_SERIALIZED
            ))
        ) {
            return $this->dumpEncoded($val, $abs);
        }
        if ($abs['typeMore'] === Abstracter::TYPE_STRING_BINARY) {
            return $this->dumpBinary($val, $abs);
        }
        if ($abs['strlen']) {
            $val .= '<span class="maxlen">&hellip; ' . ($abs['strlen'] - $strlenDumped) . ' more bytes (not logged)</span>';
        }
        if ($abs['prettifiedTag']) {
            $template = $this->debug->html->buildTag(
                'span',
                array(
                    'class' => 'value-container',
                    'data-type' => $abs['type'],
                ),
                '<span class="prettified">(prettified)</span> {val}'
            );
            $this->html->setDumpOpt('template', $template);
        }
        return $val;
    }

    /**
     * Dump binary string
     *
     * @param string      $val dumped value
     * @param Abstraction $abs String Abstraction
     *
     * @return string
     */
    private function dumpBinary($val, Abstraction $abs)
    {
        $lis = array();
        if ($abs['contentType']) {
            $lis[] = '<li>mime type = <span class="t_string">' . $abs['contentType'] . '</span></li>';
        }
        $lis[] = '<li>size = <span class="t_int">' . $abs['strlen'] . '</span></li>';
        $lis[] = $val
            ? '<li class="t_string">{val}</li>'
            : '<li>Binary data not collected</li>';
        $template = '<span class="t_type">binary string</span>' . "\n"
            . $this->debug->html->buildTag(
                'ul',
                array(
                    'class' => array('list-unstyled', 'value-container'),
                    'data-type' => $abs['type'],
                ),
                "\n" . \implode("\n", $lis) . "\n"
            );
        if ($this->html->getDumpOpt('tagName') === 'td') {
            // wrap with td without adding class="binary t_string"
            $template = '<td>' . $template . '</td>';
        }
        $this->html->setDumpOpt('tagName', null);
        $this->html->setDumpOpt('template', $template);
        $strLenDiff = $abs['strlen'] - \strlen($abs['value']);
        if ($val && $strLenDiff) {
            $val .= '<span class="maxlen">&hellip; ' . $strLenDiff . ' more bytes (not logged)</span>';
        }
        return $val;
    }

    /**
     * Dump encoded string (base64, json, serialized)
     *
     * @param string      $val raw value dumped
     * @param Abstraction $abs (optional) full abstraction
     *
     * @return string
     */
    private function dumpEncoded($val, Abstraction $abs)
    {
        $attribs = $this->html->getDumpOpt('attribs');
        $attribs['class'][] = 'no-quotes';
        $attribs['class'][] = 't_' . $abs['type'];
        $typeMore = $abs['typeMore'];
        $vals = array(
            'labelDecoded' => 'Decoded',
            'labelRaw' => 'Raw',
            'valDecoded' => $this->html->dump($abs['valueDecoded']),
            'valRaw' => $this->debug->html->buildTag('span', $attribs, $val),
        );
        if ($typeMore === Abstracter::TYPE_STRING_BASE64) {
            $vals['labelDecoded'] = 'decoded';
            $vals['labelRaw'] = 'base64';
            if ($abs['strlen']) {
                $vals['valRaw'] .= '<span class="maxlen">&hellip; ' . ($abs['strlen'] - \strlen($abs['value'])) . ' more bytes (not logged)</span>';
            }
        } elseif ($typeMore === Abstracter::TYPE_STRING_JSON) {
            $vals['labelDecoded'] = 'decoded';
            $vals['labelRaw'] = 'json';
            if ($abs['prettified'] || $abs['strlen']) {
                $abs['typeMore'] = null; // unset typeMore to prevent loop
                $vals['valRaw'] = $this->html->dump($abs);
            }
        } elseif ($typeMore === Abstracter::TYPE_STRING_SERIALIZED) {
            $vals['labelDecoded'] = 'unserialized';
            $vals['labelRaw'] = 'serialized';
        }
        $val = $this->debug->html->buildTag(
            $this->html->getDumpOpt('tagName'),
            array(
                'class' => 'string-encoded tabs-container',
                'data-type' => $typeMore,
            ),
            "\n"
            . '<nav role="tablist">'
                . '<a class="nav-link" data-target=".string-raw" data-toggle="tab" role="tab">{labelRaw}</a>'
                . '<a class="active nav-link" data-target=".string-decoded" data-toggle="tab" role="tab">{labelDecoded}</a>'
            . '</nav>' . "\n"
            . '<div class="string-raw tab-pane" role="tabpanel">'
                . '{valRaw}'
            . '</div>' . "\n"
            . '<div class="active string-decoded tab-pane" role="tabpanel">'
                . '{valDecoded}'
            . '</div>' . "\n"
        );
        $this->html->setDumpOpt('tagName', null);
        return $this->debug->utility->strInterpolate($val, $vals);
    }

    /**
     * Sanitize and dump string.
     *
     * @param string $val string value to dump
     *
     * @return string
     */
    private function dumpHelper($val)
    {
        $opts = $this->html->getDumpOpt();
        $val = $this->debug->utf8->dump($val, array(
            'sanitizeNonBinary' => $opts['sanitize'],
            'useHtml' => true,
        ));
        if ($opts['visualWhiteSpace']) {
            $val = $this->visualWhiteSpace($val);
        }
        return $val;
    }
}
