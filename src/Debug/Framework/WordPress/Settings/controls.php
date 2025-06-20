<?php

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
        'describedBy' => $this->debug->i18n->trans('settings.control.password.describedBy', [], self::I18N_DOMAIN),
        'label' => $this->debug->i18n->trans('settings.control.password', [], self::I18N_DOMAIN),
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
        'label' => $this->debug->i18n->trans('settings.control.localeFirstChoice', [], self::I18N_DOMAIN),
        'options' => $localesAvailable,
        'required' => true,
        'type' => 'select',
    ),
    'route' => array(
        'default' => 'auto',
        'describedBy' => $this->debug->i18n->trans('settings.control.route.describedBy', [], self::I18N_DOMAIN),
        'label' => $this->debug->i18n->trans('settings.control.route', [], self::I18N_DOMAIN),
        'options' => array( // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
            'auto' => $this->debug->i18n->trans('settings.control.route.option.auto', [], self::I18N_DOMAIN),
            'html' => $this->debug->i18n->trans('settings.control.route.option.html', [], self::I18N_DOMAIN),
            'chromeLogger' => $this->debug->i18n->trans('settings.control.route.option.chromeLogger', [], self::I18N_DOMAIN),
            'firephp' => $this->debug->i18n->trans('settings.control.route.option.firephp', [], self::I18N_DOMAIN),
            'script' => $this->debug->i18n->trans('settings.control.route.option.script', [], self::I18N_DOMAIN),
            'serverLog' => $this->debug->i18n->trans('settings.control.route.option.serverLog', [], self::I18N_DOMAIN),
        ),
        'type' => 'select',
    ),
    'wordpress' => array(
        'default' => ['cache', 'db', 'hooks', 'http', 'core'],
        'label' => 'WordPress',
        'options' => array( // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
            'core' => array(
                'label' => $this->debug->i18n->trans('settings.control.wordpress.option.core', [], self::I18N_DOMAIN),
                'name' => 'plugins[wordpress][enabled]',
            ),
            'cache' => array(
                'label' => $this->debug->i18n->trans('settings.control.wordpress.option.cache', [], self::I18N_DOMAIN),
                'name' => 'plugins[wordpressCache][enabled]',
            ),
            'db' => array(
                'label' => $this->debug->i18n->trans('settings.control.wordpress.option.db', [], self::I18N_DOMAIN),
                'name' => 'plugins[wordpressDb][enabled]',
            ),
            'hooks' => array(
                'label' => $this->debug->i18n->trans('settings.control.wordpress.option.hooks', [], self::I18N_DOMAIN),
                'name' => 'plugins[wordpressHooks][enabled]',
            ),
            'http' => array(
                'label' => $this->debug->i18n->trans('settings.control.wordpress.option.http', [], self::I18N_DOMAIN),
                'name' => 'plugins[wordpressHttp][enabled]',
            ),
        ),
        'type' => 'checkbox',
    ),
    'logEnvInfo' => array(
        'default' => ['errorReporting', 'files', 'phpInfo', 'serverVals'],
        'label' => $this->debug->i18n->trans('settings.control.logEnvInfo', [], self::I18N_DOMAIN),
        'options' => array( // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
            'errorReporting' => $this->debug->i18n->trans('settings.control.logEnvInfo.option.errorReporting', [], self::I18N_DOMAIN),
            'files' => $this->debug->i18n->trans('settings.control.logEnvInfo.option.files', [], self::I18N_DOMAIN),
            'phpInfo' => $this->debug->i18n->trans('settings.control.logEnvInfo.option.phpInfo', [], self::I18N_DOMAIN),
            'serverVals' => $this->debug->i18n->trans('settings.control.logEnvInfo.option.serverVals', [], self::I18N_DOMAIN),
        ),
        'type' => 'checkbox',
    ),
    'logResponse' => array(
        'default' => 'auto',
        'label' => $this->debug->i18n->trans('settings.control.logResponse', [], self::I18N_DOMAIN),
        'options' => array( // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
            'auto' => $this->debug->i18n->trans('settings.control.logResponse.option.auto', [], self::I18N_DOMAIN),
            'true' => $this->debug->i18n->trans('settings.control.logResponse.option.true', [], self::I18N_DOMAIN),
            'false' => $this->debug->i18n->trans('settings.control.logResponse.option.false', [], self::I18N_DOMAIN),
        ),
        'required' => true,
        'type' => 'radio',
    ),
    'logRequestInfo' => array(
        'default' => ['cookies', 'files', 'headers', 'post'],
        'label' => $this->debug->i18n->trans('settings.control.logRequestInfo', [], self::I18N_DOMAIN),
        'options' => array( // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
            'headers' => $this->debug->i18n->trans('settings.control.logRequestInfo.option.headers', [], self::I18N_DOMAIN),
            'cookies' => $this->debug->i18n->trans('settings.control.logRequestInfo.option.cookies', [], self::I18N_DOMAIN),
            'post' => $this->debug->i18n->trans('settings.control.logRequestInfo.option.post', [], self::I18N_DOMAIN),
            'files' => $this->debug->i18n->trans('settings.control.logRequestInfo.option.files', [], self::I18N_DOMAIN),
        ),
        'type' => 'checkbox',
    ),
    'logRuntime' => array(
        'default' => 'on',
        'label' => $this->debug->i18n->trans('settings.control.logRuntime', [], self::I18N_DOMAIN),
        'options' => array(
            'on' => $this->debug->i18n->trans('settings.control.logRuntime.option.on', [], self::I18N_DOMAIN),
        ),
        'type' => 'checkbox',
    ),

    'enableProfiling' => array(
        'describedBy' => $this->debug->i18n->trans('settings.control.enableProfiling.describedBy', array(
            'declareTicks' => '<a target="_blank" href="https://www.php.net/manual/control-structures.declare.php#control-structures.declare.ticks"><code>declare(ticks=1);</code></a>',
        ), self::I18N_DOMAIN),
        'label' => $this->debug->i18n->trans('settings.control.enableProfiling', [], self::I18N_DOMAIN),
        'options' => array(
            'on' => $this->debug->i18n->trans('settings.control.enableProfiling.option.on', [], self::I18N_DOMAIN),
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
        'describedBy' => $this->debug->i18n->trans('settings.control.maxDepth.describedBy', [], self::I18N_DOMAIN),
        'label' => $this->debug->i18n->trans('settings.control.maxDepth', [], self::I18N_DOMAIN),
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
        'describedBy' => $this->debug->i18n->trans('settings.control.waitThrottle.describedBy', [], self::I18N_DOMAIN),
        'label' => $this->debug->i18n->trans('settings.control.waitThrottle', [], self::I18N_DOMAIN),
        'section' => 'errors',
        'type' => 'number',
    ),

    // email
    'enableEmailer' => array(
        // 'default' => 'on',
        'label' => $this->debug->i18n->trans('settings.control.enableEmailer', [], self::I18N_DOMAIN),
        'options' => array(
            'on' => $this->debug->i18n->trans('settings.control.enableEmailer.option.on', [], self::I18N_DOMAIN),
        ),
        'section' => 'errors',
        'type' => 'checkbox',
    ),
    'emailTo' => array(
        'attribs' => array(
            'class' => 'regular-text',
        ),
        'default' => \get_option('admin_email'),
        'label' => $this->debug->i18n->trans('settings.control.emailTo', [], self::I18N_DOMAIN),
        'section' => 'errors',
        'type' => 'email',
        'wpTrClass' => 'indent',
    ),

    // discord
    'plugins[routeDiscord][enabled]' => array(
        'label' => $this->debug->i18n->trans('settings.control.routeDiscord', [], self::I18N_DOMAIN),
        'options' => array(
            'on' => $this->debug->i18n->trans('settings.control.routeDiscord.option.on', [], self::I18N_DOMAIN),
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
        ), self::I18N_DOMAIN) . '<br /><a href="https://support.discord.com/hc/articles/228383668-Intro-to-Webhooks" target="_blank">' . $this->debug->i18n->trans('settings.control.routeDiscord.documentation', [], self::I18N_DOMAIN) . '</a>',
        'label' => $this->debug->i18n->trans('settings.controlCommon.webhookUrl', [], self::I18N_DOMAIN),
        'section' => 'errors',
        'type' => 'url',
        'wpTrClass' => 'indent',
    ),

    'plugins[routeSlack][enabled]' => array(
        'describedBy' => 'Must configure either <a href="https://api.slack.com/messaging/webhooks" target="_blank">webhookUrl</a> or <a href="https://api.slack.com/tutorials/tracks/getting-a-token" target="_blank">token</a> &amp; channel',
        'label' => $this->debug->i18n->trans('settings.control.routeSlack', [], self::I18N_DOMAIN),
        'options' => array(
            'on' => $this->debug->i18n->trans('settings.control.routeSlack.option.on', [], self::I18N_DOMAIN),
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
        ), self::I18N_DOMAIN),
        'label' => $this->debug->i18n->trans('settings.controlCommon.webhookUrl', [], self::I18N_DOMAIN),
        'section' => 'errors',
        'type' => 'url',
        'wpTrClass' => 'indent',
    ),
    'plugins[routeSlack][token]' => array(
        'describedBy' => $this->debug->i18n->trans('settings.controlCommon.defaultsToEnvVar', array(
            'envVar' => '<code>SLACK_TOKEN</code>',
        ), self::I18N_DOMAIN),
        'label' => $this->debug->i18n->trans('settings.control.routeSlackToken', [], self::I18N_DOMAIN),
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
        ), self::I18N_DOMAIN),
        'label' => $this->debug->i18n->trans('settings.control.routeSlackChannel', [], self::I18N_DOMAIN),
        'section' => 'errors',
        'wpTrClass' => 'indent',
    ),

    // Teams
    'plugins[routeTeams][enabled]' => array(
        'label' => $this->debug->i18n->trans('settings.control.routeTeams', [], self::I18N_DOMAIN),
        'options' => array(
            'on' => $this->debug->i18n->trans('settings.control.routeTeams.option.on', [], self::I18N_DOMAIN),
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
        ), self::I18N_DOMAIN)  . '<br /><a href="https://learn.microsoft.com/microsoftteams/platform/webhooks-and-connectors/how-to/add-incoming-webhook?tabs=newteams%2Cdotnet#create-an-incoming-webhook" target="_blank">' . $this->debug->i18n->trans('settings.control.routeTeams.documentation', [], self::I18N_DOMAIN) . '</a>',
        'label' => $this->debug->i18n->trans('settings.controlCommon.webhookUrl', [], self::I18N_DOMAIN),
        'section' => 'errors',
        'type' => 'url',
        'wpTrClass' => 'indent',
    ),
);
