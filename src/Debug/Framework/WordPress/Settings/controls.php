<?php

use bdk\Debug\Framework\WordPress\Plugin;

if (\defined('ABSPATH') === false) {
    exit;
}

$currentValues = \get_option(self::GROUP_NAME) ?: array();

$localeDefault = \get_locale();
$localesAvailable = $this->debug->i18n->availableLocales();
if (isset($localesAvailable[$localeDefault]) === false) {
    $localeDefault = substr($localeDefault, 0, 2);
}

// @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
return array(
    'password' => array(
        'attribs' => array(
            'data-lpignore' => true,
        ),
        'describedBy' => $this->debug->i18n->trans('settings.control.password.describedBy', Plugin::I18N_DOMAIN),
        'label' => $this->debug->i18n->trans('settings.control.password', Plugin::I18N_DOMAIN),
        'type' => 'password',
        'value' => isset($currentValues['passwordHash'])
            ? '_no_change_'
            : '',
    ),
    'previousPasswordHash' => array(
        'type' => 'hidden',
        'value' => isset($currentValues['passwordHash'])
            ? $currentValues['passwordHash']
            : null,
        'wpTrClass' => 'hidden',
    ),
    'i18n[localeFirstChoice]' => array(
        'default' => $localeDefault,
        'label' => $this->debug->i18n->trans('settings.control.localeFirstChoice', Plugin::I18N_DOMAIN),
        'options' => $localesAvailable,
        'required' => true,
        'type' => 'select',
    ),
    'route' => array(
        'default' => 'auto',
        'describedBy' => $this->debug->i18n->trans('settings.control.route.describedBy', Plugin::I18N_DOMAIN),
        'label' => $this->debug->i18n->trans('settings.control.route', Plugin::I18N_DOMAIN),
        'options' => array( // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
            'auto' => $this->debug->i18n->trans('settings.control.route.option.auto', Plugin::I18N_DOMAIN),
            'html' => $this->debug->i18n->trans('settings.control.route.option.html', Plugin::I18N_DOMAIN),
            'chromeLogger' => $this->debug->i18n->trans('settings.control.route.option.chromeLogger', Plugin::I18N_DOMAIN),
            'firephp' => $this->debug->i18n->trans('settings.control.route.option.firephp', Plugin::I18N_DOMAIN),
            'script' => $this->debug->i18n->trans('settings.control.route.option.script', Plugin::I18N_DOMAIN),
            'serverLog' => $this->debug->i18n->trans('settings.control.route.option.serverLog', Plugin::I18N_DOMAIN),
        ),
        'type' => 'select',
    ),
    'wordpress' => array(
        'default' => ['cache', 'db', 'hooks', 'http', 'core'],
        'label' => 'WordPress',
        'options' => array( // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
            'core' => array(
                'label' => $this->debug->i18n->trans('settings.control.wordpress.option.core', Plugin::I18N_DOMAIN),
                'name' => 'plugins[wordpress][enabled]',
            ),
            'cache' => array(
                'label' => $this->debug->i18n->trans('settings.control.wordpress.option.cache', Plugin::I18N_DOMAIN),
                'name' => 'plugins[wordpressCache][enabled]',
            ),
            'db' => array(
                'label' => $this->debug->i18n->trans('settings.control.wordpress.option.db', Plugin::I18N_DOMAIN),
                'name' => 'plugins[wordpressDb][enabled]',
            ),
            'hooks' => array(
                'label' => $this->debug->i18n->trans('settings.control.wordpress.option.hooks', Plugin::I18N_DOMAIN),
                'name' => 'plugins[wordpressHooks][enabled]',
            ),
            'http' => array(
                'label' => $this->debug->i18n->trans('settings.control.wordpress.option.http', Plugin::I18N_DOMAIN),
                'name' => 'plugins[wordpressHttp][enabled]',
            ),
        ),
        'type' => 'checkbox',
    ),
    'logEnvInfo' => array(
        'default' => ['errorReporting', 'files', 'phpInfo', 'serverVals'],
        'label' => $this->debug->i18n->trans('settings.control.logEnvInfo', Plugin::I18N_DOMAIN),
        'options' => array( // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
            'errorReporting' => $this->debug->i18n->trans('settings.control.logEnvInfo.option.errorReporting', Plugin::I18N_DOMAIN),
            'files' => $this->debug->i18n->trans('settings.control.logEnvInfo.option.files', Plugin::I18N_DOMAIN),
            'phpInfo' => $this->debug->i18n->trans('settings.control.logEnvInfo.option.phpInfo', Plugin::I18N_DOMAIN),
            'serverVals' => $this->debug->i18n->trans('settings.control.logEnvInfo.option.serverVals', Plugin::I18N_DOMAIN),
        ),
        'type' => 'checkbox',
    ),
    'logResponse' => array(
        'default' => 'auto',
        'label' => $this->debug->i18n->trans('settings.control.logResponse', Plugin::I18N_DOMAIN),
        'options' => array( // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
            'auto' => $this->debug->i18n->trans('settings.control.logResponse.option.auto', Plugin::I18N_DOMAIN),
            'true' => $this->debug->i18n->trans('settings.control.logResponse.option.true', Plugin::I18N_DOMAIN),
            'false' => $this->debug->i18n->trans('settings.control.logResponse.option.false', Plugin::I18N_DOMAIN),
        ),
        'required' => true,
        'type' => 'radio',
    ),
    'logRequestInfo' => array(
        'default' => ['cookies', 'files', 'headers', 'post'],
        'label' => $this->debug->i18n->trans('settings.control.logRequestInfo', Plugin::I18N_DOMAIN),
        'options' => array( // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
            'headers' => $this->debug->i18n->trans('settings.control.logRequestInfo.option.headers', Plugin::I18N_DOMAIN),
            'cookies' => $this->debug->i18n->trans('settings.control.logRequestInfo.option.cookies', Plugin::I18N_DOMAIN),
            'post' => $this->debug->i18n->trans('settings.control.logRequestInfo.option.post', Plugin::I18N_DOMAIN),
            'files' => $this->debug->i18n->trans('settings.control.logRequestInfo.option.files', Plugin::I18N_DOMAIN),
        ),
        'type' => 'checkbox',
    ),
    'logRuntime' => array(
        'default' => 'on',
        'label' => $this->debug->i18n->trans('settings.control.logRuntime', Plugin::I18N_DOMAIN),
        'options' => array(
            'on' => $this->debug->i18n->trans('settings.control.logRuntime.option.on', Plugin::I18N_DOMAIN),
        ),
        'type' => 'checkbox',
    ),

    'enableProfiling' => array(
        'describedBy' => $this->debug->i18n->trans('settings.control.enableProfiling.describedBy', array(
            'declareTicks' => '<a target="_blank" href="https://www.php.net/manual/control-structures.declare.php#control-structures.declare.ticks"><code>declare(ticks=1);</code></a>',
        ), Plugin::I18N_DOMAIN),
        'label' => $this->debug->i18n->trans('settings.control.enableProfiling', Plugin::I18N_DOMAIN),
        'options' => array(
            'on' => $this->debug->i18n->trans('settings.control.enableProfiling.option.on', Plugin::I18N_DOMAIN),
        ),
        'type' => 'checkbox',
    ),

    'maxDepth' => array(
        'attribs' => array(
            'class' => 'small-text',
            'min' => 0,
            'step' => 1,
        ),
        'default' => 0,
        'describedBy' => $this->debug->i18n->trans('settings.control.maxDepth.describedBy', Plugin::I18N_DOMAIN),
        'label' => $this->debug->i18n->trans('settings.control.maxDepth', Plugin::I18N_DOMAIN),
        'type' => 'number',
    ),

    // errors
    'waitThrottle' => array(
        'attribs' => array(
            'class' => 'small-text',
            'min' => 0,
            'step' => 1,
        ),
        'default' => 60,
        'describedBy' => $this->debug->i18n->trans('settings.control.waitThrottle.describedBy', Plugin::I18N_DOMAIN),
        'label' => $this->debug->i18n->trans('settings.control.waitThrottle', Plugin::I18N_DOMAIN),
        'section' => 'errors',
        'type' => 'number',
    ),

    // email
    'enableEmailer' => array(
        // 'default' => 'on',
        'label' => $this->debug->i18n->trans('settings.control.enableEmailer', Plugin::I18N_DOMAIN),
        'options' => array(
            'on' => $this->debug->i18n->trans('settings.control.enableEmailer.option.on', Plugin::I18N_DOMAIN),
        ),
        'section' => 'errors',
        'type' => 'checkbox',
    ),
    'emailTo' => array(
        'attribs' => array(
            'class' => 'regular-text',
        ),
        'default' => \get_option('admin_email'),
        'label' => $this->debug->i18n->trans('settings.control.emailTo', Plugin::I18N_DOMAIN),
        'section' => 'errors',
        'type' => 'email',
        'wpTrClass' => 'indent',
    ),

    // discord
    'plugins[routeDiscord][enabled]' => array(
        'label' => $this->debug->i18n->trans('settings.control.routeDiscord', Plugin::I18N_DOMAIN),
        'options' => array(
            'on' => $this->debug->i18n->trans('settings.control.routeDiscord.option.on', Plugin::I18N_DOMAIN),
        ),
        'section' => 'errors',
        'type' => 'checkbox',
    ),
    'plugins[routeDiscord][webhookUrl]' => array(
        'attribs' => array(
            'class' => 'regular-text',
            'placeholder' => 'https://discord.com/api/webhooks/xxxxx',
        ),
        'describedBy' => $this->debug->i18n->trans('settings.controlCommon.defaultsToEnvVar', array(
            'envVar' => '<code>DISCORD_WEBHOOK_URL</code>',
        ), Plugin::I18N_DOMAIN) . '<br /><a href="https://support.discord.com/hc/articles/228383668-Intro-to-Webhooks" target="_blank">' . $this->debug->i18n->trans('settings.control.routeDiscord.documentation', Plugin::I18N_DOMAIN) . '</a>',
        'label' => $this->debug->i18n->trans('settings.controlCommon.webhookUrl', Plugin::I18N_DOMAIN),
        'section' => 'errors',
        'type' => 'url',
        'wpTrClass' => 'indent',
    ),

    'plugins[routeSlack][enabled]' => array(
        'describedBy' => 'Must configure either <a href="https://api.slack.com/messaging/webhooks" target="_blank">webhookUrl</a> or <a href="https://api.slack.com/tutorials/tracks/getting-a-token" target="_blank">token</a> &amp; channel',
        'label' => $this->debug->i18n->trans('settings.control.routeSlack', Plugin::I18N_DOMAIN),
        'options' => array(
            'on' => $this->debug->i18n->trans('settings.control.routeSlack.option.on', Plugin::I18N_DOMAIN),
        ),
        'section' => 'errors',
        'type' => 'checkbox',
    ),
    'plugins[routeSlack][webhookUrl]' => array(
        'attribs' => array(
            'class' => 'regular-text',
            'placeholder' => 'https://hooks.slack.com/services/xxxxx',
        ),
        'describedBy' => $this->debug->i18n->trans('settings.controlCommon.defaultsToEnvVar', array(
            'envVar' => '<code>SLACK_WEBHOOK_URL</code>',
        ), Plugin::I18N_DOMAIN),
        'label' => $this->debug->i18n->trans('settings.controlCommon.webhookUrl', Plugin::I18N_DOMAIN),
        'section' => 'errors',
        'type' => 'url',
        'wpTrClass' => 'indent',
    ),
    'plugins[routeSlack][token]' => array(
        'describedBy' => $this->debug->i18n->trans('settings.controlCommon.defaultsToEnvVar', array(
            'envVar' => '<code>SLACK_TOKEN</code>',
        ), Plugin::I18N_DOMAIN),
        'label' => $this->debug->i18n->trans('settings.control.routeSlackToken', Plugin::I18N_DOMAIN),
        'section' => 'errors',
        'type' => 'password',
        'wpTrClass' => 'indent',
    ),
    'plugins[routeSlack][channel]' => array(
        'attribs' => array(
            'placeholder' => '#myChannel',
        ),
        'describedBy' => $this->debug->i18n->trans('settings.controlCommon.defaultsToEnvVar', array(
            'envVar' => '<code>SLACK_CHANNEL</code>',
        ), Plugin::I18N_DOMAIN),
        'label' => $this->debug->i18n->trans('settings.control.routeSlackChannel', Plugin::I18N_DOMAIN),
        'section' => 'errors',
        'wpTrClass' => 'indent',
    ),

    // Teams
    'plugins[routeTeams][enabled]' => array(
        'label' => $this->debug->i18n->trans('settings.control.routeTeams', Plugin::I18N_DOMAIN),
        'options' => array(
            'on' => $this->debug->i18n->trans('settings.control.routeTeams.option.on', Plugin::I18N_DOMAIN),
        ),
        'section' => 'errors',
        'type' => 'checkbox',
    ),
    'plugins[routeTeams][webhookUrl]' => array(
        'attribs' => array(
            'class' => 'regular-text',
            'placeholder' => 'https://qwerty.webhook.office.com/webhookb2/xxxxx',
        ),
        'describedBy' => $this->debug->i18n->trans('settings.controlCommon.defaultsToEnvVar', array(
            'envVar' => '<code>TEAMS_WEBHOOK_URL</code>',
        ), Plugin::I18N_DOMAIN)  . '<br /><a href="https://learn.microsoft.com/microsoftteams/platform/webhooks-and-connectors/how-to/add-incoming-webhook?tabs=newteams%2Cdotnet#create-an-incoming-webhook" target="_blank">' . $this->debug->i18n->trans('settings.control.routeTeams.documentation', Plugin::I18N_DOMAIN) . '</a>',
        'label' => $this->debug->i18n->trans('settings.controlCommon.webhookUrl', Plugin::I18N_DOMAIN),
        'section' => 'errors',
        'type' => 'url',
        'wpTrClass' => 'indent',
    ),
);
