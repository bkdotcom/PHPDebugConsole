<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0b1
 */

namespace bdk\Debug\Abstraction;

use bdk\Debug;
use bdk\Debug\AbstractComponent;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\Utility\Utf8;
use bdk\Debug\Utility\Utf8Buffer;
use bdk\HttpMessage\Utility\ContentType;
use bdk\HttpMessage\Utility\ParseStr;

/**
 * Abstract string values
 * (ie base64, binary, filepath, form-urlencoded, json, serialized)
 */
class AbstractString extends AbstractComponent
{
    /** @var Abstracter */
    protected $abstracter;

    /** @var array<string,mixed> */
    protected $cfg = array(
        'detectFiles' => false,
    );

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
    public function getAbstraction($string, $typeMore = null, array $crateVals = array())
    {
        $abs = $this->absInit($string, $typeMore);

        $method = 'getAbs' . \ucfirst((string) $typeMore);
        if (\method_exists($this, $method)) {
            $abs = $this->{$method}($abs, $crateVals);
        }

        return $this->absFinish($abs);
    }

    /**
     * Get string's type.
     *
     * @param string $val string value
     *
     * @return list{Type::TYPE_STRING,Type::TYPE_*} type and typeMore
     */
    public function getType($val)
    {
        $debugVals = array(
            Abstracter::NOT_INSPECTED => Type::TYPE_NOT_INSPECTED,
            Abstracter::RECURSION => Type::TYPE_RECURSION,
            Abstracter::UNDEFINED => Type::TYPE_UNDEFINED,
        );
        return isset($debugVals[$val])
            ? [$debugVals[$val], null]
            : [Type::TYPE_STRING, $this->getTypeMore($val)];
    }

    /**
     * Remove temporary values.
     * Further trim value if "brief"
     *
     * @param Abstraction $abs Abstraction
     *
     * @return Abstraction
     */
    private function absFinish(Abstraction $abs)
    {
        $this->trimValueIfBrief($abs);

        // cleanup length info
        if ($abs['strlen'] && $abs['strlen'] === $abs['strlenValue'] && $abs['strlen'] === \strlen($abs['value'])) {
            unset($abs['strlen'], $abs['strlenValue']);
        }

        // remove temporary values
        unset($abs['maxlen'], $abs['valueRaw']);
        return $abs;
    }

    /**
     * Get a string abstraction..
     *
     * Ie a string and meta info
     *
     * @param string $string   string value
     * @param string $typeMore ie, 'base64', 'json', 'numeric', etc
     *
     * @return Abstraction
     */
    protected function absInit($string, $typeMore)
    {
        $strLen = \strlen($string);
        $maxLen = $this->getMaxLen($typeMore, $strLen);
        $value = $maxLen > -1
            ? $this->debug->utf8->strcut($string, 0, $maxLen)
            : $string;
        return new Abstraction(Type::TYPE_STRING, array(
            'brief' => $this->cfg['brief'],
            'maxlen' => $maxLen,                // temporary
            'strlen' => $strLen,                // length of untrimmed value (may be unset)
            'strlenValue' => \strlen($value),   // length of logged/captured value (may be unset unset)
            'typeMore' => $typeMore,
            'value' => $value,
            'valueRaw' => $string,              // temporary
        ));
    }

    /**
     * Get base64 abstraction
     *
     * @param Abstraction $abs Abstraction
     *
     * @return Abstraction
     */
    private function getAbsBase64(Abstraction $abs)
    {
        // decode regardless of whether brief
        $abs['valueDecoded'] = $this->abstracter->crate(\base64_decode($abs['valueRaw'], true));
        return $abs;
    }

    /**
     * Get binary abstraction
     *
     * @param Abstraction $abs Abstraction
     *
     * @return Abstraction
     */
    private function getAbsBinary(Abstraction $abs)
    {
        // is string long enough to try to determine the mime type?
        $strLenMime = $this->cfg['stringMinLen']['contentType'];
        if ($strLenMime > -1 && $abs['strlen'] > $strLenMime) {
            $abs['contentType'] = $this->debug->stringUtil->contentType($abs['valueRaw']);
        }
        $buffer = new Utf8Buffer($abs['valueRaw']);
        $info = $buffer->analyze();
        $abs['percentBinary'] = $info['percentBinary'];
        if ($abs['brief'] && !empty($abs['contentType'])) {
            // if we're brief, don't store any of the binary data
            $abs['strlenValue'] = 0;
            $abs['value'] = '';
            return $abs;
        }
        if ($info['percentBinary'] > 33) {
            // display entire value as binary / hex
            $value = \bin2hex($abs['value']);
            $abs['value'] = \trim(\chunk_split($value, 2, ' '));
            return $abs;
        }
        return $this->getAbsBinaryChunked($abs);
    }

