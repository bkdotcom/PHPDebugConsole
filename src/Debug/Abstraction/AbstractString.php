<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Abstraction;

use bdk\Debug;
use bdk\Debug\AbstractComponent;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Type;
use bdk\HttpMessage\Utility\ContentType;

/**
 * Abstracter:  Methods used to abstract objects
 */
class AbstractString extends AbstractComponent
{
    /** @var Abstracter */
    protected $abstracter;

    /** @var Debug */
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
     * @param array  $crateVals crate values
     *
     * @return Abstraction
     */
    public function getAbstraction($string, $typeMore = null, $crateVals = array())
    {
        $absValues = $this->absValuesInit($string, $typeMore);
        switch ($typeMore) {
            case Type::TYPE_STRING_BASE64:
                $absValues = $this->getAbsValuesBase64($absValues);
                break;
            case Type::TYPE_STRING_BINARY:
                $absValues = $this->getAbsValuesBinary($absValues);
                break;
            case Type::TYPE_STRING_JSON:
                $absValues = $this->getAbsValuesJson($absValues, $crateVals);
                break;
            case Type::TYPE_STRING_SERIALIZED:
                $absValues = $this->getAbsValuesSerialized($absValues);
                break;
            default:
                if ($absValues['maxlen'] > -1) {
                    $absValues['value'] = $this->debug->utf8->strcut($string, 0, $absValues['maxlen']);
                }
        }
        $absValues = $this->absValuesFinish($absValues);
        return new Abstraction(Type::TYPE_STRING, $absValues);
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
        $debugVals = array(
            Abstracter::NOT_INSPECTED => Type::TYPE_NOT_INSPECTED,
            Abstracter::RECURSION => Type::TYPE_RECURSION,
            Abstracter::UNDEFINED => Type::TYPE_UNDEFINED,
        );
        if (isset($debugVals[$val])) {
            return array($debugVals[$val], null);
        }
        if (\is_numeric($val) === false) {
            return array(Type::TYPE_STRING, $this->getTypeMore($val));
        }
        $typeMore = $this->abstracter->type->isTimestamp($val)
            ? Type::TYPE_TIMESTAMP
            : Type::TYPE_STRING_NUMERIC;
        return array(Type::TYPE_STRING, $typeMore);
    }

    /**
     * Remove temporary values.
     * Further trim value if "brief"
     *
     * @param array $absValues Abstraction values
     *
     * @return array
     */
    private function absValuesFinish(array $absValues)
    {
        $typeMore = $absValues['typeMore'];
        if ($absValues['brief'] && $typeMore !== Type::TYPE_STRING_BINARY) {
            $matches = array();
            $regex = '/^([^\r\n]{1,' . ($absValues['maxlen'] ?: 128) . '})/';
            \preg_match($regex, $absValues['value'], $matches);
            $absValues['value'] = $matches[1];
        }
        if ($absValues['strlen'] === \strlen($absValues['value']) && $typeMore !== Type::TYPE_STRING_BINARY) {
            // including strlen indicates that value was truncated
            //   (strlen is always provided for binary)
            $absValues['strlen'] = null;
        }
        unset($absValues['maxlen'], $absValues['valueRaw']);
        return $absValues;
    }

    /**
     * Get a string abstraction..
     *
     * Ie a string and meta info
     *
     * @param string $string   string value
     * @param string $typeMore ie, 'base64', 'json', 'numeric', etc
     *
     * @return array
     */
    protected function absValuesInit($string, $typeMore)
    {
        $maxLenCats = array(
            Type::TYPE_STRING_BASE64 => 'base64',
            Type::TYPE_STRING_BINARY => 'binary',
            Type::TYPE_STRING_JSON => 'json',
            Type::TYPE_STRING_SERIALIZED => 'other',
        );
        $maxLenCat = isset($maxLenCats[$typeMore])
            ? $maxLenCats[$typeMore]
            : 'other';
        $strlen = \strlen($string);
        $maxlen = $this->getMaxLen($maxLenCat, $strlen);
        return array(
            'brief' => $this->cfg['brief'],
            'maxlen' => $maxlen, // temporary
            'strlen' => $strlen,
            'typeMore' => $typeMore,
            'value' => $maxlen > -1
                ? \substr($string, 0, $maxlen)
                : $string,
            'valueRaw' => $string, // temporary
        );
    }

