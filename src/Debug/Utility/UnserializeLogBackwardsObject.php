<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2026 Brad Kent
 * @since     3.6
 */

namespace bdk\Debug\Utility;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Abstraction\Object\Abstraction as ObjectAbstraction;
use bdk\Debug\Abstraction\Object\Definition;
use bdk\Debug\Abstraction\Object\MethodParams;
use bdk\Debug\Abstraction\Object\Methods;
use bdk\Debug\Abstraction\Object\Properties;
use bdk\Debug\Utility\ArrayUtil;
use bdk\Debug\Utility\UnserializeLogBackwards;
use bdk\PubSub\ValueStore;

/**
 * "Normalize" log entries and values
 */
class UnserializeLogBackwardsObject
{
    /** @var Debug */
    protected static $debug;

    /**
     * Update class definition
     *
     * @param ValueStore $def   Class definition to update
     * @param Debug      $debug Debug instance
     *
     * @return ValueStore
     */
    public static function updateClassDefinition(ValueStore $def, Debug $debug)
    {
        self::$debug = $debug;
        $values = self::updateClassDefinitionValues($def);
        if ($values['className'] !== "\x00default\x00") {
            $valueStoreDefault = self::$debug->abstracter->abstractObject->definition->getValueStoreDefault();
            $def = new ObjectAbstraction($valueStoreDefault, $values);
            self::$debug->abstracter->abstractObject->definition->markAsUsed($valueStoreDefault);
            return $def;
        }
        return $def->setValues($values);
    }

    /**
     * Update object abstraction
     *
     * @param Abstraction $abs   Object Abstraction
     * @param Debug       $debug Debug instance
     *
     * @return AbstractObject
     */
    public static function updateObjectAbstraction(Abstraction $abs, Debug $debug)
    {
        self::$debug = $debug;
        $values = $abs->getValues();
        $values = self::updateObjectAbstractionValues($values);

        $classDefinition = self::$debug->data->get('classDefinitions.' . $values['className']);
        if ($classDefinition === null) {
            $valuesDef = \array_diff_key($values, \array_flip(['debugMethod']));
            $classDefinition = new ValueStore($valuesDef);
            $classDefinition = self::updateClassDefinition($classDefinition, self::$debug);
            self::$debug->data->set('classDefinitions.' . $values['className'], $classDefinition);
        }
        $values = ArrayUtil::diffDeep($values, $classDefinition->getValues());
        return new ObjectAbstraction($classDefinition, $values);
    }

    /**
     * Get updated class definition values
     *
     * @param ValueStore $def Class definition
     *
     * @return array
     */
    private static function updateClassDefinitionValues(ValueStore $def)
    {
        $values = $def->getValues();
        $values = \array_filter($values, static function ($val) {
            return $val !== null;
        });
        if ($values['className'] === "\x00default\x00") {
            $values['cfgFlags'] = self::$debug->abstracter->abstractObject->definition->getValueStoreDefault()->getValue('cfgFlags');
            $values['scopeClass'] = null;
        }
        $values['__isUsed'] = true;
        $values['methods'] = self::updateObjectMethods($values['methods'], true);
        $values['phpDoc'] = self::updatePhpDoc($values['phpDoc']);
        $values['properties'] = self::updateObjectProperties($values['properties'], true);
        if (
            isset($values['scopeClass'])
            && \in_array($values['scopeClass'], [
                '',
                'bdk\\Debug',
                'bdk\\Debug\\Abstraction\\AbstractObject',
            ], true)
        ) {
            $values['scopeClass'] = null;
        }
        unset($values['debugMethod']);
        unset($values['traverseValues']);
        return AbstractObject::buildValues(Definition::buildValues($values));
    }

    /**
     * Update object abstraction values
     *
     * @param array $values Values to update
     *
     * @return array
     */
    private static function updateObjectAbstractionValues(array $values)
    {
        if (isset($values['collectMethods'])) {
            if ($values['collectMethods'] === false) {
                $values['cfgFlags'] &= ~AbstractObject::METHOD_COLLECT;
            }
            unset($values['collectMethods']);
        }

        $values = \array_filter($values, static function ($val) {
            return $val !== null;
        });

        if (isset($values['scopeClass']) && \in_array($values['scopeClass'], ['', 'bdk\\Debug\\Abstraction\\AbstractObject'], true)) {
            // prior to 3.4 scopeClass settled on AbstractObject
            $values['scopeClass'] = null;
        }

        $values['methods'] = self::updateObjectMethods($values['methods']);
        $values['phpDoc'] = self::updatePhpDoc($values['phpDoc']);
        $values['properties'] = self::updateObjectProperties($values['properties']);
        $values = AbstractObject::buildValues($values);

        unset($values['debugMethod']);
        unset($values['sort']);
        unset($values['traverseValues']);

        return $values;
    }

