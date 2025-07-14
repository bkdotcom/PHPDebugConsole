<?php

namespace bdk\Test\Debug\Framework\WordPress;

use bdk\Debug;
use bdk\PhpUnitPolyfill\AssertionTrait;
use bdk\PubSub\Event;
use bdk\Test\Debug\DebugTestFramework;

/**
 * @covers \bdk\Debug\Framework\WordPress\Deprecated
 */
class DeprecatedTest extends DebugTestFramework
{
    use AssertionTrait;

    protected static $plugin;

    public static function setUpBeforeClass(): void
    {
        if (PHP_VERSION_ID < 70000) {
            self::markTestSkipped('Wordpress requires PHP 7.0+');
            return;
        }
        parent::setUpBeforeClass();
        if (!\function_exists('get_option')) {
            require_once __DIR__ . '/mock_wordpress.php';
        }
        wp_reset_mock();
        self::resetDebug();
        self::$plugin = new \bdk\Debug\Framework\WordPress\Deprecated();
        self::$plugin->onBootstrap(new Event(self::$debug));
    }

    public function testGetSubscriptions()
    {
        self::assertInstanceOf('bdk\PubSub\SubscriberInterface', self::$plugin);
        self::assertSame([
            Debug::EVENT_BOOTSTRAP => 'onBootstrap',
        ], self::$plugin->getSubscriptions());
    }

    public function testOnBootstrap()
    {
        wp_reset_mock();
        self::$plugin->onBootstrap(new Event(self::$debug));
        // $objectHash = \spl_object_hash(self::$plugin);
        self::assertSame(array(
            'deprecated_argument_run' => [[self::$plugin, 'onDeprecatedArgument']],
            'deprecated_class_run' => [[self::$plugin, 'onDeprecatedConstructor']],
            'deprecated_constructor_run' => [[self::$plugin, 'onDeprecatedConstructor']],
            'deprecated_file_included' => [[self::$plugin, 'onDeprecatedFile']],
            'deprecated_function_run' => [[self::$plugin, 'onDeprecatedFunction']],
            'deprecated_hook_run' => [[self::$plugin, 'onDeprecatedHook']],
        ), $GLOBALS['wp_actions_filters']['actions']);
        $filterKeys = [
            'deprecated_argument_trigger_error',
            'deprecated_class_trigger_error',
            'deprecated_constructor_trigger_error',
            'deprecated_file_trigger_error',
            'deprecated_function_trigger_error',
            'deprecated_hook_trigger_error',
        ];
        self::assertSame($filterKeys, \array_keys($GLOBALS['wp_actions_filters']['filters']));
        foreach ($filterKeys as $key) {
            self::assertCount(1, $GLOBALS['wp_actions_filters']['filters'][$key]);
            self::assertInstanceOf('Closure', $GLOBALS['wp_actions_filters']['filters'][$key][0]);
            self::assertFalse($GLOBALS['wp_actions_filters']['filters'][$key][0]());
        }
    }

    /**
     * @dataProvider providerOnDeprecated
     */
    public function testOnDeprecated($type, $args, $msgExpected)
    {
        $method = 'onDeprecated' . \ucfirst($type);
        $line = __LINE__ + 1;
        $this->deprecatedWhatever($method, $args);
        self::assertSame(array(
            'method' => 'warn',
            'args' => array(
                $msgExpected,
            ),
            'meta' => array(
                'detectFiles' => true,
                'file' => __FILE__,
                'icon' => 'fa fa-arrow-down',
                'line' => $line,
                'uncollapse' => true,
            ),
        ), $this->helper->logEntryToArray($this->debug->data->get('log/__end__')));
    }

    public static function providerOnDeprecated()
    {
        return array(
            'argument.1' => array('argument', ['someFunction', 'message  ', '1.2.3'], 'someFunction was called with an argument that is deprecated since version 1.2.3.  message'),
            'argument.2' => array('argument', ['someFunction', '', '1.2.3'], 'someFunction was called with an argument that is deprecated since version 1.2.3.'),
            'class' => array('class', ['oldClass', 'newClass', '1.2.3'], 'oldClass is deprecated since version 1.2.3.  Use newClass instead.'),
            'class.no-replacement' => array('class', ['oldClass', '', '1.2.3'], 'oldClass is deprecated since version 1.2.3.  No alternative available.'),
            'constructor.1' => array('constructor', ['classname', '1.2.3', 'parentClass'], 'The called constructor method classname in parentClass is deprecated since version 1.2.3.  Use __construct instead.'),
            'constructor.2' => array('constructor', ['classname', '1.2.3', ''], 'The called constructor method classname is deprecated since version 1.2.3.  Use __construct instead.'),
            'file.1' => array('file', ['oldFile', 'replacementFile', '1.2.3', 'message  '], 'oldFile is deprecated since version 1.2.3.  Use replacementFile instead.  message'),
            'file.2' => array('file', ['oldFile', 'replacementFile', '1.2.3', ''], 'oldFile is deprecated since version 1.2.3.  Use replacementFile instead.'),
            'file.no-replacement' => array('file', ['oldFile', '', '1.2.3', ''], 'oldFile is deprecated since version 1.2.3.  No alternative available.'),
            'function' => array('function', ['old', 'replacement', '1.2.3'], 'old is deprecated since version 1.2.3.  Use replacement instead.'),
            'function.no-replacement' => array('function', ['old', '', '1.2.3'], 'old is deprecated since version 1.2.3.  No alternative available.'),
            'hook.1' => array('hook', ['old', 'replacement', '1.2.3', 'message  '], 'old is deprecated since version 1.2.3.  Use replacement instead.  message'),
            'hook.2' => array('hook', ['old', 'replacement', '1.2.3', ''], 'old is deprecated since version 1.2.3.  Use replacement instead.'),
            'hook.no-replacement' => array('hook', ['old', '', '1.2.3', ''], 'old is deprecated since version 1.2.3.  No alternative available.'),
        );
    }

    private function deprecatedWhatever($method, $args)
    {
        $this->_deprecated_proxy($method, $args);
    }

    private function _deprecated_proxy($method, $args)
    {
        \call_user_func_array(array(self::$plugin, $method), $args);
    }
}
