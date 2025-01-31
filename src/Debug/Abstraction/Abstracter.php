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

namespace bdk\Debug\Abstraction;

use bdk\Debug;
use bdk\Debug\AbstractComponent;
use bdk\Debug\Abstraction\AbstractArray;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Abstraction\AbstractString;
use bdk\Debug\Abstraction\Type;

/**
 * Store array/object/resource info
 *
 * @property-read AbstractArray $abstractArray
 * @property-read AbstractObject $abstractObject
 * @property-read AbstractString $abstractString
 * @property-read Debug $debug
 * @property-read Type $type
 */
class Abstracter extends AbstractComponent
{
    const ABSTRACTION = "\x00debug\x00";
    const NOT_INSPECTED = "\x00notInspected\x00";
    const RECURSION = "\x00recursion\x00";  // ie, array recursion
    const UNDEFINED = "\x00undefined\x00";

    /** @var AbstractArray */
    protected $abstractArray;

    /** @var AbstractObject */
    protected $abstractObject;

    /** @var AbstractString */
    protected $abstractString;

    /** @var Debug */
    protected $debug;

    /** @var Type */
    protected $type;

    /** @var list<string> */
    protected $readOnly = [
        'abstractArray',
        'abstractObject',
        'abstractString',
        'debug',
        'type',
    ];

    /** @var array>string,mixed> */
    protected $cfg = array(
        'brief' => false, // collect & output less details
                          //    see also AbstractObject::$cfgFlags where each key
                          //    can be set to true/false as a cfg value here
        'fullyQualifyPhpDocType' => false,
        'interfacesCollapse' => [
            'ArrayAccess',
            'BackedEnum',
            'Countable',
            'Iterator',
            'IteratorAggregate',
            'UnitEnum',
        ],
        'maxDepth' => 0, // value < 1 : no max-depth
        'objectSectionOrder' => [
            'attributes',
            'extends',
            'implements',
            'constants',
            'cases',
            'properties',
            'methods',
            'phpDoc',
        ],
        'objectsExclude' => [
            // __NAMESPACE__ added in constructor
            'DOMNode',
        ],
        'objectSort' => 'inheritance visibility name',
        'objectsWhitelist' => null,     // will be used if array
        'stringMaxLen' => array(
            'base64' => 156, // 2 lines of chunk_split'ed
            'binary' => array(
                0 => 128,
                128 => 0, // if over 128 bytes don't capture / store
            ),
            'other' => 8192,
        ),
        'stringMaxLenBrief' => array(
            'other' => 128,
        ),
        'stringMinLen' => array(
            'contentType' => 256, // try to determine content-type of binary string
            'encoded' => 16, // test if base64, json, or serialized (-1 = don't check)
        ),
        'useDebugInfo' => true,
    );

    /** @var array */
    private $crateVals = array();

    /**
     * Constructor
     *
     * @param Debug               $debug debug instance
     * @param array<string,mixed> $cfg   config options
     */
    public function __construct(Debug $debug, $cfg = array())
    {
        $this->debug = $debug;  // we need debug instance so we can bubble events up channels
        $this->cfg['objectsExclude'][] = __NAMESPACE__;
        $this->abstractArray = new AbstractArray($this);
        $this->abstractObject = new AbstractObject($this);
        $this->abstractString = new AbstractString($this);
        $this->type = new Type($this);
        $this->cfg = \array_merge(
            $this->cfg,
            \array_fill_keys(
                \array_keys(AbstractObject::$cfgFlags),
                true
            ),
            array(
                'brief' => false,
                'propVirtualValueCollect' => false,
            )
        );
        $this->setCfg(\array_merge($this->cfg, $cfg));
    }

