<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.3
 */

namespace bdk\Debug\Framework\Yii2;

use bdk\Debug;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\Framework\Yii2\Module as DebugModule;
use bdk\PubSub\SubscriberInterface;
use Yii;
use yii\base\Event as YiiEvent;

/**
 * Collect and log Yii events
 */
class CollectEvents implements SubscriberInterface
{
    /** @var Debug */
    protected $debug;

    /** @var DebugModule */
	protected $debugModule;

    /** @var list<array<string,mixed>> */
    private $collectedEvents = array();

	/**
	 * Constructor
	 *
	 * @param DebugModule $debugModule DebugModule instance
	 */
	public function __construct(DebugModule $debugModule)
	{
		$this->debug = $debugModule->debug;
		$this->debugModule = $debugModule;
	}

    /**
     * Collect Yii events
     *
     * @return void
     */
    public function bootstrap()
    {
        if ($this->debugModule->shouldCollect('events') === false) {
            return;
        }
        /*
            $this->module->getVersion() returns the application "module" version vs framework version ¯\_(ツ)_/¯
        */
        $yiiVersion = Yii::getVersion();  // Framework version
        if (\version_compare($yiiVersion, '2.0.14', '<')) {
            return;
        }
        YiiEvent::on('*', '*', function (YiiEvent $event) {
            $this->collectedEvents[] = array(
                'eventClass' => \get_class($event),
                'index' => \count($this->collectedEvents),
                'isStatic' => \is_object($event->sender) === false,
                'name' => $event->name,
                'senderClass' => \is_object($event->sender)
                    ? \get_class($event->sender)
                    : $event->sender,
            );
        });
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_OUTPUT => 'onDebugOutput',
        );
    }

    /**
     * PhpDebugConsole output event listener
     *
     * @return void
     */
    public function onDebugOutput()
    {
        $this->logCollectedEvents();
    }

    /**
     * Get collectedEvents table rows
     *
     * @return array
     */
    private function getEventTableData()
    {
        $tableData = array();
        foreach ($this->collectedEvents as $info) {
            $key = $info['senderClass'] . $info['name'];
            if (isset($tableData[$key])) {
                $tableData[$key]['count']++;
                continue;
            }
            $info['count'] = 1;
            $tableData[$key] = $info;
        }

        \usort($tableData, static function ($infoA, $infoB) {
            $cmp = \strcmp($infoA['senderClass'], $infoB['senderClass']);
            if ($cmp) {
                return $cmp;
            }
            return $infoA['index'] - $infoB['index'];
        });
        return $tableData;
    }

    /**
     * Output collected event info
     *
     * @return void
     */
    protected function logCollectedEvents()
    {
        $tableData = $this->getEventTableData();
        foreach ($tableData as &$info) {
            unset($info['index']);
            $info['senderClass'] = $this->debug->abstracter->crateWithVals($info['senderClass'], array(
                'type' => Type::TYPE_IDENTIFIER,
                'typeMore' => Type::TYPE_IDENTIFIER_CLASSNAME,
            ));
            $info['eventClass'] = $this->debug->abstracter->crateWithVals($info['eventClass'], array(
                'type' => Type::TYPE_IDENTIFIER,
                'typeMore' => Type::TYPE_IDENTIFIER_CLASSNAME,
            ));
        }

        $debug = $this->debug->rootInstance->getChannel('events');
        $debug->table(\array_values($tableData));
    }
}