    /**
     * "Chunk" the collected value into utf8 & non-utf8 (binary) blocks
     *
     * @param Abstraction $abs Abstraction
     *
     * @return Abstraction
     */
    private function getAbsBinaryChunked(Abstraction $abs)
    {
        $buffer = new Utf8Buffer($abs['value']);
        $info = $buffer->analyze();
        $abs['chunks'] = \array_map(static function ($chunk) {
            if ($chunk[0] === Utf8::TYPE_OTHER) {
                $str = \bin2hex($chunk[1]);
                $chunk[1] = \trim(\chunk_split($str, 2, ' '));
            }
            return $chunk;
        }, $info['blocks']);
        $abs['value'] = ''; // vs null..  string abstraction value should be string
        if (empty($abs['chunks'])) {
            unset($abs['chunks']);
        }
        return $abs;
    }

    /**
     * Get filepath abstraction
     *
     * @param Abstraction $abs Abstraction
     *
     * @return Abstraction
     */
    private function getAbsFilepath(Abstraction $abs)
    {
        $docRoot = (string) $this->debug->serverRequest->getServerParam('DOCUMENT_ROOT');
        $filePath = $abs['value'];
        $containsDocRoot = $docRoot && \strpos($filePath, $docRoot) === 0;
        $baseName = \basename($filePath);
        $pathRel = \substr($filePath, 0, 0 - \strlen($baseName));
        if ($containsDocRoot) {
            $pathRel = \substr($pathRel, \strlen($docRoot));
        }
        // phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
        return $abs->setValues(array(
            'docRoot' => $containsDocRoot,
            'pathCommon' => '',
            'pathRel' => $pathRel,
            'baseName' => $baseName,
            'type' => Type::TYPE_STRING,
            'typeMore' => Type::TYPE_STRING_FILEPATH,
        ));
    }

    /**
     * Get form-urlencoded abstraction
     *
     * @param Abstraction $abs Abstraction
     *
     * @return Abstraction
     */
    private function getAbsForm(Abstraction $abs)
    {
        if (empty($abs['valueDecoded'])) {
            $abs['valueDecoded'] = ParseStr::parse($abs['valueRaw']);
        }
        return $abs;
    }

    /**
     * Get json abstraction
     *
     * @param Abstraction $abs       Abstraction
     * @param array       $crateVals crate values
     *
     * @return Abstraction
     */
    private function getAbsJson(Abstraction $abs, array $crateVals)
    {
        if ($this->cfg['brief']) {
            $abs['valueDecoded'] = null;
            // re-encode without whitespace
            $abs['value'] = $this->debug->stringUtil->prettyJson($abs['valueRaw'], 0, 0);
            return $abs;
        }
        if (empty($crateVals['prettified'])) {
            $absTemp = $this->debug->prettify($abs['valueRaw'], ContentType::JSON);
            $absTempValues = $absTemp->getValues();
            $abs->setValues(\array_merge(array(
                'strlen' => \strlen($absTemp['value']),
                'strlenValue' => \strlen($absTemp['value']),
            ), $absTempValues));
        }
        if (empty($abs['valueDecoded'])) {
            $abs['valueDecoded'] = $this->abstracter->crate(\json_decode($abs['valueRaw'], true));
        }
        return $abs;
    }

    /**
     * Get abstraction for serialized string
     *
     * @param Abstraction $abs Abstraction
     *
     * @return Abstraction
     */
    private function getAbsSerialized(Abstraction $abs)
    {
        $abs['valueDecoded'] = $this->cfg['brief']
            ? null
            : $this->abstracter->crate(
                // using unserializeSafe for good measure
                //   only safe-to-decode values should have made it this far
                $this->debug->php->unserializeSafe($abs['valueRaw'])
            );
        return $abs;
    }

