<?php

namespace bdk\Debug\Framework\WordPress;

use bdk\Debug;
use bdk\Debug\AbstractComponent;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\LogEntry;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;
use bdk\Table\TableCell;
use bdk\Table\TableRow;

/**
 * WordPress "shortcode" info
 */
class Shortcodes extends AbstractComponent implements SubscriberInterface
{
    /** @var array<string,mixed> */
    protected $cfg = array(
        'enabled' => true,
    );

    /** @var Debug */
    protected $debug;

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_BOOTSTRAP => 'onBootstrap',
            Debug::EVENT_OUTPUT => 'onOutput',
        );
    }

    /**
     * Debug::EVENT_BOOTSTRAP subscriber
     *
     * @param Event $event Debug::EVENT_BOOTSTRAP event object
     *
     * @return void
     */
    public function onBootstrap(Event $event)
    {
        $this->debug = $event->getSubject()->getChannel('WordPress', array(
            'channelIcon' => 'fa fa-wordpress',
            'channelSort' => 1,
            'nested' => false,
        ));
    }

    /**
     * Debug::EVENT_OUTPUT subscriber
     *
     * @return void
     */
    public function onOutput()
    {
        if ($this->cfg['enabled'] === false) {
            return;
        }

        $logEntry = new LogEntry(
            $this->debug,
            'table',
            [
                'shortcodes',
                $this->getShortCodeData(),
            ],
            array(
                'columnLabels' => array(
                    'links' => \_x('links', 'shortcode.links', 'debug-console-php'),
                    \bdk\Table\Factory::KEY_INDEX => 'shortcode',
                ),
                'sortable' => true,
            )
        );
        $this->debug->rootInstance->getPlugin('methodTable')->doTable($logEntry);
        $this->modifyShortcodeTable($logEntry);
        $this->debug->log($logEntry);
    }

    /**
     * Do some processing on the shortcode table logEntry
     * Add the phpDoc row
     *
     * @param LogEntry $logEntry Debug LogEntry instance
     *
     * @return void
     */
    protected function modifyShortcodeTable(LogEntry $logEntry)
    {
        $table = $logEntry['args'][0];
        $rowsNew = [];
        foreach ($table->getRows() as $row) {
            $rowsNew[] = $row;
            $cells = $row->getCells();
            $shortcode = $cells[0]->getValue();
            $links = $cells[2]->getValue();
            $cells[2]->setValue(
                $links . ' <a href="#" data-toggle="#shortcode_' . $shortcode . '_doc" title="handler phpDoc"><i class="fa fa-code"></i></a>'
            );
            $rowsNew[] = $this->buildInfoRow($row);
        }
        $table->setRows($rowsNew);
    }

    /**
     * Build the phpDoc info row for the shortcode
     *
     * @param TableRow $row TableRow instance
     *
     * @return TableRow
     */
    private function buildInfoRow(TableRow $row)
    {
        $cells = $row->getCells();
        $shortcode = $cells[0]->getValue();
        $method = $cells[1]->getValue();
        $phpDoc = $this->debug->phpDoc->getParsed($method . '()');
        $infoCell = new TableCell($this->debug->abstracter->crateWithVals(
            $this->buildPhpDoc($shortcode, $phpDoc),
            array(
                'addQuotes' => false,
                'sanitize' => false,
                'visualWhiteSpace' => false,
            )
        ));
        $infoCell->setAttribs(array(
            'colspan' => 3,
        ));
        return (new TableRow())
            ->setAttribs(array(
                'id' => 'shortcode_' . $shortcode . '_doc',
                'style' => 'display: none;',
            ))
            ->appendCell($infoCell);
    }

    /**
     * Build link to codex documentation
     *
     * @param string $shortcode shortcode
     *
     * @return string
     */
    private function buildCodexLink($shortcode)
    {
        $wpShortcodes = ['audio', 'caption', 'embed', 'gallery', 'playlist', 'video'];
        return \preg_match('/(?:wp_)?(\w+)/', $shortcode, $matches) && \in_array($matches[1], $wpShortcodes, true)
            ? $this->debug->html->buildTag(
                'a',
                array(
                    'href' => 'http://codex.wordpress.org/' . \ucfirst($matches[1]) . '_Shortcode',
                    'target' => '_blank',
                    'title' => \_x('Codex documentation', 'shortcode.codex_doc', 'debug-console-php'),
                ),
                '<i class="fa fa-external-link"></i>'
            )
            : '';
    }

    /**
     * Build the phpDoc output for the shortcode
     *
     * @param string $shortCode Shortcode
     * @param array  $phpDoc    Parsed phpDoc
     *
     * @return string
     */
    private function buildPhpDoc($shortCode, array $phpDoc)
    {
        if ($shortCode === 'embed') {
            $phpDoc['since'] = [
                array('desc' => '', 'version' => 2.9),
            ];
        }
        $params = \preg_replace('/\{\s*(.*?)\s*\}/s', '$1', $phpDoc['param'][0]['desc']);
        $params = \preg_replace('/@type\s+/', '', $params);
        return $phpDoc['return']['desc'] . "\n\n"
            . $params . "\n\n"
            . 'Since:' . "\n"
            . \implode("\n", \array_map(static function ($since) {
                return \trim($since['version'] . ' ' . $since['desc']);
            }, $phpDoc['since'] ?: []));
    }

    /**
     * Get shortcode data
     *
     * @return array
     */
    private function getShortCodeData()
    {
        $tags = $GLOBALS['shortcode_tags']; // + array('whatGives' => [$this, 'onBootstrap']
        $tags['embed'] = [$GLOBALS['wp_embed'], 'shortcode'];

        $shortcodeData = $this->debug->arrayUtil->mapWithKeys(function ($callable, $shortcode) {
            if (\is_array($callable)) {
                $callable = \get_class($callable[0]) . '::' . $callable[1];
            }
            return array(
                'callable' => $this->debug->abstracter->crateWithVals(
                    $callable,
                    array(
                        'type' => Type::TYPE_IDENTIFIER,
                        'typeMore' => Type::TYPE_IDENTIFIER_METHOD,
                    )
                ),
                'links' => $this->debug->abstracter->crateWithVals(
                    $this->buildCodexLink($shortcode),
                    array(
                        'addQuotes' => false,
                        'sanitize' => false,
                    )
                ),
            );
        }, $tags);
        \ksort($shortcodeData);
        return $shortcodeData;
    }

    /**
     * {@inheritDoc}
     */
    protected function postSetCfg(array $cfg = array(), array $prev = array())
    {
        $isFirstConfig = empty($this->cfg['configured']);
        $enabledChanged = isset($cfg['enabled']) && $cfg['enabled'] !== $prev['enabled'];
        if ($enabledChanged === false && $isFirstConfig === false) {
            return;
        }
        $this->cfg['configured'] = true;
    }
}
