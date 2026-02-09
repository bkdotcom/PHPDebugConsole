<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0.5
 */

namespace bdk\Debug\Plugin\Method;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\LogEntry;
use bdk\Debug\Plugin\CustomMethodTrait;
use bdk\PubSub\SubscriberInterface;
use bdk\Table\Factory as TableFactory;
use bdk\Table\Table as BdkTable;

/**
 * Table method
 */
class Table implements SubscriberInterface
{
    use CustomMethodTrait;

    /** @var LogEntry */
    private $logEntry;

    /** @var array<string,string> */
    private $tableMeta = [
        'caption' => 'setter',
        'columnLabels' => 'option', // key => label
        'columnMeta' => 'option', // key => meta array
        'columns' => 'option', // list of keys
        'inclIndex' => 'option',
        'sortable' => 'meta',
        'totalCols' => 'option',
    ];

    /** @var string[] */
    protected $methods = [
        'table',
        'doTable',
    ];

    /** @var TableFactory */
    protected $tableFactory;

    /**
     * Constructor
     *
     * @codeCoverageIgnore
     */
    public function __construct()
    {
        $this->tableFactory = new TableFactory(array(
            'getValInfo' => [$this, 'getValInfo'],
        ));
    }

    /**
     * Output an array or object as a table
     *
     * Accepts array/object of array/objects/values
     *
     * Parameters:
     *   1st encountered array (or traversable) is the data
     *   2nd encountered array (optional) specifies columns to output
     *   1st encountered string is a label/caption
     *
     * @param mixed ...$arg traversable, [option array], [caption] in no particular order
     *
     * @return Debug
     *
     * @since 2.0 properly handles array of objects (objects can implement Traversable)
     * @since 2.1 properly handles Traversable as param
     */
    public function table()
    {
        if ($this->debug->getCfg('collect', Debug::CONFIG_DEBUG) === false) {
            return $this->debug;
        }
        $logEntry = new LogEntry(
            $this->debug,
            __FUNCTION__,
            \func_get_args(),
            array(
                'sortable' => true,
            )
        );
        $this->doTable($logEntry);
        $this->debug->log($logEntry);
        return $this->debug;
    }

    /**
     * Handle table() call
     *
     * @param LogEntry $logEntry log entry instance
     *
     * @return void
     */
    public function doTable(LogEntry $logEntry)
    {
        $this->debug = $logEntry->getSubject();

        $cfg = $logEntry->getMeta('cfg', array());
        $cfgRestore = array();
        $maxDepth = isset($cfg['maxDepth'])
            ? $cfg['maxDepth']
            : $this->debug->getCfg('maxDepth');
        if (\in_array($maxDepth, [1, 2], true)) {
            $cfg['maxDepth'] = 3;
        }
        if ($cfg) {
            $cfgRestore = $this->debug->setCfg($cfg);
            $logEntry->setMeta('cfg', null);
        }

        $this->doTableLogEntry($logEntry);

        if ($cfgRestore) {
            $this->debug->setCfg($cfgRestore, Debug::CONFIG_NO_RETURN);
        }
    }

    /**
     * "getValInfo" callback used by TableFactory
     *
     * @param mixed $value Value to get type of
     * @param bool  $isRow Does value represent a row
     *
     * @return array<string,mixed>
     */
    public function getValInfo($value, $isRow = false)
    {
        $type = $this->debug->abstracter->type->getType($value)[0];
        $nonIterable = [
            'UnitEnum',
            'Closure',
            'DateTime',
            'DateTimeImmutable',
        ];
        $isIterable = true;
        foreach ($nonIterable as $nonIterableType) {
            if ($value instanceof $nonIterableType) {
                $isIterable = false;
                break;
            }
        }
        /*
        $className = null;
        if ($type === Type::TYPE_OBJECT) {
            $className = $value instanceof Abstraction
                ? $value['className']
                : \get_class($value);
        } elseif ($type === Type::TYPE_IDENTIFIER && $value instanceof Abstraction) {
            // Extract class name from identifier value (e.g., "PDO::PARAM_STR" -> "PDO")
            $identifierValue = $value['value'];
            if (\is_string($identifierValue) && \strpos($identifierValue, '::') !== false) {
                $className = \explode('::', $identifierValue)[0];
            }
        }
        */
        return array(
            'className' => $type === Type::TYPE_OBJECT
                ? ($value instanceof Abstraction
                    ? $value['className']
                    : \get_class($value))
                : null,
            'iterable' => $isIterable,
            'type' => $type,
        );
    }

