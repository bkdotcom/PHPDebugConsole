<?php

namespace bdk\Debug\Framework\WordPress;

use bdk\Debug;
use bdk\Debug\Framework\WordPress\Settings;
use bdk\Debug\Utility\ArrayUtil;

/**
 * WordPress plugin entry point
 */
class Plugin
{
    const I18N_DOMAIN = 'wordpress';

    /** @var Debug */
    public $debug;

    /** @var array */
    protected $configDefault = array(
        'collect' => true,  // start with collection on as we bootstrap
        'emailFunc' => 'wp_mail',
        'i18n' => array(
            'domainFilepath' => array(
                'wordpress' => __DIR__ . '/lang/{locale}.php',
            ),
        ),
        'plugins' => array(
            'routeDiscord' => array(
                'class' => 'bdk\Debug\Route\Discord',
                'enabled' => false,
            ),
            'routeSlack' => array(
                'class' => 'bdk\Debug\Route\Slack',
                'enabled' => false,
            ),
            'routeTeams' => array(
                'class' => 'bdk\Debug\Route\Teams',
                'enabled' => false,
            ),
            'wordpress' => array( 'class' => 'bdk\Debug\Framework\WordPress\WordPress' ),
            'wordpressCache' => array( 'class' => 'bdk\Debug\Framework\WordPress\ObjectCache' ),
            'wordpressDb' => array( 'class' => 'bdk\Debug\Framework\WordPress\WpDb' ),
            'wordpressDeprecated' => array( 'class' => 'bdk\Debug\Framework\WordPress\Deprecated' ),
            'wordpressHooks' => array( 'class' => 'bdk\Debug\Framework\WordPress\Hooks' ),
            'wordpressHttp' => array( 'class' => 'bdk\Debug\Framework\WordPress\WpHttp' ),
            'wordpressSettings' => array(
                'class' => 'bdk\Debug\Framework\WordPress\Settings',
                'wordpress' => null, // set to $this in getDebugConfig()
            ),
        ),
    );

    /** @var string */
    private $pluginBasename;

    /**
     * Constructor
     *
     * @param string $pluginFilepath Path to the plugin file
     */
    public function __construct($pluginFilepath)
    {
        $this->pluginBasename = \plugin_basename($pluginFilepath);

        $debugConfig = $this->getDebugConfig();
        $this->debug = new Debug($debugConfig);

        \add_action('init', [$this, 'onInit']);
        \add_action('shutdown', [$this, 'onShutdown'], 0);
    }

    /**
     * Get debug configuration
     *
     * @return array
     */
    public function getDebugConfig()
    {
        $storedOptions = \get_option(Settings::GROUP_NAME) ?: array(
            'emailTo' => \get_option('admin_email'),
            'i18n' => array(
                'localeFirstChoice' => \get_locale(),
            ),
            'logEnvInfo' => array(
                'session' => false,
            ),
        );

        $config = ArrayUtil::mergeDeep(
            $this->configDefault,
            $storedOptions
        );
        $config['plugins']['wordpressSettings']['wordpress'] = $this;

        foreach (['routeDiscord', 'routeSlack', 'routeTeams'] as $name) {
            $enabled = !empty($config['plugins'][$name]['enabled']);
            if ($enabled === false) {
                unset($config['plugins'][$name]);
            }
        }

        return $config;
    }

    /**
     * Handle WordPress init action
     *
     * @return void
     */
    public function onInit()
    {
        if (\current_user_can('manage_options')) {
            // we're logged in as admin
            $this->debug->setCfg(array(
                'collect' => true,
                'output' => true,
            ));
        } elseif ($this->debug->getCfg('output') === false) {
            // debug query param / cookie invalid  ('key' config option)
            // turn off collection
            $this->debug->setCfg('collect', false);
        }
        if ($this->debug->getCfg('output') && \class_exists('bdk\WampPublisher')) {
            // not currently configurable, but WampPublisher is installed,
            //   this is what you want
            $this->debug->setCfg('route', $this->debug->routeWamp);
        }
    }

    /**
     * Handle WordPress shutdown action
     *
     * @return void
     */
    public function onShutdown()
    {
        $this->debug->output(false); // false = don't return -> output directly
    }

    /**
     * Gets the path to our plugin file relative to the plugins directory
     *
     * @return string
     */
    public function pluginBasename()
    {
        return $this->pluginBasename;
    }
}
