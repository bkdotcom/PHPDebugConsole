<?php

namespace bdk\Debug\Framework\WordPress;

use bdk\Debug;
use bdk\Debug\AbstractComponent;
use bdk\Debug\Abstraction\Abstraction;
use bdk\HttpMessage\Utility\ContentType;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;

/**
 * Wordpress "core" info
 */
class WordPress extends AbstractComponent implements SubscriberInterface
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
        if ($this->cfg['enabled'] === false) {
            return;
        }
        $this->debug->group('Environment', $this->debug->meta('level', 'info'));
        $this->debug->log('WordPress version', \get_bloginfo('version'));
        $this->debug->log('wp_get_development_mode', \wp_get_development_mode());
        $this->debug->log('wp_get_environment_type', \wp_get_environment_type());
        $this->debug->log('WP_DEBUG', \WP_DEBUG);
        $this->debug->log('WP_DEBUG_DISPLAY', \WP_DEBUG_DISPLAY);
        $this->debug->log('WP_DEBUG_LOG', \WP_DEBUG_LOG);
        $this->debug->groupEnd();
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
        $type = $this->getQueryType();
        if ($type) {
            $this->debug->log(\_x('Query Type', 'query.type', 'debug-console-php'), $type);
        }

        if (!empty($GLOBALS['template'])) {
            $this->debug->log(\_x('Query Template', 'query.template', 'debug-console-php'), \basename($GLOBALS['template']));
        }

        $this->logShowOnFront();
        $this->logPostType();

        $this->debug->log(\_x('Query Arguments', 'query.arguments', 'debug-console-php'), $GLOBALS['wp_query']->query);

        $this->logQuerySql();
        $this->logQueriedObject();
    }

    /**
     * Log request/query info
     *
     * @return void
     */
    public function logRequestInfo()
    {
        $this->debug->group(\_x('Request / Rewrite', 'request.rewrite', 'debug-console-php'), $this->debug->meta('level', 'info'));
        $this->debug->log(\_x('Request', 'request', 'debug-console-php'), $GLOBALS['wp']->request);
        $this->debug->log(\_x('Query String', 'request.query', 'debug-console-php'), $GLOBALS['wp']->query_string);
        $this->debug->log(\_x('Matched Rewrite Rule', 'rewrite-rule', 'debug-console-php'), $GLOBALS['wp']->matched_rule);
        $this->debug->log(\_x('Matched Rewrite Query', 'rewrite-query', 'debug-console-php'), $GLOBALS['wp']->matched_query);
        $this->debug->groupEnd();
    }

    /**
     * Determine the query type. Follows the template loader order.
     *
     * @return string|null
     */
    private function getQueryType()
    {
        $methods = [
            '404', 'archive', 'attachment', 'author',
            'category', 'date', 'front_page', 'home',
            'page', 'paged', 'search', 'single',
            'tag', 'tax',
        ];

        $type = null;
        foreach ($methods as $typeCheck) {
            if (\call_user_func('is_' . $typeCheck)) {
                $type = \ucwords(\str_replace('_', ' ', $typeCheck));
                break;
            }
        }
        return $type;
    }

    /**
     * Log queried object post type
     *
     * @return void
     */
    private function logPostType()
    {
        $postTypeObject = null;
        $queriedObject = $GLOBALS['wp_query']->get_queried_object();
        if ($queriedObject && isset($queriedObject->post_type)) {
            $postTypeObject = \get_post_type_object($queriedObject->post_type);
        }
        if ($postTypeObject) {
            $this->debug->log(\_x('Post Type', 'query.post-type', 'debug-console-php'), $postTypeObject->labels->singular_name);
        }
    }

    /**
     * Log queried object
     *
     * @return void
     */
    private function logQueriedObject()
    {
        $queriedObject = $GLOBALS['wp_query']->get_queried_object();
        if ($queriedObject !== null) {
            $this->debug->log(\_x('Queried Object', 'query.object', 'debug-console-php'), $queriedObject);
            $this->debug->log(\_x('Queried Object Id', 'query.object-id', 'debug-console-php'), $GLOBALS['wp_query']->get_queried_object_id());
        }
    }

    /**
     * Log query SQL
     *
     * @return void
     */
    private function logQuerySql()
    {
        if (empty($GLOBALS['wp_query']->request)) {
            return;
        }
        $sql = \is_callable([$GLOBALS['wpdb'], 'remove_placeholder_escape'])
            ? $GLOBALS['wpdb']->remove_placeholder_escape($GLOBALS['wp_query']->request)
            : $GLOBALS['wp_query']->request;
        $sql = $this->debug->prettify($sql, ContentType::SQL);
        $isPrettified = $sql instanceof Abstraction;
        if ($isPrettified) {
            $sql['prettifiedTag'] = false; // don't add "(prettified)" to output
        }
        $this->debug->log(\_x('Query SQL', 'query.sql', 'debug-console-php'), $sql);
    }

    /**
     * Log 'show_on_front' option
     *
     * @return void
     */
    private function logShowOnFront()
    {
        $showOnFront = \get_option('show_on_front');
        $this->debug->log(\_x('Show on Front', 'option.show_on_front', 'debug-console-php'), $showOnFront);
        if ($showOnFront === 'page') {
            $this->debug->log(\_x('Page For Posts', 'option.page_for_posts', 'debug-console-php'), \get_option('page_for_posts'));
            $this->debug->log(\_x('Page on Front', 'option.page_on_front', 'debug-console-php'), \get_option('page_on_front'));
        }
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
        if ($cfg['enabled']) {
            \add_action('wp', [$this, 'logRequestInfo']);
            return;
        }
        \remove_action('wp', [$this, 'logRequestInfo']);
    }
}
