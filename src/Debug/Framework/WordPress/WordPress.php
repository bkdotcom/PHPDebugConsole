<?php

namespace bdk\Debug\Framework\WordPress;

use bdk\Debug;
use bdk\Debug\AbstractComponent;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Framework\WordPress\Plugin;
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
            $this->debug->log($this->debug->i18n->trans('query.type', Plugin::I18N_DOMAIN), $type);
        }

        if (!empty($GLOBALS['template'])) {
            $this->debug->log($this->debug->i18n->trans('query.template', Plugin::I18N_DOMAIN), \basename($GLOBALS['template']));
        }

        $this->logShowOnFront();
        $this->logPostType();

        $this->debug->log($this->debug->i18n->trans('query.arguments', Plugin::I18N_DOMAIN), $GLOBALS['wp_query']->query);

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
        $this->debug->group($this->debug->i18n->trans('request.rewrite', Plugin::I18N_DOMAIN), $this->debug->meta('level', 'info'));
        $this->debug->log($this->debug->i18n->trans('request'), $GLOBALS['wp']->request);
        $this->debug->log($this->debug->i18n->trans('request.query', Plugin::I18N_DOMAIN), $GLOBALS['wp']->query_string);
        $this->debug->log($this->debug->i18n->trans('rewrite-rule', Plugin::I18N_DOMAIN), $GLOBALS['wp']->matched_rule);
        $this->debug->log($this->debug->i18n->trans('rewrite-query', Plugin::I18N_DOMAIN), $GLOBALS['wp']->matched_query);
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
            $this->debug->log($this->debug->i18n->trans('query.post-type', Plugin::I18N_DOMAIN), $postTypeObject->labels->singular_name);
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
            $this->debug->log($this->debug->i18n->trans('query.object', Plugin::I18N_DOMAIN), $queriedObject);
            $this->debug->log($this->debug->i18n->trans('query.object-id', Plugin::I18N_DOMAIN), $GLOBALS['wp_query']->get_queried_object_id());
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
        $this->debug->log($this->debug->i18n->trans('query.sql', Plugin::I18N_DOMAIN), $sql);
    }

    /**
     * Log 'show_on_front' option
     *
     * @return void
     */
    private function logShowOnFront()
    {
        $showOnFront = \get_option('show_on_front');
        $this->debug->log($this->debug->i18n->trans('option.show_on_front', Plugin::I18N_DOMAIN), $showOnFront);
        if ($showOnFront === 'page') {
            $this->debug->log($this->debug->i18n->trans('option.page_for_posts', Plugin::I18N_DOMAIN), \get_option('page_for_posts'));
            $this->debug->log($this->debug->i18n->trans('option.page_on_front', Plugin::I18N_DOMAIN), \get_option('page_on_front'));
        }
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
        if ($cfg['enabled']) {
            \add_action('wp', [$this, 'logRequestInfo']);
            return;
        }
        \remove_action('wp', [$this, 'logRequestInfo']);
    }
}
