<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.4
 */

namespace bdk\Debug\Collector;

use bdk\Debug;
use bdk\Debug\AbstractComponent;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\Type;
use bdk\HttpMessage\Utility\ContentType;
use bdk\PubSub\Event;

/**
 * Log sql statement info
 */
class StatementInfoLogger extends AbstractComponent
{
    /**
     * Constructor pulls values from debug config
     * EVENT_CONFIG subscriber keeps config in sync
     *
     * @var array<string,mixed>
     */
    protected $cfg = array(
        'queryLimitSelect' => 500,
        'queryLimitUpdate' => 100,
        'queryLimitWarn' => 'noClause', // whether to warn when rowCount exceeds queryLimitSelect/queryLimitUpdate
                                        //   true|false|'noClause'
                                        //   will "info" when evals false
        'slowQueryDurationMs' => 500, // milliseconds
    );

    /** @var array<int,string> */
    protected static $constants = array();

    /** @var Debug */
    protected $debug;

    /** @var StatementInfo currently being processed */
    protected $info;

    /** @var int */
    protected static $id = 0;

    /** @var array<string,int|float> */
    private $stats = array(
        'duration' => 0, // total duration (in seconds)
        'limitInfo' => 0,
        'limitWarn' => 0,
        'logged' => 0,
        'peakMemoryUsage' => 0,
        'prettifyErrors' => 0, // number of times prettifying failed
        'slow' => 0, // slow query count
    );

    /** @var array<string,mixed> currently being processed */
    private $parsed;

    /**
     * Constructor
     *
     * @param Debug $debug Debug instance
     * @param array $cfg   Configuration
     */
    public function __construct(Debug $debug, array $cfg = array())
    {
        $this->debug = $debug;
        $cfgDefault = \array_intersect_key($debug->getCfg(null, Debug::CONFIG_DEBUG), $this->cfg);
        $cfg = \array_merge($cfgDefault, $cfg);
        $this->setCfg($cfg);
        if (!self::$constants) {
            self::$constants = $this->debug->sql->getParamConstants();
        }
        $this->debug->rootInstance->addPlugin($this->debug->pluginHighlight, 'highlight');
        $this->debug->eventManager->subscribe(Debug::EVENT_CONFIG, [$this, 'onConfig']);
    }

    /**
     * Get aggregated statistics
     *
     * @return array<string,int|float> stats
     */
    public function getStats()
    {
        return $this->stats;
    }

    /**
     * Return the value of the previously output id attribute
     *
     * @return string
     */
    public static function lastGroupId()
    {
        return 'statementInfo' . self::$id;
    }

    /**
     * Add statement info to debug log
     *
     * @param StatementInfo $info         StatementInfo instance
     * @param array         $metaOverride (optional) group meta values
     *
     * @return void
     */
    public function log(StatementInfo $info, array $metaOverride = array())
    {
        $label = $this->logInit($info);
        $this->debug->groupCollapsed($label, $this->debug->meta(\array_merge(array(
            'boldLabel' => false,
            'icon' => $this->debug->getCfg('channelIcon', Debug::CONFIG_DEBUG),
            'id' => 'statementInfo' . (++ self::$id),
        ), $metaOverride)));
        $this->logQuery($label);
        $this->logParams();
        $this->logDuration();
        $this->logMemoryUsage();
        $this->performQueryAnalysis();
        if ($info->exception) {
            $code = $info->exception->getCode();
            $msg = $info->exception->getMessage();
            if (\strpos($msg, (string) $code) === false) {
                $msg .= ' (code ' . $code . ')';
            }
            $this->debug->warn(\get_class($info->exception) . ': ' . \trim($msg));
            $this->debug->groupUncollapse();
        } elseif ($info->rowCount !== null) {
            $this->logRowCount();
        }
        $this->debug->groupEnd();
    }

