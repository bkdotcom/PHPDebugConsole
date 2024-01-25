<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2023 Brad Kent
 * @version   v3.1
 */

namespace bdk\Debug\Plugin;

use bdk\Debug;
use bdk\Debug\Plugin\CustomMethodTrait;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;

/**
 * Add additional public methods to debug instance
 */
class Prettify implements SubscriberInterface
{
    use CustomMethodTrait;

    /** @var string[] */
    protected $methods = array(
        'prettify',
    );

    /** @var bool */
    private $highlightAdded = false;

    /**
     * Constructor
     *
     * @codeCoverageIgnore
     */
    public function __construct()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_CUSTOM_METHOD => 'onCustomMethod',
            Debug::EVENT_PRETTIFY => array('onPrettify', -1),
        );
    }

    /**
     * Prettify a string if known content-type
     *
     * @param Event $event Debug::EVENT_PRETTIFY event object
     *
     * @return void
     */
    public function onPrettify(Event $event)
    {
        $matches = array();
        if (\preg_match('#\b(html|json|sql|xml)\b#', (string) $event['contentType'], $matches) !== 1) {
            return;
        }
        $this->onPrettifyDo($event, $matches[1]);
        $event['value'] = $this->debug->abstracter->crateWithVals($event['value'], array(
            'addQuotes' => false,
            'attribs' => array(
                'class' => 'highlight language-' . $event['highlightLang'],
            ),
            'contentType' => $event['contentType'],
            'prettified' => $event['isPrettified'],
            'prettifiedTag' => $event['isPrettified'],
            'visualWhiteSpace' => false,
        ));
        if ($this->highlightAdded === false) {
            $this->debug->addPlugin($this->debug->pluginHighlight);
            $this->highlightAdded = true;
        }
        $event->stopPropagation();
    }

    /**
     * Prettify string
     *
     * format whitepace
     *    json, xml  (or anything else handled via Debug::EVENT_PRETTIFY)
     * add attributes to indicate value should be syntax highlighted
     *    html, json, xml
     *
     * @param string $string      string to prettify]
     * @param string $contentType mime type
     *
     * @return Abstraction|string
     */
    public function prettify($string, $contentType)
    {
        $event = $this->debug->rootInstance->eventManager->publish(
            Debug::EVENT_PRETTIFY,
            $this->debug,
            array(
                'contentType' => $contentType,
                'value' => $string,
            )
        );
        return $event['value'];
    }

    /**
     * Update event's value with prettified string
     *
     * @param Event  $event Event instance
     * @param string $type  html, json, sql, or xml
     *
     * @return string highlight lang
     */
    private function onPrettifyDo(Event $event, $type)
    {
        $lang = $type;
        $string = $event['value'];
        switch ($type) {
            case 'html':
                $lang = 'markup';
                break;
            case 'json':
                $string = $this->debug->stringUtil->prettyJson($string);
                break;
            case 'sql':
                $string = $this->debug->stringUtil->prettySql($string);
                break;
            case 'xml':
                $string = $this->debug->stringUtil->prettyXml($string);
        }
        $event['highlightLang'] = $lang;
        $event['isPrettified'] = $string !== $event['value'];
        $event['value'] = $string;
        return $lang;
    }
}