    /**
     * Convert old object inheritance keys to current names
     *
     * @param array $values Object abstraction values
     *
     * @return array
     */
    private static function updateObjectInheritance(array $values)
    {
        if (\array_key_exists('inheritedFrom', $values)) {
            $values['declaredLast'] = $values['inheritedFrom'];
            unset($values['inheritedFrom']);
        }
        if (\array_key_exists('overrides', $values)) {
            $values['declaredPrev'] = $values['overrides'];
            unset($values['overrides']);
        }
        if (\array_key_exists('originallyDeclared', $values)) {
            $values['declaredOrig'] = $values['originallyDeclared'];
            unset($values['originallyDeclared']);
        }
        return $values;
    }

    /**
     * Update object method info
     *
     * @param array $methods      Abstracted methods
     * @param bool  $isDefinition Whether the method info is for a class definition (vs method info on an object instance)
     *
     * @return array
     */
    private static function updateObjectMethods(array $methods, $isDefinition = false)
    {
        return ArrayUtil::mapWithKeys(static function (array $info, $methodName) use ($isDefinition) {
            return self::updateObjectMethod($info, $methodName, $isDefinition);
        }, $methods);
    }

    /**
     * Update method info
     *
     * @param array  $info         Method info
     * @param string $methodName   Method name
     * @param bool   $isDefinition Whether the method info is for a class definition (vs method info on an object instance)
     *
     * @return array
     */
    private static function updateObjectMethod(array $info, $methodName, $isDefinition = false)
    {
        if ($methodName === '__toString') {
            $info['implements'] = 'Stringable';
            $info['return']['type'] = 'string';
        }
        $info = self::updateObjectInheritance($info);
        $info = Methods::buildValues($info);
        $info['params'] = \array_map(static function ($paramInfo) {
            $paramInfo = MethodParams::buildValues($paramInfo);
            $paramInfo['name'] = \trim($paramInfo['name'], '&$.');
            unset($paramInfo['constantName']); // v2.3
            return $paramInfo;
        }, \array_values($info['params']));
        $info['phpDoc'] = self::updatePhpDoc($info['phpDoc']);
        if (isset($info['phpDoc']['return'])) {
            $info['return'] = $info['phpDoc']['return'];
            unset($info['phpDoc']['return']);
        }
        if (isset($info['return']['desc']) === false) {
            $info['return']['desc'] = '';
        }
        if ($isDefinition && isset($info['returnValue'])) {
            $info['returnValue'] = null;
        }
        \ksort($info['return']);
        return $info;
    }

    /**
     * Update object property info
     *
     * @param array $properties   Abstracted properties
     * @param bool  $isDefinition Whether the property info is for a class definition (vs property info on an object instance)
     *
     * @return array
     */
    private static function updateObjectProperties(array $properties, $isDefinition = false)
    {
        return \array_map(static function (array $info) use ($isDefinition) {
            $info = UnserializeLogBackwards::updateValues($info);
            $info = self::updateObjectInheritance($info);
            $info = Properties::buildValues($info);
            $info['visibility'] = (array) $info['visibility'];
            if (isset($info['desc'])) {
                $info['phpDoc']['desc'] = $info['desc'];
            }
            if ($isDefinition) {
                unset($info['scopeClass']);
            }
            $info = \array_diff_assoc($info, array(
                'isExcluded' => false,
            ));
            unset($info['desc']);
            return $info;
        }, $properties);
    }

    /**
     * Update phpDoc values
     * prior to 3.3 null was used vs ''
     *
     * @param array $phpDoc phpDoc values
     *
     * @return array
     */
    private static function updatePhpDoc(array $phpDoc)
    {
        if (isset($phpDoc['description'])) {
            $phpDoc['desc'] = $phpDoc['description'];
            $phpDoc['description'] = null;
        }
        $phpDoc = \array_filter($phpDoc, static function ($val) {
            return $val !== null;
        });
        $phpDoc = \array_merge(array(
            'desc' => '',
            'summary' => '',
        ), $phpDoc);
        return $phpDoc;
    }
}
