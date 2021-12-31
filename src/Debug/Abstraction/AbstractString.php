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

namespace bdk\Debug\Abstraction;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Component;
use finfo;

/**
 * Abstracter:  Methods used to abstract objects
 */
class AbstractString extends Component
{
    protected $abstracter;
    protected $debug;

    /**
     * Constructor
     *
     * @param Abstracter $abstracter abstracter instance
     */
    public function __construct(Abstracter $abstracter)
    {
        $this->abstracter = $abstracter;
        $this->debug = $abstracter->debug;
    }

    /**
     * Get a string abstraction..
     *
     * Ie a string and meta info
     *
     * @param string $string    string value
     * @param string $typeMore  ie, 'base64', 'json', 'numeric', etc
     * @param array  $crateVals create values
     *
     * @return Abstraction
     */
    public function getAbstraction($string, $typeMore, $crateVals)
    {
        if ($typeMore === Abstracter::TYPE_STRING_BASE64) {
            return $this->getAbstractionBase64($string);
        }
        if ($typeMore === Abstracter::TYPE_STRING_BINARY) {
            return $this->getAbstractionBinary($string);
        }
        if ($typeMore === Abstracter::TYPE_STRING_JSON) {
            return $this->getAbstractionJson($string, $crateVals);
        }
        $strLen = \strlen($string);
        $maxLen = $this->getMaxLen('other', $strLen);
        $absValues = array(
            'strlen' => $maxLen > -1 && $strLen > $maxLen
                ? $strLen
                : null,
            'typeMore' => $typeMore,
            'value' => $this->debug->utf8->strcut($string, 0, $maxLen),
        );
        if ($typeMore === Abstracter::TYPE_STRING_SERIALIZED) {
            $absValues['valueDecoded'] = $this->abstracter->crate(\unserialize($string));
        }
        return new Abstraction(Abstracter::TYPE_STRING, $absValues);
    }

    /**
     * Get string's type.
     *
     * @param string $val string value
     *
     * @return array type and typeMore
     */
    public function getType($val)
    {
        if ($val === Abstracter::NOT_INSPECTED) {
            return array(Abstracter::TYPE_NOT_INSPECTED, null);   // not a native php type!
        }
        if ($val === Abstracter::RECURSION) {
            return array(Abstracter::TYPE_RECURSION, null);       // not a native php type!
        }
        if ($val === Abstracter::UNDEFINED) {
            return array(Abstracter::TYPE_UNDEFINED, null);       // not a native php type!
        }
        if (\is_numeric($val)) {
            return array(Abstracter::TYPE_STRING, Abstracter::TYPE_STRING_NUMERIC);
        }
        return $this->getTypeMore($val);
    }

    /**
     * Get base64 abstraction
     *
     * @param string $string base64 encoded string
     *
     * @return Abstraction
     */
    private function getAbstractionBase64($string)
    {
        $strLen = \strlen($string);
        $maxLen = $this->getMaxLen('base64', $strLen);
        $absValues = array(
            'strlen' => $maxLen > -1 && $strLen > $maxLen
                ? $strLen
                : null,
            'typeMore' => Abstracter::TYPE_STRING_BASE64,
            'value' => $maxLen > -1
                ? \substr($string, 0, $maxLen)
                : $string,
            'valueDecoded' => $this->abstracter->crate(\base64_decode($string)),
        );
        return new Abstraction(Abstracter::TYPE_STRING, $absValues);
    }