    /**
     * Get base64 abstraction
     *
     * @param array $absValues Abstraction values
     *
     * @return array
     */
    private function getAbsValuesBase64($absValues)
    {
        $absValues['valueDecoded'] = $this->cfg['brief']
            ? null
            : $this->abstracter->crate(\base64_decode($absValues['valueRaw'], true));
        return $absValues;
    }

    /**
     * Get binary abstraction
     *
     * @param array $absValues Abstraction values
     *
     * @return array
     */
    private function getAbsValuesBinary($absValues)
    {
        // is string long enough to try to determine the mime type?
        $strLenMime = $this->cfg['stringMinLen']['contentType'];
        if ($strLenMime > -1 && $absValues['strlen'] > $strLenMime) {
            $absValues['contentType'] = $this->debug->stringUtil->contentType($absValues['valueRaw']);
        }
        return $absValues;
    }

    /**
     * Get json abstraction
     *
     * @param array $absValues Abstraction values
     * @param array $crateVals crate values
     *
     * @return array
     */
    private function getAbsValuesJson($absValues, $crateVals)
    {
        if ($this->cfg['brief']) {
            $absValues['valueDecoded'] = null;
            return $absValues;
        }
        $classes = $this->debug->arrayUtil->pathGet($crateVals, 'attribs.class', array());
        if (\is_array($classes) === false) {
            $classes = \explode(' ', $classes);
        }
        if (\in_array('language-json', $classes, true) === false) {
            $abstraction = $this->debug->prettify($absValues['valueRaw'], ContentType::JSON);
            $absValues = $abstraction->getValues();
        }
        if (empty($absValues['valueDecoded'])) {
            $absValues['valueDecoded'] = $this->abstracter->crate(\json_decode($absValues['valueRaw'], true));
        }
        return $absValues;
    }

    /**
     * Get abstraction for serialized string
     *
     * @param array $absValues Abstraction values
     *
     * @return Abstraction
     */
    private function getAbsValuesSerialized($absValues)
    {
        $absValues['value'] = $absValues['maxlen'] > -1
            ? $this->debug->utf8->strcut($absValues['valueRaw'], 0, $absValues['maxlen'])
            : $absValues['valueRaw'];
        $absValues['valueDecoded'] = $this->cfg['brief']
            ? null
            : $this->abstracter->crate(
                // using unserializeSafe for good measure
                //   only safe-to-decode values should have made it this far
                $this->debug->php->unserializeSafe($absValues['valueRaw'])
            );
        return $absValues;
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
        $maxlen = \array_key_exists($cat, $stringMaxLen)
            ? $stringMaxLen[$cat]
            : $stringMaxLen['other'];
        if (\is_array($maxlen) === false) {
            return $maxlen !== null
                ? $maxlen
                : -1;
        }
        $len = -1;
        foreach ($maxlen as $breakpoint => $lenNew) {
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
     * @return string|null
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
            return $typeMore;
        }
        if ($this->debug->utf8->isUtf8($val) === false) {
            return Type::TYPE_STRING_BINARY;
        }
        $maxlen = $this->getMaxLen('other', $strLen);
        if ($maxlen > -1 && $strLen > $maxlen) {
            return Type::TYPE_STRING_LONG;
        }
        return null;
    }

    /**
     * Test if string is Base64 encoded, json, or serialized
     *
     * @param string $val string value
     *
     * @return string|null
     */
    private function getTypeStringEncoded($val)
    {
        if ($this->debug->stringUtil->isBase64Encoded($val)) {
            return Type::TYPE_STRING_BASE64;
        }
        if ($this->debug->stringUtil->isJson($val)) {
            return Type::TYPE_STRING_JSON;
        }
        if ($this->debug->stringUtil->isSerializedSafe($val)) {
            return Type::TYPE_STRING_SERIALIZED;
        }
        return null;
    }
}
