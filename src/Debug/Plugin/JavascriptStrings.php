<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
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

    /** @var list<string> These keys have the prefix js. in the translation file */
    private $jsKeys = [
        'attributes',
        'cfg.cookie',
        'cfg.documentation',
        'cfg.link-files',
        'cfg.link-template',
        'cfg.persist-drawer',
        'cfg.theme',
        'cfg.theme.auto',
        'cfg.theme.dark',
        'cfg.theme.light',
        'debugInfo-excluded',
        'debugInfo-value',
        'deprecated',
        'dynamic',
        'final',
        'hook.set',
        'hook.get',
        'hook.both',
        'implements',
        'inherited',
        'method.abstract',
        'method.magic',
        'overrides',
        'private-ancestor',
        'promoted',
        'property.magic',
        'side.alert',
        'side.channels',
        'side.error',
        'side.expand-all-groups',
        'side.info',
        'side.other',
        'side.php-errors',
        'side.warning',
        'throws',
        'virtual',
        'write-only',
    ];

    /** @var list<string> */
    private $sharedKeys = [
        'error.cat.deprecated',
        'error.cat.error',
        'error.cat.fatal',
        'error.cat.notice',
        'error.cat.strict',
        'error.cat.warning',
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
        foreach ($this->jsKeys as $key) {
            $strings[$key] = $i18n->trans('js.' . $key);
        }
        foreach ($this->sharedKeys as $key) {
            $strings[$key] = $i18n->trans($key);
        }
        return array(
            'script' => ['
            phpDebugConsole.setCfg({
                "strings": ' . \json_encode($strings) . ',
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
}
