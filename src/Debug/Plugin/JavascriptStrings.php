<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.5
 */

namespace bdk\Debug\Plugin;

use bdk\Debug;
use bdk\Debug\AssetProviderInterface;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;

/**
 * Provide strings for our javascript
 */
class JavascriptStrings implements AssetProviderInterface, SubscriberInterface
{
    /** @var Debug */
    private $debug;

    /** @var list<string> */
    private $keys = [
        'error.cat.deprecated',
        'error.cat.error',
        'error.cat.fatal',
        'error.cat.notice',
        'error.cat.strict',
        'error.cat.warning',
        'self.name',
        'word.close',
        'word.options',
    ];

    /**
     * {@inheritDoc}
     */
    public function getAssets()
    {
        $i18n = $this->debug->i18n;
        if ($i18n->getLocale() === 'en') {
            return array();
        }
        $strings = array();
        foreach ($this->getJsKeys() as $key) {
            $strings[$key] = $i18n->trans('js.' . $key);
        }
        foreach ($this->keys as $key) {
            $strings[$key] = $i18n->trans($key);
        }
        return array(
            'script' => ['
            phpDebugConsole.setCfg({
                strings: ' . \json_encode($strings) . ',
            });
            '],
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_PLUGIN_INIT => 'onPluginInit',
        );
    }

    /**
     * Debug::EVENT_PLUGIN_INIT subscriber
     *
     * @param Event $event Debug::EVENT_PLUGIN_INIT Event instance
     *
     * @return void
     */
    public function onPluginInit(Event $event)
    {
        $this->debug = $event->getSubject();
    }

    /**
     * Get the keys for javascript strings
     *
     * @return list<string>
     */
    private function getJsKeys()
    {
        $filepath = __DIR__ . '/../lang/debug/en.php';
        $data = require $filepath;
        $jsKeys = [];
        foreach (\array_keys($data) as $key) {
            if (\strpos($key, 'js.') === 0) {
                $jsKeys[] = \substr($key, 3); // remove js. prefix
            }
        }
        return $jsKeys;
    }
}