    /**
     * "crate" value for logging
     *
     * Conditionally calls getAbstraction
     *
     * @param mixed  $mixed  value to crate
     * @param string $method Method doing the crating
     * @param array  $hist   (@internal) array/object history (used to test for recursion)
     *
     * @return mixed
     */
    public function crate($mixed, $method = null, $hist = array())
    {
        $typeInfo = self::needsAbstraction($mixed);
        if (!$typeInfo) {
            return $mixed;
        }
        return $typeInfo === [Type::TYPE_ARRAY, Type::TYPE_RAW]
            ? $this->abstractArray->crate($mixed, $method, $hist)
            : $this->getAbstraction($mixed, $method, $typeInfo, $hist);
    }

    /**
     * Wrap value in Abstraction
     *
     * @param mixed $mixed  value to abstract
     * @param array $values additional values to set
     *
     * @return Abstraction
     */
    public function crateWithVals($mixed, $values = array())
    {
        /*
            Note: this->crateValues is the raw values passed to this method
               the values may end up being processed in Abstraction::onSet
               ie, converting attribs.class to an array
        */
        $this->crateVals = $values;
        // make sure any supplied typeInfo is applied during the abstraction
        $typeInfo = array(
            'type' => null,
            'typeMore' => null,
        );
        $typeInfo = \array_merge($typeInfo, \array_intersect_key($values, $typeInfo));
        $typeInfo = $typeInfo['type']
            ? \array_values($typeInfo)
            : array();
        unset($values['type']);
        $abs = $this->getAbstraction($mixed, __FUNCTION__, $typeInfo);
        foreach ($values as $k => $v) {
            $abs[$k] = $v;
        }
        $this->crateVals = array();
        return $abs;
    }

    /**
     * Store a "snapshot" of arrays, objects, & resources (or any other value)
     * along with other meta info/options for the value
     *
     * Remove any reference to an "external" variable
     * Deep cloning objects = problematic
     *   + some objects aren't able to be cloned & throw fatal error
     *   + difficult to maintain circular references
     * Instead of storing objects in log, store "Abstraction" which containing
     *     type, methods, & properties
     *
     * @param mixed  $val      value to "abstract"
     * @param string $method   Method requesting abstraction
     * @param array  $typeInfo (@internal) array specifying value's type & "typeMore"
     * @param array  $hist     (@internal) array/object history (used to test for recursion)
     *
     * @return Abstraction
     *
     * @internal
     *
     * @SuppressWarnings(PHPMD.DevelopmentCodeFragment)
     */
    public function getAbstraction($val, $method = null, $typeInfo = array(), $hist = array())
    {
        list($type, $typeMore) = $typeInfo ?: $this->type->getType($val);
        switch ($type) {
            case Type::TYPE_ARRAY:
                return $this->abstractArray->getAbstraction($val, $method, $hist);
            case Type::TYPE_CALLABLE:
                return $this->abstractArray->getCallableAbstraction($val);
            case Type::TYPE_FLOAT:
                return $this->getAbstractionFloat($val, $typeMore);
            case Type::TYPE_OBJECT:
                return $val instanceof \SensitiveParameterValue
                    ? $this->abstractString->getAbstraction(\call_user_func($this->debug->getPlugin('redaction')->getCfg('redactReplace'), 'redacted'))
                    : $this->abstractObject->getAbstraction($val, $method, $hist);
            case Type::TYPE_RESOURCE:
                return new Abstraction($type, array(
                    'value' => \print_r($val, true) . ': ' . \get_resource_type($val),
                ));
            case Type::TYPE_STRING:
                return $this->abstractString->getAbstraction($val, $typeMore, $this->crateVals);
            default:
                return new Abstraction($type, array(
                    'brief' => $this->cfg['brief'],
                    'typeMore' => $typeMore,
                    'value' => $val,
                ));
        }
    }

    /**
     * Is the passed value an abstraction
     *
     * @param mixed  $mixed value to check
     * @param string $type  additionally check type
     *
     * @return bool
     *
     * @psalm-assert-if-true Abstraction $mixed
     */
    public static function isAbstraction($mixed, $type = null)
    {
        $isAbstraction = $mixed instanceof Abstraction;
        if (!$isAbstraction) {
            return false;
        }
        return $type
            ? $mixed['type'] === $type
            : true;
    }