    /**
     * Log runtime statistics
     *
     * @return void
     */
    public function logStats()
    {
        $debug = $this->debug;
        $stats = $this->getStats();
        $debug->log($this->debug->i18n->trans('runtime.logged-operations') . ': ', $stats['logged']);
        $debug->time($this->debug->i18n->trans('runtime.total-time'), $stats['duration']);
        if ($stats['peakMemoryUsage']) {
            $debug->log($this->debug->i18n->trans('runtime.memory.peak'), $debug->utility->getBytes($stats['peakMemoryUsage']));
        }
        if ($stats['slow']) {
            $debug->warn($this->debug->i18n->trans('db.slow-queries') . ': ', $stats['slow'], $debug->meta('file', null));
        }
        if ($stats['limitWarn']) {
            $debug->warn(
                $this->debug->i18n->trans('db.limit-exceeded') . ': ',
                $stats['limitInfo'] + $stats['limitWarn'],
                $debug->meta('file', null)
            );
        } elseif ($stats['limitInfo']) {
            $debug->info($this->debug->i18n->trans('db.limit-exceeded') . ': ', $stats['limitInfo'] + $stats['limitWarn']);
        }
    }

    /**
     * Debug::EVENT_CONFIG subscriber
     *
     * @param Event $event Event instance
     *
     * @return void
     */
    public function onConfig(Event $event)
    {
        if (!$event['isTarget'] || !$event['debug']) {
            return;
        }
        $cfg = \array_intersect_key($event['debug'], $this->cfg);
        $this->setCfg($cfg);
    }

    /**
     * Log duration
     *
     * @return void
     */
    private function logDuration()
    {
        if ($this->info->duration === null) {
            return;
        }
        $isSlow = $this->info->duration * 1000 >= $this->cfg['slowQueryDurationMs'];
        $this->debug->time(
            $this->debug->i18n->trans('db.duration'),
            $this->info->duration,
            $this->debug->meta('level', $isSlow ? 'warn' : null)
        );
        $this->stats['duration'] += $this->info->duration;
        if ($isSlow) {
            $this->stats['slow']++;
            $this->debug->groupEndValue(
                $this->debug->abstracter->crateWithVals($this->debug->i18n->trans('word.slow'), array(
                    'attribs' => array('class' => 'badge bg-warn fw-bold no-quotes'),
                )),
                $this->debug->meta('attribs', array(
                    'class' => 'hide',
                ))
            );
        }
    }

    /**
     * Intake StatementInfo and return label
     *
     * @param StatementInfo $info StatementInfo instance
     *
     * @return string|false
     */
    private function logInit(StatementInfo $info)
    {
        $sql = \preg_replace('/[\r\n\s]+/', ' ', $info->sql);
        $sql = $this->debug->sql->replaceParams($sql, $info->params);
        $this->info = $info;
        $this->parsed = $this->debug->sql->parse($sql);
        $this->stats['logged']++;
        return $this->parsed
            ? $this->debug->sql->labelFromParsed($this->parsed)
            : $sql;
    }

    /**
     * Log memory usage
     *
     * @return void
     */
    private function logMemoryUsage()
    {
        if ($this->info->memoryUsage !== null) {
            $memory = $this->debug->utility->getBytes($this->info->memoryUsage);
            $this->debug->log($this->debug->i18n->trans('runtime.memory.usage'), $memory);
            $this->stats['peakMemoryUsage'] = \max($this->stats['peakMemoryUsage'], $this->info->memoryUsage);
        }
    }

    /**
     * Log statement bound params
     *
     * @return void
     */
    protected function logParams()
    {
        if ($this->info->params) {
            $this->info->types
                ? $this->logParamsTypes()
                : $this->debug->log($this->debug->i18n->trans('word.parameters'), $this->info->params);
        }
    }

