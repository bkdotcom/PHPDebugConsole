<?php

namespace bdk\Debug\Framework\WordPress;

use bdk\Debug;
use bdk\Debug\AbstractComponent;
use bdk\Debug\AssetProviderInterface;
use bdk\Debug\Framework\WordPress\Plugin;
use bdk\Debug\Framework\WordPress\Settings\ControlBuilder;
use bdk\Debug\Framework\WordPress\Settings\FormProcessor;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;

/**
 * Plugin settings page
 */
class Settings extends AbstractComponent implements AssetProviderInterface, SubscriberInterface
{
    const GROUP_NAME = 'debugConsoleForPhp';

    const PAGE_SLUG_NAME = 'debugConsoleForPhp';

    /** @var ControlBuilder */
    protected $controlBuilder;

    /** @var array */
    protected $allErrors = [];

    /**
     * Registered via registerSettings()
     * defined in settingsControls.php
     *
     * @var array
     */
    protected $controls = array();

    /** @var Debug */
    protected $debug;

    /**
     * {@inheritDoc}
     */
    public function getAssets()
    {
        return array(
            'css' => ['
            input[data-lpignore=true] + div[data-lastpass-icon-root] {
                display: none;
            }
            table.form-table tr.indent th label {
                padding-left: 2em;
            }
            '],
            'script' => [__DIR__ . '/Settings/settings.js'],
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_BOOTSTRAP => 'onBootstrap',
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
        $this->debug = $event->getSubject();
        \add_action('admin_init', [$this, 'registerSettings']);
        \add_action('admin_menu', function () {
            \add_options_page(
                $this->debug->i18n->trans('settings.description', Plugin::I18N_DOMAIN),  // page title
                'DebugConsolePhp',	            // menu title
                'manage_options',	            // capability
                self::PAGE_SLUG_NAME,           // menu slug
                [$this, 'outputSettingsPage'],	// callable
                null				            // position
            );
        });
        $name = 'plugin_action_links_' . $this->cfg['wordpress']->pluginBasename();
        \add_filter($name, [$this, 'pluginActionLinks'], 10, 4);
    }

    /**
     * Register our settings and control fields
     *
     * @return void
     */
    public function registerSettings()
    {
        $this->initControlBuilder();

        \register_setting(self::GROUP_NAME, self::GROUP_NAME, array(
            'description' => $this->debug->i18n->trans('settings.description', Plugin::I18N_DOMAIN),
            'sanitize_callback' => [$this, 'sanitize'],
            'type' => 'array',
        ));
        \add_settings_section('general', $this->debug->i18n->trans('settings.section.general', Plugin::I18N_DOMAIN), static function () {
            // echo html here
        }, self::PAGE_SLUG_NAME);
        \add_settings_section('errors', $this->debug->i18n->trans('settings.section.errors', Plugin::I18N_DOMAIN), static function () {
            // echo html here
        }, self::PAGE_SLUG_NAME);

        $this->allErrors = \get_settings_errors(self::GROUP_NAME);
        if ($this->allErrors) {
            $GLOBALS['wp_settings_errors'] = \array_filter($GLOBALS['wp_settings_errors'], static function ($errorInfo) {
                return $errorInfo['setting'] !== self::GROUP_NAME;
            });
        }

        $this->controls = require __DIR__ . '/Settings/controls.php';
        foreach ($this->controls as $name => $control) {
            $this->addSettingsField($control, $name);
        }
    }

    /**
     * Sanitize/Validate/Finalize settings
     *
     * @param array $post Submitted group values array
     *
     * @return array
     */
    public function sanitize(array $post)
    {
        $post = array(
            self::GROUP_NAME => $post,
        );
        $sanitized = FormProcessor::getValues($this->controls, $post);
        $sanitized = $sanitized[self::GROUP_NAME];
        $sanitized['emailMin'] = $sanitized['waitThrottle'];
        $sanitized['passwordHash'] = $this->getPasswordHash($sanitized);
        $sanitized['plugins']['routeDiscord']['throttleMin'] = $sanitized['waitThrottle'];
        $sanitized['plugins']['routeSlack']['throttleMin'] = $sanitized['waitThrottle'];
        $sanitized['plugins']['routeTeams']['throttleMin'] = $sanitized['waitThrottle'];
        unset($sanitized['password']);
        unset($sanitized['previousPasswordHash']);
        unset($sanitized['waitThrottle']);
        return $sanitized;
    }

    /**
     * Output settings page
     *
     * @return void
     */
    public function outputSettingsPage()
    {
        if ($this->debug->hasPlugin('routeHtml') === false) {
            $routeHtml = $this->debug->getRoute('html');
            echo $routeHtml->buildScriptTag();
            echo $routeHtml->buildStyleTag();
            $this->debug->setCfg(array(
                'outputCss' => false,
                'outputScript' => false,
            ));
        }
        echo '<h2>' . $this->debug->i18n->trans('plugin-name', Plugin::I18N_DOMAIN) . ' ' . $this->debug->i18n->trans('settings', Plugin::I18N_DOMAIN) . '</h2>' . "\n";
        echo '<form action="options.php" method="post">' . "\n";
        \settings_fields(self::GROUP_NAME);
        \do_settings_sections(self::PAGE_SLUG_NAME);
        echo '<input name="submit" class="button button-primary" type="submit" value="' . $this->debug->i18n->trans('settings.control.save', Plugin::I18N_DOMAIN) . '" />' . "\n";
        echo '</form>';
    }

    /**
     * Add "settings" link to our plugin in the plugins list
     *
     * @param array $actions Current action links
     *
     * @return array
     */
    public function pluginActionLinks($actions)
    {
        // other args avail: $plugin_file, $plugin_data, $context
        $settings = '<a href="' . \admin_url('options-general.php?page=' . self::PAGE_SLUG_NAME) . '">'
            . $this->debug->i18n->trans('settings', Plugin::I18N_DOMAIN) . '</a>';
        \array_unshift($actions, $settings);
        return $actions;
    }

    /**
     * Wrapper for `add_settings_field()` / make life easier
     *
     * @param array       $field control definition
     * @param string|null $key   control key / default name
     *
     * @return void
     */
    protected function addSettingsField(array $field, $key = null)
    {
        $field = \array_merge(array(
            'name' => $key,
        ), $field);
        $field = $this->controlBuilder->fieldPrep($field);
        $field['errors'] = $this->getFieldErrors($field['id']);
        $this->controls[$key] = $field;
        \add_settings_field(
            $field['id'],
            $field['label'],
            function () use ($field) {
                echo $this->controlBuilder->build($field);
            },
            self::PAGE_SLUG_NAME,
            $field['section'],
            array(
                'class' => $field['wpTrClass'],
                'label_for' => $field['wpLabelFor'],
            )
        );
    }

    /**
     * Get errors for a specific field
     *
     * @param string $id Control field id
     *
     * @return array
     */
    protected function getFieldErrors($id)
    {
        $errors = [];
        foreach ($this->allErrors as $error) {
            if ($error['setting'] === self::GROUP_NAME && $error['code'] === $id) {
                $errors[] = array(
                    'message' => $error['message'],
                    'type' => $error['type'],
                );
            }
        }
        return $errors;
    }

    /**
     * Get passwordHash value from form submission
     *
     * @param array $formValues Submitted form values
     *
     * @return string|null
     */
    private function getPasswordHash(array $formValues)
    {
        if (empty($formValues['password'])) {
            return null;
        }
        if ($formValues['password'] === '_no_change_') {
            return isset($formValues['previousPasswordHash'])
                ? $formValues['previousPasswordHash']
                : null;
        }
        return \password_hash($formValues['password'], PASSWORD_DEFAULT);
    }

    /**
     * Inititalize ControlBuilder
     *
     * @return void
     */
    private function initControlBuilder()
    {
        $groupValues = \get_option(self::GROUP_NAME);
        $haveGroupValues = \is_array($groupValues);
        $groupValues = $groupValues ?: array();
        $this->controlBuilder = new ControlBuilder(array(
            'getValue' => function (array $control) use ($groupValues) {
                \preg_match_all('/\[?([^\[\]]+)\]?/', $control['name'], $matches);
                $nameParts = $matches[1];
                $default = isset($control['default'])
                    ? $control['default']
                    : null;
                return $this->debug->arrayUtil->pathGet($groupValues, \array_slice($nameParts, 1), $default);
            },
            'groupName' => self::GROUP_NAME,
            'haveValues' => $haveGroupValues,
        ));
    }
}