    /**
     * Get binary abstraction
     *
     * @param string $string binary string
     *
     * @return Abstraction
     */
    private function getAbstractionBinary($string)
    {
        $strLen = \strlen($string);
        $maxLen = $this->getMaxLen('binary', $strLen);
        $absValues = array(
            'strlen' => $strLen, // store length regardless if we're truncating
            'typeMore' => Abstracter::TYPE_STRING_BINARY,
            'value' => $maxLen > -1
                ? \substr($string, 0, $maxLen)
                : $string,
        );
        $strLenMime = $this->cfg['stringMinLen']['contentType'];
        if ($strLenMime > -1 && $strLen > $strLenMime) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $absValues['contentType'] = $finfo->buffer($string);
        }
        return new Abstraction(Abstracter::TYPE_STRING, $absValues);
    }

    /**
     * Get json abstraction
     *
     * @param string $string    json
     * @param array  $crateVals crate values
     *
     * @return Abstraction
     */
    private function getAbstractionJson($string, $crateVals)
    {
        $strLen = \strlen($string);
        $maxLen = $this->getMaxLen('json', $strLen);
        $absValues = array(
            'strlen' => $maxLen > -1 && $strLen > $maxLen
                ? $strLen
                : null,
            'typeMore' => Abstracter::TYPE_STRING_JSON,
            'valueDecoded' => array(),
            'value' => $string,
        );
        $classes = $this->debug->arrayUtil->pathGet($crateVals, 'attribs.class', array());
        if (!\is_array($classes)) {
            $classes = \explode(' ', $classes);
        }
        if (!\in_array('language-json', $classes)) {
            $abstraction = $this->debug->prettify($string, 'application/json');
            $absValues = $abstraction->getValues();
        }
        if (empty($absValues['valueDecoded'])) {
            $absValues['valueDecoded'] = $this->abstracter->crate(\json_decode($string, true));
        }
        return new Abstraction(Abstracter::TYPE_STRING, $absValues);
    }

    /**
     * Get maximum length that should be collected for given type and strlen
     *
     * @param string $cat    category (ie base64, binary, other)
     * @param int    $strlen string length
     *
     * @return int -1 for no limit
     */
    private function getMaxLen($cat, $strlen)
    {
        $stringMaxLen = $this->cfg['stringMaxLen'];
        $maxLen = \array_key_exists($cat, $stringMaxLen)
            ? $stringMaxLen[$cat]
            : $stringMaxLen['other'];
        if (!\is_array($maxLen)) {
            return $maxLen !== null
                ? $maxLen
                : -1;
        }
        $len = -1;
        foreach ($maxLen as $breakpoint => $lenNew) {
            if ($breakpoint > $strlen) {
                break;
            }
            $len = $lenNew;
        }
        return $len !== null
            ? $len
            : -1;
    }

    /**
     * Check for "encoded", binary, & large/long
     *
     * @param string $val string value
     *
     * @return array type and typeMore
     */
    private function getTypeMore($val)
    {
        $strLen = \strlen($val);
        $strLenEncoded = $this->cfg['stringMinLen']['encoded'];
        $typeMore = null;
        if ($strLenEncoded > -1 && $strLen >= $strLenEncoded) {
            $typeMore = $this->getTypeStringEncoded($val);
        }
        if ($typeMore) {
            return array(Abstracter::TYPE_STRING, $typeMore);
        }
        if ($this->debug->utf8->isUtf8($val) === false) {
            $typeMore = Abstracter::TYPE_STRING_BINARY;
            return array(Abstracter::TYPE_STRING, $typeMore);
        }
        $maxLen = $this->getMaxLen('other', $strLen);
        if ($maxLen > -1 && $strLen > $maxLen) {
            $typeMore = Abstracter::TYPE_STRING_LONG;
        }
        return array(Abstracter::TYPE_STRING, $typeMore);
    }

    /**
     * Test if string is Base64 enncoded, json, or serialized
     *
     * @param string $val string value
     *
     * @return string|null
     */
    private function getTypeStringEncoded($val)
    {
        if ($this->debug->stringUtil->isBase64Encoded($val)) {
            return Abstracter::TYPE_STRING_BASE64;
        }
        if ($this->debug->stringUtil->isJson($val)) {
            return Abstracter::TYPE_STRING_JSON;
        }
        if ($this->debug->stringUtil->isSerializedSafe($val)) {
            return Abstracter::TYPE_STRING_SERIALIZED;
        }
        return null;
    }
}
