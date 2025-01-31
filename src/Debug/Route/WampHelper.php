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

namespace bdk\Debug\Route;

use bdk\Debug;

/**
 * WAMP Helper Methods
 */
class WampHelper
{
    /** @var Debug */
    public $debug;

    /**
     * Constructor
     *
     * @param Debug $debug Debug instance
     */
    public function __construct(Debug $debug)
    {
        $this->debug = $debug;
    }

    /**
     * Get meta values to publish
     *
     * @return array
     */
    public function getMeta()
    {
        $default = array(
            'argv' => array(),
            'DOCUMENT_ROOT' => null,
            'HTTPS' => null,
            'HTTP_HOST' => null,
            'processId' => \getmypid(),
            'REMOTE_ADDR' => null,
            'REQUEST_METHOD' => $this->debug->serverRequest->getMethod(),
            'REQUEST_TIME' => null,
            'REQUEST_URI' => \urldecode($this->debug->serverRequest->getRequestTarget()),
            'SERVER_ADDR' => null,
            'SERVER_NAME' => null,
        );
        $metaVals = \array_merge(
            $default,
            $this->debug->serverRequest->getServerParams()
        );
        $metaVals = \array_intersect_key($metaVals, $default);
        if ($this->debug->isCli()) {
            $metaVals['REQUEST_METHOD'] = null;
            $metaVals['REQUEST_URI'] = '$: ' . \implode(' ', $metaVals['argv']);
        }
        unset($metaVals['argv']);
        return $this->debug->redact($metaVals);
    }

    /**
     * Get config values that are published with meta info
     *
     * @return array
     */
    public function getMetaConfig()
    {
        return array(
            'channelNameRoot' => $this->debug->rootInstance->getCfg('channelName', Debug::CONFIG_DEBUG),
            'debugVersion' => Debug::VERSION,
            'drawer' => $this->debug->getCfg('routeHtml.drawer'),
            'interface' => $this->debug->getInterface(),
            'linkFilesTemplateDefault' => \strtr(
                \ini_get('xdebug.file_link_format'),
                array(
                    '%f' => '%file',
                    '%l' => '%line',
                )
            ) ?: null,
        );
    }
}