    /**
     * Process table log entry
     *
     * @param LogEntry $logEntry Log entry instance
     *
     * @return void
     */
    private function doTableLogEntry(LogEntry $logEntry)
    {
        $this->initLogEntry($logEntry);

        $table = $this->tableFactory->create(
            isset($logEntry['args'][0])
                ? $logEntry['args'][0]
                : null,
            $this->optionsFromLogEntry($logEntry)
        );

        if ($table->getRows()) {
            $this->valsFromLogEntry($table, $logEntry);
            $this->removeTableMetaFromLogEntry($logEntry);
            $logEntry['args'] = [$table];
            return;
        }

        // no table rows...  create log method logEntry instead
        $logEntry['method'] = 'log';
        if ($logEntry->getMeta('caption')) {
            \array_unshift($logEntry['args'], $logEntry->getMeta('caption'));
        } elseif (\count($logEntry['args']) === 0) {
            $logEntry['args'] = [$this->debug->i18n->trans('method.table.no-args')];
        }
        $this->removeTableMetaFromLogEntry($logEntry);
    }

    /**
     * Find the data, caption, & columns in logEntry arguments
     *
     * @param LogEntry $logEntry log entry instance
     *
     * @return void
     */
    private function initLogEntry(LogEntry $logEntry)
    {
        $this->logEntry = $logEntry;
        $args = $this->logEntry['args'];
        $argCount = \count($args);
        $other = Abstracter::UNDEFINED;
        $logEntry['args'] = [];
        for ($i = 0; $i < $argCount; $i++) {
            $isOther = $this->testArg($args[$i]);
            if ($isOther && $other === Abstracter::UNDEFINED) {
                $other = $args[$i];
            }
        }
        if ($logEntry['args'] === [] && $other !== Abstracter::UNDEFINED) {
            $logEntry['args'] = [$other];
        }
    }

    /**
     * Remove table related meta info from logEntry
     *
     * @param LogEntry $logEntry Log entry instance
     *
     * @return void
     */
    private function removeTableMetaFromLogEntry(LogEntry $logEntry)
    {
        foreach (\array_keys($this->tableMeta) as $key) {
            $logEntry->setMeta($key, null);
        }
        $logEntry['meta'] = \array_filter($logEntry['meta'], static function ($val) {
            return $val !== null;
        });
    }

    /**
     * Get table options from logEntry meta
     *
     * @param LogEntry $logEntry Log entry instance
     *
     * @return array<string,mixed>
     */
    private function optionsFromLogEntry(LogEntry $logEntry)
    {
        $keys = \array_keys($this->tableMeta, 'option', true);
        $meta = \array_intersect_key($logEntry['meta'], \array_flip($keys));
        return \array_replace_recursive(array(
            'columnLabels' => array(
                TableFactory::KEY_SCALAR => $this->debug->i18n->trans('word.value'),
            ),
        ), $meta);
    }

    /**
     * Update table with values from logEntry meta
     *
     * @param BdkTable $table    Table instance
     * @param LogEntry $logEntry Log entry instance
     *
     * @return void
     */
    private function valsFromLogEntry(BdkTable $table, LogEntry $logEntry)
    {
        $keys = \array_keys($this->tableMeta, 'setter', true);
        foreach ($keys as $key) {
            $val = $logEntry->getMeta($key);
            $setter = 'set' . \ucfirst($key);
            $table->$setter($val);
        }
        $keys = \array_keys($this->tableMeta, 'meta', true);
        foreach ($keys as $key) {
            $val = $logEntry->getMeta($key);
            $table->setMeta($key, $val);
        }
    }

    /**
     * Place argument as "data", "caption", "columns", or "other"
     *
     * @param mixed $val argument value
     *
     * @return bool whether to treat the val as "other"
     */
    private function testArg($val)
    {
        if (\is_array($val)) {
            $this->testArgArray($val);
            return false;
        }
        if (\is_object($val)) {
            $this->testArgObject($val);
            return false;
        }
        if (\is_string($val) && $this->logEntry->getMeta('caption') === null) {
            $this->logEntry->setMeta('caption', $val);
            return false;
        }
        return true;
    }

    /**
     * Should array argument be treated as table data or columns?
     *
     * @param array $val table() arg of type array
     *
     * @return void
     */
    private function testArgArray($val)
    {
        if ($this->logEntry['args'] === []) {
            $this->logEntry['args'] = [$val];
        } elseif (!$this->logEntry->getMeta('columns')) {
            $this->logEntry->setMeta('columns', $val);
        }
    }

    /**
     * Should object argument be treated as table data?
     *
     * @param array $val table() arg of type object
     *
     * @return void
     */
    private function testArgObject($val)
    {
        // Traversable or other
        if ($this->logEntry['args'] === []) {
            $this->logEntry['args'] = [$val];
        }
    }
}