    /**
     * Get maximum length that should be collected for given type and strlen
     *
     * @param string $cat    category (ie base64, binary, other)
     * @param int    $strlen string length
     *
     * @return int max length value (-1 for no limit)
     */
    private function getMaxLen($cat, $strlen)
    {
        $stringMaxLen = $this->cfg['brief']
            ? $this->cfg['stringMaxLenBrief']
            : $this->cfg['stringMaxLen'];
        $maxLen = \array_key_exists($cat, $stringMaxLen)
            ? $stringMaxLen[$cat]
            : $stringMaxLen['other'];

        if (\is_array($maxLen)) {
            $maxLen = $this->getBreakpointBasedMaxLen($maxLen, $strlen);
        }
        return $maxLen !== null
            ? $maxLen
            : -1;
    }

    /**
     * Calculate max length based on breakpoints
     *
     * @param array $breakpoints array of breakpoint => length values
     * @param int   $strlen      string length to check against breakpoints
     *
     * @return int max length value (-1 for no limit)
     */
    private function getBreakpointBasedMaxLen(array $breakpoints, $strlen)
    {
        $len = -1;
        foreach ($breakpoints as $breakpoint => $lenNew) {
            if ($breakpoint > $strlen) {
                break;
            }
            $len = $lenNew;
        }
        return $len;
    }

    /**
     * Check for "encoded", binary, & large/long
     *
     * @param string $val string value
     *
     * @return Type::TYPE_STRING_*|null
     */
    private function getTypeMore($val)
    {
        $typeMore = \is_numeric($val)
            ? $this->getTypeMoreNumeric($val)
            : $this->getTypeMoreEncoded($val);
        if ($typeMore) {
            return $typeMore;
        }
        if ($this->debug->utf8->isUtf8($val) === false) {
            return Type::TYPE_STRING_BINARY;
        }
        if ($this->cfg['detectFiles'] && $this->debug->utility->isFile($val)) {
            return Type::TYPE_STRING_FILEPATH;
        }
        $strLen = \strlen($val);
        $maxlen = $this->getMaxLen('other', $strLen);
        return $maxlen > -1 && $strLen > $maxlen
            ? Type::TYPE_STRING_LONG
            : null;
    }

    /**
     * Test if string is Base64 encoded, json, or serialized
     *
     * @param string $val string value
     *
     * @return Type::TYPE_STRING_*|null
     */
    private function getTypeMoreEncoded($val)
    {
        $minLen = $this->cfg['stringMinLen']['encoded'];
        if ($minLen < 0 || \strlen($val) < $minLen) {
            return null; // not long enough to test
        }
        if ($this->debug->stringUtil->isBase64Encoded($val)) {
            return Type::TYPE_STRING_BASE64;
        }
        if ($this->debug->stringUtil->isJson($val)) {
            return Type::TYPE_STRING_JSON;
        }
        return $this->debug->stringUtil->isSerializedSafe($val)
            ? Type::TYPE_STRING_SERIALIZED
            : null;
    }

    /**
     * Determine if value is timestamp or plain numeric
     *
     * @param string $val numeric string value
     *
     * @return string
     */
    private function getTypeMoreNumeric($val)
    {
        return $this->abstracter->type->isTimestamp($val)
            ? Type::TYPE_TIMESTAMP
            : Type::TYPE_STRING_NUMERIC;
    }

    /**
     * Trim the abstraction value if in brief mode and not binary
     *
     * @param Abstraction $abs Abstraction to modify
     *
     * @return void
     */
    private function trimValueIfBrief(Abstraction $abs)
    {
        if (!$abs['brief'] || $abs['typeMore'] === Type::TYPE_STRING_BINARY) {
            return;
        }
        $maxLen = $abs['maxlen'] > -1 ? $abs['maxlen'] : 128;
        $matches = [];
        $regex = '/^([^\r\n]{1,' . $maxLen . '})/';
        \preg_match($regex, $abs['value'], $matches);
        $abs['value'] = $matches
            ? $matches[1]
            : \substr($abs['value'], 0, $maxLen);
        $abs['strlenValue'] = \strlen($abs['value']);
    }
}
