<?php

namespace bdk\Debug\Framework\WordPress;

use bdk\Debug;
use bdk\Debug\AbstractComponent;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\LogEntry;
use bdk\Debug\Utility\TableRow;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;

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

        $this->debug->eventManager->subscribe(Debug::EVENT_LOG, [$this, 'onLog'], 1000);
        $this->debug->table('shortcodes', $this->getShortCodeData(), $this->debug->meta(array(
            'columnNames' => array(
                'links' => \_x('links', 'shortcode.links', 'debug-console-php'),
                TableRow::INDEX => 'shortcode',
            ),
        )));
        $this->debug->eventManager->unsubscribe(Debug::EVENT_LOG, [$this, 'onLog']);
    }

    /**
     * Do some processing on the shortcode table logEntry
     * Add the phpDoc row
     *
     * @param LogEntry $logEntry Debug LogEntry instance
     *
     * @return void
     */
    public function onLog(LogEntry $logEntry)
    {
        if ($logEntry['method'] !== 'table') {
            return;
        }

        $dataNew = array();
        $rowsMeta = $logEntry['meta']['tableInfo']['rows'];
        foreach ($logEntry['args'][0] as $shortcode => $row) {
            $phpDoc = $this->debug->phpDoc->getParsed($row[0] . '()');
            $row[1] .= ' <a href="#" data-toggle="#shortcode_' . $shortcode . '_doc" title="handler phpDoc"><i class="fa fa-code"></i></a>';
            $dataNew[$shortcode] = $row;
            $dataNew[$shortcode . ' info'] = [$this->debug->abstracter->crateWithVals(
                $this->buildPhpDoc($shortcode, $phpDoc),
                array(
                    'addQuotes' => false,
                    'sanitize' => false,
                    'visualWhiteSpace' => false,
                )
            )];
            $rowsMeta[$shortcode . ' info'] = $this->buildInfoMeta($shortcode);
        }
        $logEntry['args'][0] = $dataNew;
        $logEntry['meta']['tableInfo']['rows'] = $rowsMeta;
        unset($logEntry['meta']['tableInfo']['columns'][2]);
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
     * Build the table meta info for the phpdoc row
     *
     * @param string $shortcode shortcode
     *
     * @return array
     */
    private function buildInfoMeta($shortcode)
    {
        return array(
            'attribs' => array(
                'id' => 'shortcode_' . $shortcode . '_doc',
                'style' => 'display: none;',
            ),
            'columns' => array(
                array(
                    'attribs' => array(
                        'colspan' => 3,
                    ),
                ),
            ),
            'keyOutput' => false,
        );
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
        $tags = $GLOBALS['shortcode_tags']; // + array('whatgives' => [$this, 'onBootstrap']
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
    protected function postSetCfg($cfg = array(), $prev = array())
    {
        $isFirstConfig = empty($this->cfg['configured']);
        $enabledChanged = isset($cfg['enabled']) && $cfg['enabled'] !== $prev['enabled'];
        if ($enabledChanged === false && $isFirstConfig === false) {
            return;
        }
        $this->cfg['configured'] = true;
    }
}
