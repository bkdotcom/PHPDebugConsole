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
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Abstraction\Object\Abstraction as ObjectAbstraction;
use bdk\Debug\Abstraction\Object\Definition;
use bdk\Debug\Abstraction\Object\MethodParams;
use bdk\Debug\Abstraction\Object\Methods;
use bdk\Debug\Abstraction\Object\Properties;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\LogEntry;
use bdk\Debug\Utility\ArrayUtil;
use bdk\PubSub\ValueStore;

/**
 * "Normalize" log entries and values
 */
class UnserializeLogBackwards
{
    /** @var Debug */
    protected static $debug;

    /**
     * Update class definition
     *
     * @param ValueStore $def Class definition to update
     *
     * @return ValueStore
     */
    public static function updateClassDefinition(ValueStore $def, Debug $debug)
    {
        self::$debug = $debug;
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
        unset($values['traverseValues']);
        $values = AbstractObject::buildValues(Definition::buildValues($values));
        if ($values['className'] !== "\x00default\x00") {
            $valueStoreDefault = self::$debug->abstracter->abstractObject->definition->getValueStoreDefault();
            $def = new ObjectAbstraction($valueStoreDefault, $values);
            self::$debug->abstracter->abstractObject->definition->markAsUsed($valueStoreDefault);
            return $def;
        }
        $def->setValues($values);
        return $def;
    }

    /**
     * Update LogEntry with any necessary changes to work with current version of debug
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return LogEntry
     */
    public static function updateLogEntry(LogEntry $logEntry)
    {
        self::$debug = $logEntry->getSubject();
        $method = $logEntry['method'];
        if ($method === 'alert') {
            $logEntry = self::updateLogEntryAlert($logEntry);
        }
        if (\in_array($method, ['profileEnd', 'table', 'trace'], true) && !($logEntry['args'][0] instanceof Abstraction)) {
            self::updateLogEntryTabular($logEntry);
            return $logEntry;
        }
        self::updateLogEntryDefault($logEntry);
        return $logEntry;
    }

    private static function updateLogEntryAlert(LogEntry $logEntry)
    {
        if ($logEntry->getMeta('class')) {
            $level = $logEntry->getMeta('class');
            $levelTrans = array(
                'danger' => 'error',
                'warning' => 'warn',
            );
            $level = \str_replace(\array_keys($levelTrans), $levelTrans, $level);
            $logEntry->setMeta('level', $level);
            $logEntry->setMeta('class', null);
            $logEntry->crate(); // removes the null meta value
        }
        return $logEntry;
    }

    /**
     * Update log entry
     *
     * @param LogEntry $logEntry Log entry to update
     *
     * @return void
     */
    private static function updateLogEntryDefault(LogEntry $logEntry)
    {
        $logEntry['args'] = self::updateValues($logEntry['args']);
    }

    /**
     * Update table, trace, profile end logEntry
     *
     * @param LogEntry $logEntry Log entry to update
     *
     * @return void
     */
    private static function updateLogEntryTabular(LogEntry $logEntry)
    {
        $tableData = $logEntry['args'][0];
        $tableInfo = \array_merge(array(
            'columns' => array(),
            'rows' => array(),
        ), $logEntry->getMeta('tableInfo', array()));
        $columnKeys = \array_column($tableInfo['columns'], 'key');
        $logEntry->setMeta('tableInfo', null);
        if ($logEntry->getMeta('caption')) {
            $logEntry['args'][1] = $logEntry->getMeta('caption');
            $logEntry->setMeta('caption', null);
        }
        foreach ($tableData as $k => $row) {
            if (!empty($tableInfo['rows'][$k]['isScalar'])) {
                $tableData[$k] = array(
                    \bdk\Table\Factory::KEY_SCALAR => \reset($row),
                );
            } elseif (\count($columnKeys) === \count($row)) {
                $tableData[$k] = \array_combine($columnKeys, $row);
            }
        }
        $logEntry['args'][0] = $tableData;
        self::$debug->rootInstance->getPlugin('methodTable')->doTable($logEntry);
        $logEntry->crate();
    }

    /**
     * Update abstraction
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return Abstraction
     */
    private static function updateAbstraction(Abstraction $abs)
    {
        if ($abs->getValue('type') === Type::TYPE_OBJECT) {
            return self::updateObjectAbstraction($abs);
        }
        $values = $abs->getValues();
        $values = \array_diff_assoc($values, array(
            'strlen' => null,
        ));
        $abs->setValues($values);
        return $abs;
    }

    /**
     * Update object abstraction
     *
     * @param Abstraction $abs Object Abstraction
     *
     * @return AbstractObject
     */
    private static function updateObjectAbstraction(Abstraction $abs)
    {
        $values = $abs->getValues();

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

        unset($values['sort']);
        unset($values['traverseValues']);

        $classDefinition = self::$debug->data->get('classDefinitions.' . $values['className']); // ?: self::$debug->abstracter->abstractObject->definition->getValueStoreDefault();
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
        }, $methods);
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
            $info = self::updateValues($info);
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

    /**
     * Walk / Iterate over array and update values as needed
     *
     * @param array $values Values to iterate over
     *
     * @return array
     */
    private static function updateValues(array $values)
    {
        return \array_map(static function ($val) {
            $isAbsArray = \is_array($val) && isset($val['debug']) && $val['debug'] === Abstracter::ABSTRACTION;
            if ($isAbsArray && $val['type'] === Type::TYPE_OBJECT) {
                unset($val['debug'], $val['type']);
                $valueStore = self::$debug->abstracter->abstractObject->definition->getValueStoreDefault();
                $val = new ObjectAbstraction($valueStore, $val);
            } elseif ($isAbsArray) {
                unset($val['debug']);
                $val = new Abstraction($val['type'], $val);
            }
            if ($val instanceof Abstraction) {
                return self::updateAbstraction($val);
            } elseif (\is_array($val)) {
                return self::updateValues($val);
            }
            return $val;
        }, $values);
    }
}
