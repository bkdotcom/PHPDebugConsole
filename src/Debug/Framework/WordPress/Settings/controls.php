<?php

if (\defined('ABSPATH') === false) {
    exit;
}

$currentValues = \get_option(self::GROUP_NAME) ?: array();

// @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
return array(
    'password' => array(
        'attribs' => array(
            'data-lpignore' => true,
        ),
        'describedBy' => \_x('This password may be passed in the request to enable collecting/outputting the log without being logged in as an admin.<br />Enter the plain-text password here.  It will be stored as a hashed value.', 'settings.control.password.describedBy', 'debug-console-php'),
        'label' => \_x('Password', 'settings.control.password', 'debug-console-php'),
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
    'route' => array(
        'default' => 'auto',
        'describedBy' => \_x('How the log will be output', 'settings.control.route.describedBy', 'debug-console-php'),
        'label' => \_x('Route', 'settings.control.route', 'debug-console-php'),
        'options' => array( // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
            'auto' => \_x('Automatic', 'settings.control.route.option.auto', 'debug-console-php'),
            'html' => \_x('HTML (appended to page output)', 'settings.control.route.option.html', 'debug-console-php'),
            'chromeLogger' => \_x('chromeLogger (browser plugin)', 'settings.control.route.option.chromeLogger', 'debug-console-php'),
            'firephp' => \_x('FirePHP (browser plugin)', 'settings.control.route.option.firephp', 'debug-console-php'),
            'script' => \_x('Script (output to developer console)', 'settings.control.route.option.script', 'debug-console-php'),
            'serverLog' => \_x('Server Log (browser plugin)', 'settings.control.route.option.serverLog', 'debug-console-php'),
        ),
        'type' => 'select',
    ),
    'wordpress' => array(
        'default' => ['core', 'cache', 'db', 'hooks', 'http', 'shortcodes'],
        'label' => 'WordPress',
        'options' => array( // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
            'core' => array(
                'label' => \_x('Query, template, post-type, show_on_front, etc', 'settings.control.wordpress.option.core', 'debug-console-php'),
                'name' => 'plugins[wordpress][enabled]',
            ),
            'cache' => array(
                'label' => \_x('Cache Information', 'settings.control.wordpress.option.cache', 'debug-console-php'),
                'name' => 'plugins[wordpressCache][enabled]',
            ),
            'db' => array(
                'label' => \_x('Database queries', 'settings.control.wordpress.option.db', 'debug-console-php'),
                'name' => 'plugins[wordpressDb][enabled]',
            ),
            'hooks' => array(
                'label' => \_x('Hooks', 'settings.control.wordpress.option.hooks', 'debug-console-php'),
                'name' => 'plugins[wordpressHooks][enabled]',
            ),
            'http' => array(
                'label' => \_x('HTTP requests', 'settings.control.wordpress.option.http', 'debug-console-php'),
                'name' => 'plugins[wordpressHttp][enabled]',
            ),
            'shortcodes' => array(
                'label' => \_x('Shortcodes', 'settings.control.wordpress.option.shortcodes', 'debug-console-php'),
                'name' => 'plugins[wordpressShortcodes][enabled]',
            ),
        ),
        'type' => 'checkbox',
    ),
    'logEnvInfo' => array(
        'default' => ['errorReporting', 'files', 'phpInfo', 'serverVals'],
        'label' => \_x('Log Environment', 'settings.control.logEnvInfo', 'debug-console-php'),
        'options' => array( // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
            'errorReporting' => \_x('Issues with error reporting', 'settings.control.logEnvInfo.option.errorReporting', 'debug-console-php'),
            'files' => \_x('Files included', 'settings.control.logEnvInfo.option.files', 'debug-console-php'),
            'phpInfo' => \_x('Php information', 'settings.control.logEnvInfo.option.phpInfo', 'debug-console-php'),
            'serverVals' => \_x('$_SERVER values', 'settings.control.logEnvInfo.option.serverVals', 'debug-console-php'),
        ),
        'type' => 'checkbox',
    ),
    'logResponse' => array(
        'default' => 'auto',
        'label' => \_x('Log Response', 'settings.control.logResponse', 'debug-console-php'),
        'options' => array( // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
            'auto' => \_x('Automatic', 'settings.control.logResponse.option.auto', 'debug-console-php'),
            'true' => \_x('Always', 'settings.control.logResponse.option.true', 'debug-console-php'),
            'false' => \_x('Never', 'settings.control.logResponse.option.false', 'debug-console-php'),
        ),
        'required' => true,
        'type' => 'radio',
    ),
    'logRequestInfo' => array(
        'default' => ['cookies', 'files', 'headers', 'post'],
        'label' => \_x('Log Request', 'settings.control.logRequestInfo', 'debug-console-php'),
        'options' => array( // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
            'headers' => \_x('Request headers', 'settings.control.logRequestInfo.option.headers', 'debug-console-php'),
            'cookies' => \_x('Cookies ($_COOKIE)', 'settings.control.logRequestInfo.option.cookies', 'debug-console-php'),
            'post' => \_x('Post data ($_POST)', 'settings.control.logRequestInfo.option.post', 'debug-console-php'),
            'files' => \_x('Posted files ($_FILES)', 'settings.control.logRequestInfo.option.files', 'debug-console-php'),
        ),
        'type' => 'checkbox',
    ),
    'logRuntime' => array(
        'default' => 'on',
        'label' => \_x('Log Runtime', 'settings.control.logRuntime', 'debug-console-php'),
        'options' => array(
            'on' => \_x('Log memory usage and request duration', 'settings.control.logRuntime.option.on', 'debug-console-php'),
        ),
        'type' => 'checkbox',
    ),

    'enableProfiling' => array(
        'describedBy' => \strtr(\_x('If DebugConsoleForPhp is collecting data <b>AND</b> this option is enabled, then we will set {declareTicks}<br /><b>Only enable this when needed</b>', 'settings.control.enableProfiling.describedBy', 'debug-console-php'), array(
            '{declareTicks}' => '<a target="_blank" href="https://www.php.net/manual/control-structures.declare.php#control-structures.declare.ticks"><code>declare(ticks=1);</code></a>',
        )),
        'label' => \_x('Enable Profile Method', 'settings.control.enableProfiling', 'debug-console-php'),
        'options' => array(
            'on' => \strtr(\_x('Enable DebugConsoleForPhp\'s <a target="_blank" href="{url}">profile method</a>', 'settings.control.enableProfiling.option.on', 'debug-console-php'), array(
                '{url}' => \esc_url('https://bradkent.com/php/debug#methodProfile'),
            )),
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
        'describedBy' => \_x('Maximum depth to traverse when logging objects/arrays.  0 = unlimited', 'settings.control.maxDepth.describedBy', 'debug-console-php'),
        'label' => \_x('Max Depth', 'settings.control.maxDepth', 'debug-console-php'),
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
        'describedBy' => \_x('Minimum time between notifications (in minutes)', 'settings.control.waitThrottle.describedBy', 'debug-console-php'),
        'label' => \_x('Throttle / Wait', 'settings.control.waitThrottle', 'debug-console-php'),
        'section' => 'errors',
        'type' => 'number',
    ),

    // email
    'enableEmailer' => array(
        // 'default' => 'on',
        'label' => \_x('Enable Email', 'settings.control.enableEmailer', 'debug-console-php'),
        'options' => array(
            'on' => \_x('Send errors to email', 'settings.control.enableEmailer.option.on', 'debug-console-php'),
        ),
        'section' => 'errors',
        'type' => 'checkbox',
    ),
    'emailTo' => array(
        'attribs' => array(
            'class' => 'regular-text',
        ),
        'default' => \get_option('admin_email'),
        'label' => \_x('Email To', 'settings.control.emailTo', 'debug-console-php'),
        'section' => 'errors',
        'type' => 'email',
        'wpTrClass' => 'indent',
    ),

    // discord
    'plugins[routeDiscord][enabled]' => array(
        'label' => \_x('Enable Discord', 'settings.control.routeDiscord', 'debug-console-php'),
        'options' => array(
            'on' => \_x('Send errors to Discord', 'settings.control.routeDiscord.option.on', 'debug-console-php'),
        ),
        'section' => 'errors',
        'type' => 'checkbox',
    ),
    'plugins[routeDiscord][webhookUrl]' => array(
        'attribs' => array(
            'class' => 'regular-text',
            'placeholder' => 'https://discord.com/api/webhooks/xxxxx',
        ),
        'describedBy' => \strtr(\_x('If left empty, will fall back to environment variable {envVar}', 'settings.controlCommon.defaultsToEnvVar', 'debug-console-php'), array(
            '{envVar}' => '<code>DISCORD_WEBHOOK_URL</code>',
        )) . '<br /><a href="https://support.discord.com/hc/articles/228383668-Intro-to-Webhooks" target="_blank">' . \_x('Discord webhook documentation', 'settings.control.routeDiscord.documentation', 'debug-console-php') . '</a>',
        'label' => \_x('Webhook URL', 'settings.controlCommon.webhookUrl', 'debug-console-php'),
        'section' => 'errors',
        'type' => 'url',
        'wpTrClass' => 'indent',
    ),

    'plugins[routeSlack][enabled]' => array(
        'describedBy' => 'Must configure either <a href="https://api.slack.com/messaging/webhooks" target="_blank">webhookUrl</a> or <a href="https://api.slack.com/tutorials/tracks/getting-a-token" target="_blank">token</a> &amp; channel',
        'label' => \_x('Enable Slack', 'settings.control.routeSlack', 'debug-console-php'),
        'options' => array(
            'on' => \_x('Send errors to Slack', 'settings.control.routeSlack.option.on', 'debug-console-php'),
        ),
        'section' => 'errors',
        'type' => 'checkbox',
    ),
    'plugins[routeSlack][webhookUrl]' => array(
        'attribs' => array(
            'class' => 'regular-text',
            'placeholder' => 'https://hooks.slack.com/services/xxxxx',
        ),
        'describedBy' => \strtr(\_x('If left empty, will fall back to environment variable {envVar}', 'settings.controlCommon.defaultsToEnvVar', 'debug-console-php'), array(
            '{envVar}' => '<code>SLACK_WEBHOOK_URL</code>',
        )),
        'label' => \_x('Webhook URL', 'settings.controlCommon.webhookUrl', 'debug-console-php'),
        'section' => 'errors',
        'type' => 'url',
        'wpTrClass' => 'indent',
    ),
    'plugins[routeSlack][token]' => array(
        'describedBy' => \strtr(\_x('If left empty, will fall back to environment variable {envVar}', 'settings.controlCommon.defaultsToEnvVar', 'debug-console-php'), array(
            '{envVar}' => '<code>SLACK_TOKEN</code>',
        )),
        'label' => \_x('Token', 'settings.control.routeSlackToken', 'debug-console-php'),
        'section' => 'errors',
        'type' => 'password',
        'wpTrClass' => 'indent',
    ),
    'plugins[routeSlack][channel]' => array(
        'attribs' => array(
            'placeholder' => '#myChannel',
        ),
        'describedBy' => \strtr(\_x('If left empty, will fall back to environment variable {envVar}', 'settings.controlCommon.defaultsToEnvVar', 'debug-console-php'), array(
            '{envVar}' => '<code>SLACK_CHANNEL</code>',
        )),
        'label' => \_x('Channel', 'settings.control.routeSlackChannel', 'debug-console-php'),
        'section' => 'errors',
        'wpTrClass' => 'indent',
    ),

    // Teams
    'plugins[routeTeams][enabled]' => array(
        'label' => \_x('Enable Teams', 'settings.control.routeTeams', 'debug-console-php'),
        'options' => array(
            'on' => \_x('Send errors to Teams', 'settings.control.routeTeams.option.on', 'debug-console-php'),
        ),
        'section' => 'errors',
        'type' => 'checkbox',
    ),
    'plugins[routeTeams][webhookUrl]' => array(
        'attribs' => array(
            'class' => 'regular-text',
            'placeholder' => 'https://qwerty.webhook.office.com/webhookb2/xxxxx',
        ),
        'describedBy' => \strtr(\_x('If left empty, will fall back to environment variable {envVar}', 'settings.controlCommon.defaultsToEnvVar', 'debug-console-php'), array(
            '{envVar}' => '<code>TEAMS_WEBHOOK_URL</code>',
        ))  . '<br /><a href="https://learn.microsoft.com/microsoftteams/platform/webhooks-and-connectors/how-to/add-incoming-webhook?tabs=newteams%2Cdotnet#create-an-incoming-webhook" target="_blank">' . \_x('Teams webhook documentation', 'settings.control.routeTeams.documentation', 'debug-console-php') . '</a>',
        'label' => \_x('Webhook URL', 'settings.controlCommon.webhookUrl', 'debug-console-php'),
        'section' => 'errors',
        'type' => 'url',
        'wpTrClass' => 'indent',
    ),
);
