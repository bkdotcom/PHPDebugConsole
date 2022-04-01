<?php

namespace bdk\Test\Debug\Method;

use bdk\Debug;
use bdk\Test\Debug\DebugTestFramework;

/**
 * PHPUnit tests for Debug::time() methods
 *
 * @covers \bdk\Debug
 * @covers \bdk\Debug\Method\Helper
 */
class AlertTest extends DebugTestFramework
{
    public function providerTestMethod()
    {
        $return = array();

        $message = 'Ballistic missle threat inbound to Hawaii.  <b>Seek immediate shelter</b>.  This is not a drill.';
        $messageEscaped = \htmlspecialchars($message);
        $entryExpect = array(
            'method' => 'alert',
            'args' => array(
                $message,
            ),
            'meta' => array(
                'dismissible' => false,
                'level' => 'error',
            ),
        );

        $entryExpect['meta']['level'] = 'error';
        $style = 'padding: 5px; line-height: 26px; font-size: 125%; font-weight: bold; background-color: #ffbaba; border: 1px solid #d8000c; color: #d8000c;';
        $return['error'] = array(
            'alert',
            array(
                $message,
                // level error by default
            ),
            array(
                'entry' => $entryExpect,
                'chromeLogger' => array(
                    array(
                        '%c' . $message,
                        $style,
                    ),
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-1: %d|[{"Type":"ERROR"},' . \json_encode($message, JSON_UNESCAPED_SLASHES) . ']|',
                'html' => '<div class="alert-error m_alert" role="alert">' . $messageEscaped . '</div>',
                'script' => \str_replace('%c', '%%c', 'console.log(' . \json_encode('%c' . $message, JSON_UNESCAPED_SLASHES) . ',"padding: 5px; line-height: 26px; font-size: 125%; font-weight: bold; background-color: #ffbaba; border: 1px solid #d8000c; color: #d8000c;");'),
                'text' => '》[Alert ⦻ error] ' . $message . '《',
                'wamp' => $entryExpect,
            )
        );

        $entryExpect['meta']['level'] = 'info';
        $style = 'padding: 5px; line-height: 26px; font-size: 125%; font-weight: bold; background-color: #d9edf7; border: 1px solid #bce8f1; color: #31708f;';
        $return['info'] = array(
            'alert',
            array(
                $message,
                Debug::meta('level', 'info'),
            ),
            array(
                'entry' => $entryExpect,
                'chromeLogger' => array(
                    array(
                        '%c' . $message,
                        $style,
                    ),
                    null,
                    'info',
                ),
                'firephp' => 'X-Wf-1-1-1-1: %d|[{"Type":"INFO"},' . \json_encode($message, JSON_UNESCAPED_SLASHES) . ']|',
                'html' => '<div class="alert-info m_alert" role="alert">' . $messageEscaped . '</div>',
                'script' => \str_replace('%c', '%%c', 'console.info(' . \json_encode('%c' . $message, JSON_UNESCAPED_SLASHES) . ',"' . $style . '");'),
                'text' => '》[Alert ℹ info] ' . $message . '《',
                'wamp' => $entryExpect,
            )
        );

        $entryExpect['meta']['level'] = 'success';
        $style = 'padding: 5px; line-height: 26px; font-size: 125%; font-weight: bold; background-color: #dff0d8; border: 1px solid #d6e9c6; color: #3c763d;';
        $return['success'] = array(
            'alert',
            array(
                $message,
                Debug::meta('level', 'success'),
            ),
            array(
                'entry' => $entryExpect,
                'chromeLogger' => array(
                    array(
                        '%c' . $message,
                        $style,
                    ),
                    null,
                    'info',
                ),
                'firephp' => 'X-Wf-1-1-1-1: %d|[{"Type":"INFO"},' . \json_encode($message, JSON_UNESCAPED_SLASHES) . ']|',
                'html' => '<div class="alert-success m_alert" role="alert">' . $messageEscaped . '</div>',
                'script' => \str_replace('%c', '%%c', 'console.info(' . \json_encode('%c' . $message, JSON_UNESCAPED_SLASHES) . ',"' . $style . '");'),
                'text' => '》[Alert ℹ success] ' . $message . '《',
                'wamp' => $entryExpect,
            )
        );

        $entryExpect['meta']['level'] = 'warn';
        $style = 'padding: 5px; line-height: 26px; font-size: 125%; font-weight: bold; background-color: #fcf8e3; border: 1px solid #faebcc; color: #8a6d3b;';
        $return['warn'] = array(
            'alert',
            array(
                $message,
                Debug::meta('level', 'warn'),
            ),
            array(
                'entry' => $entryExpect,
                'chromeLogger' => array(
                    array(
                        '%c' . $message,
                        $style,
                    ),
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-1: %d|[{"Type":"WARN"},' . \json_encode($message, JSON_UNESCAPED_SLASHES) . ']|',
                'html' => '<div class="alert-warn m_alert" role="alert">' . $messageEscaped . '</div>',
                'script' => \str_replace('%c', '%%c', 'console.log(' . \json_encode('%c' . $message, JSON_UNESCAPED_SLASHES) . ',"' . $style . '");'),
                'text' => '》[Alert ⚠ warn] ' . $message . '《',
                'wamp' => $entryExpect,
            )
        );

        // test alias
        $entryExpect['meta']['level'] = 'warn';
        $style = 'padding: 5px; line-height: 26px; font-size: 125%; font-weight: bold; background-color: #fcf8e3; border: 1px solid #faebcc; color: #8a6d3b;';
        $return['levelAlias'] = array(
            'alert',
            array(
                $message,
                Debug::meta('level', 'warning'),
            ),
            array(
                'entry' => $entryExpect,
                'chromeLogger' => array(
                    array(
                        '%c' . $message,
                        $style,
                    ),
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-1: %d|[{"Type":"WARN"},' . \json_encode($message, JSON_UNESCAPED_SLASHES) . ']|',
                'html' => '<div class="alert-warn m_alert" role="alert">' . $messageEscaped . '</div>',
                'script' => \str_replace('%c', '%%c', 'console.log(' . \json_encode('%c' . $message, JSON_UNESCAPED_SLASHES) . ',"' . $style . '");'),
                'text' => '》[Alert ⚠ warn] ' . $message . '《',
                'wamp' => $entryExpect,
            )
        );

        // test invalid
        $entryExpect['meta']['level'] = 'error';
        $style = 'padding: 5px; line-height: 26px; font-size: 125%; font-weight: bold; background-color: #ffbaba; border: 1px solid #d8000c; color: #d8000c;';
        $return['levelInvalid'] = array(
            'alert',
            array(
                $message,
                Debug::meta('level', 'bogus'),
            ),
            array(
                'entry' => $entryExpect,
                'chromeLogger' => array(
                    array(
                        '%c' . $message,
                        $style,
                    ),
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-1: %d|[{"Type":"ERROR"},' . \json_encode($message, JSON_UNESCAPED_SLASHES) . ']|',
                'html' => '<div class="alert-error m_alert" role="alert">' . $messageEscaped . '</div>',
                'script' => \str_replace('%c', '%%c', 'console.log(' . \json_encode('%c' . $message, JSON_UNESCAPED_SLASHES) . ',"' . $style . '");'),
                'text' => '》[Alert ⦻ error] ' . $message . '《',
                'wamp' => $entryExpect,
            )
        );

        $args = array(
            '%s %s %s %s %s %s',
            'plain ol string',
            array(0),
            array(),
            null,
            true,
            false,
        );
        $return['substitutions'] = array(
            'alert',
            $args,
            array(
                'entry' => array(
                    'method' => 'alert',
                    'args' => $args,
                    'meta' => array(
                        'dismissible' => false,
                        'level' => 'error',
                    ),
                ),
                'chromeLogger' => array(
                    $args,
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-19: 89|[{"Label":"%s %s %s %s %s %s","Type":"ERROR"},["plain ol string",[0],[],null,true,false]]|',
                'html' => '<div class="alert-error m_alert" role="alert">'
                    . 'plain ol string'
                    . ' <span class="t_keyword">array</span><span class="t_punct">(</span>1<span class="t_punct">)</span>'
                    . ' <span class="t_keyword">array</span><span class="t_punct">(</span>0<span class="t_punct">)</span>'
                    . ' <span class="t_null">null</span>'
                    . ' <span class="t_bool" data-type-more="true">true</span>'
                    . ' <span class="t_bool" data-type-more="false">false</span>'
                    . '</div>',
                'script' => 'console.log("%s %s %s %s %s %s","plain ol string",[0],[],null,true,false);',
                'text' => '》[Alert ⦻ error] plain ol string array(1) array(0) null true false《',
                'wamp' => array(
                    'alert',
                    $args,
                    array(
                        'dismissible' => false,
                        'level' => 'error',
                    ),
                ),
            ),
        );

        return $return;
    }

    public function testCollectFalse()
    {
        $this->debug->setCfg('collect', false);
        $this->testMethod(
            'alert',
            array('Unseen alert'),
            array(
                'notLogged' => true,
                'return' => $this->debug,
            )
        );
    }
}