    /**
     * Is the passed value an array, object, or resource that needs abstracted?
     *
     * @param mixed $val value to check
     *
     * @return list{Type::TYPE_*,Type::TYPE_*}|false array(type, typeMore) or false
     */
    public function needsAbstraction($val)
    {
        if ($val instanceof Abstraction) {
            return false;
        }
        list($type, $typeMore) = $this->type->getType($val);
        if ($type === Type::TYPE_BOOL) {
            return false;
        }
        if (\in_array($typeMore, [Type::TYPE_ABSTRACTION, Type::TYPE_STRING_NUMERIC], true)) {
            return false;
        }
        return $typeMore
            ? [$type, $typeMore]
            : false;
    }

    /**
     * Abstract a float
     *
     * This is done to avoid having NAN & INF values.. which can't be json encoded
     *
     * @param float       $val      float value
     * @param string|null $typeMore (optional) TYPE_FLOAT_INF or TYPE_FLOAT_NAN
     *
     * @return Abstraction
     */
    private function getAbstractionFloat($val, $typeMore)
    {
        if ($typeMore === Type::TYPE_FLOAT_INF) {
            $val = Type::TYPE_FLOAT_INF;
        } elseif ($typeMore === Type::TYPE_FLOAT_NAN) {
            $val = Type::TYPE_FLOAT_NAN;
        }
        return new Abstraction(Type::TYPE_FLOAT, array(
            'typeMore' => $typeMore,
            'value' => $val,
        ));
    }

    /**
     * {@inheritDoc}
     */
    protected function postSetCfg($cfg = array(), $prev = array())
    {
        $debugClass = \get_class($this->debug);
        if (!\array_intersect(['*', $debugClass], $this->cfg['objectsExclude'])) {
            $this->cfg['objectsExclude'][] = $debugClass;
        }
        if (isset($cfg['stringMaxLen'])) {
            if (\is_array($cfg['stringMaxLen']) === false) {
                $cfg['stringMaxLen'] = array(
                    'other' => $cfg['stringMaxLen'],
                );
            }
            $this->cfg['stringMaxLen'] = \array_merge($prev['stringMaxLen'], $cfg['stringMaxLen']);
        }
        if (isset($cfg['stringMinLen'])) {
            $this->cfg['stringMinLen'] = \array_merge($prev['stringMinLen'], $cfg['stringMinLen']);
        }
        if (isset($cfg['objectSectionOrder'])) {
            $oso = \array_intersect($cfg['objectSectionOrder'], $prev['objectSectionOrder']);
            $oso = \array_merge($oso, $prev['objectSectionOrder']);
            $oso = \array_unique($oso);
            $this->cfg['objectSectionOrder'] = $oso;
        }
        $this->setCfgDependencies(\array_intersect_key($this->cfg, $cfg));
    }

    /**
     * Pass relevant config updates to AbstractObject & AbstractString
     *
     * @param array $cfg Updated config values
     *
     * @return void
     */
    private function setCfgDependencies($cfg)
    {
        $keysArr = [
            'maxDepth',
        ];
        $keysStr = [
            'stringMaxLen',
            'stringMaxLenBrief',
            'stringMinLen',
        ];
        $arrCfg = \array_intersect_key($cfg, \array_flip($keysArr));
        if ($arrCfg) {
            $this->abstractArray->setCfg($arrCfg);
        }
        $objCfg = \array_diff_key($cfg, \array_flip($keysStr));
        if ($objCfg) {
            $this->abstractObject->setCfg($objCfg);
        }
        $strCfg = \array_intersect_key($cfg, \array_flip(['brief']) + \array_flip($keysStr));
        if ($strCfg) {
            $this->abstractString->setCfg($strCfg);
        }
    }
}