    /**
     * Log params with types as table
     *
     * @return void
     */
    private function logParamsTypes()
    {
        $params = $this->debug->arrayUtil->mapWithKeys(function ($value, $name) {
            $param = array(
                'value' => $value,
            );
            if (!isset($this->info->types[$name])) {
                return $param;
            }
            $type = $this->info->types[$name];
            $isIntOrString = \is_int($type) || \is_string($type);
            $param['type'] = $isIntOrString && isset(self::$constants[$type])
                ? new Abstraction(Type::TYPE_IDENTIFIER, array(
                    'backedValue' => $type,
                    'typeMore' => Type::TYPE_IDENTIFIER_CONST,
                    'value' => self::$constants[$type],
                ))
                : $type; // integer value (or enum)
            return $param;
        }, $this->info->params);
        $this->debug->table($this->debug->i18n->trans('word.parameters'), $params);
    }

    /**
     * Log the sql query (if it differs from the label)
     *
     * @param string $label The abbrev'd sql statement
     *
     * @return void
     */
    private function logQuery($label)
    {
        if (\preg_replace('/[\r\n\s]+/', ' ', $this->info->sql) === $label) {
            return;
        }
        $stringMaxLenBak = $this->debug->setCfg('stringMaxLen', -1, Debug::CONFIG_NO_PUBLISH);
        $sqlPretty = $this->debug->prettify($this->info->sql, ContentType::SQL);
        $isPrettified = $sqlPretty instanceof Abstraction;
        $this->stats['prettifyErrors'] += $isPrettified ? 0 : 1;
        if ($isPrettified) {
            $sqlPretty['prettifiedTag'] = false; // don't add "(prettified)" to output
        }
        $this->debug->log(
            $sqlPretty,
            $this->debug->meta(array(
                'attribs' => array(
                    'class' => 'no-indent',
                ),
            ))
        );
        $this->debug->setCfg('stringMaxLen', $stringMaxLenBak, Debug::CONFIG_NO_PUBLISH | Debug::CONFIG_NO_RETURN);
    }

    /**
     * Log row count
     *
     * @return void
     */
    private function logRowCount()
    {
        $level = $this->rowCountLevel();
        $this->debug->log(
            $this->debug->i18n->trans('db.row-count'),
            $this->info->rowCount,
            $this->debug->meta('level', $level)
        );
        if ($level) {
            $this->stats['limit' . \ucfirst($level)]++;
            $this->debug->groupEndValue(
                $this->debug->abstracter->crateWithVals($this->debug->i18n->trans('word.limit'), array(
                    'attribs' => array('class' => 'badge bg-' . $level . ' fw-bold no-quotes'),
                )),
                $this->debug->meta('attribs', array(
                    'class' => 'hide',
                ))
            );
        }
    }

    /**
     * Find common query performance issues
     *
     * @return void
     */
    protected function performQueryAnalysis()
    {
        $issues = $this->debug->sqlQueryAnalysis->analyze($this->info->sql);
        \array_walk($issues, function ($issue) {
            $params = [$issue];
            $cCount = \substr_count($params[0], '%c');
            for ($i = 0; $i < $cCount; $i += 2) {
                $params[] = 'font-family:monospace';
                $params[] = '';
            }
            $params[] = $this->debug->meta('uncollapse', false);
            \call_user_func_array([$this->debug, 'warn'], $params);
        });
    }

    /**
     * Check if rowCount exceeds configured notice limit
     *
     * @return null|"info"|"warn"
     */
    private function rowCountLevel()
    {
        $method = $this->parsed['method'];
        $isOverLimit = ($method === 'select' && $this->info->rowCount > $this->cfg['queryLimitSelect'])
            || ($method !== 'select' && $this->info->rowCount > $this->cfg['queryLimitUpdate']);
        if (!$isOverLimit) {
            return null;
        }
        $isWarn = $this->cfg['queryLimitWarn'] === true
            || ($this->cfg['queryLimitWarn'] === 'noClause' && !$this->parsed['limit']);
        return $isWarn ? 'warn' : 'info';
    }
}
