<?php

namespace bdk\Debug\Framework\WordPress;

use bdk\Debug;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;

/**
 * Capture calls to deprecated functions, methods, classes, and files
 */
class Deprecated implements SubscriberInterface
{
    /** @var Debug */
    protected $debug;

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

        \add_action('deprecated_argument_run', [$this, 'onDeprecatedArgument'], 0, 3);
        \add_action('deprecated_class_run', [$this, 'onDeprecatedConstructor'], 0, 3);
        \add_action('deprecated_constructor_run', [$this, 'onDeprecatedConstructor'], 0, 3);
        \add_action('deprecated_file_included', [$this, 'onDeprecatedFile'], 0, 4);
        \add_action('deprecated_function_run', [$this, 'onDeprecatedFunction'], 0, 3);
        \add_action('deprecated_hook_run', [$this, 'onDeprecatedHook'], 0, 3);

        // Don't trigger E_NOTICE for deprecated usage.
        foreach (['argument', 'class', 'constructor', 'file', 'function', 'hook'] as $item) {
            \add_filter('deprecated_' . $item . '_trigger_error', static function () {
                return false;
            });
        }

        // require ABSPATH . '/wp-includes/class-json.php';
        // $json = new \Services_JSON();
    }

    /**
     * Called when a deprecated argument is used.
     *
     * @param string $function The function that was called.
     * @param string $message  A message regarding the change.
     * @param string $version  The version of WordPress that deprecated the argument used.
     *
     * @return void
     */
    public function onDeprecatedArgument($function, $message, $version)
    {
        $message = \trim(
            \strtr(\_x('{function} was called with an argument that is deprecated since version {version}.', 'deprecated.func.arg', 'debug-console-php'), array(
                '{function}' => $function,
                '{version}' => $version,
            )) . '  ' . $message
        );
        $this->warn($message);
    }

    /**
     * Called when a deprecated class is called.
     *
     * @param string $old         The name of the class being instantiated.
     * @param string $replacement The class or function that should have been called.
     * @param string $version     The version of WordPress that deprecated the class.
     *
     * @return void
     */
    public function onDeprecatedClass($old, $replacement, $version)
    {
        $messageArgs = array(
            '{old}' => $old,
            '{replacement}' => $replacement,
            '{version}' => $version,
        );
        $message = $replacement
            ? \_x('{old} is deprecated since version {version}.  Use {replacement} instead.', 'deprecated.has-replacement', 'debug-console-php')
            : \_x('{old} is deprecated since version {version}.  No alternative available.', 'deprecated.no-replacement', 'debug-console-php');
        $message = \strtr($message, $messageArgs);
        $this->warn($message);
    }

    /**
     * Called when a deprecated constructor is called.
     *
     * @param string $className   The class containing the deprecated constructor.
     * @param string $version     The version of WordPress that deprecated the function.
     * @param string $parentClass The parent class calling the deprecated constructor.
     *
     * @return void
     */
    public function onDeprecatedConstructor($className, $version, $parentClass)
    {
        $messageArgs = array(
            '{old}' => $className,
            '{parentClass}' => $parentClass,
            '{replacement}' => '__construct',
            '{version}' => $version,
        );
        $message = $parentClass && $parentClass !== $className
            ? \_x('The called constructor method {old} in {parentClass} is deprecated since version {version}.  Use {replacement} instead.', 'deprecated.constructor.parent-class', 'debug-console-php')
            : \_x('The called constructor method {old} is deprecated since version {version}.  Use {replacement} instead.', 'deprecated.constructor', 'debug-console-php');
        $message = \strtr($message, $messageArgs);
        $this->warn($message);
    }

    /**
     * Called when a deprecated file is included
     *
     * @param string $old         The file that was included
     * @param string $replacement The file that should have been included based on ABSPATH.
     * @param string $version     The version of WordPress that deprecated the file.
     * @param string $message     A message regarding the change.
     *
     * @return void
     */
    public function onDeprecatedFile($old, $replacement, $version, $message = '')
    {
        $messageArgs = array(
            '{old}' => $old,
            '{replacement}' => $replacement,
            '{version}' => $version,
        );
        $message = \trim(($replacement
            ? \_x('{old} is deprecated since version {version}.  Use {replacement} instead.', 'deprecated.has-replacement', 'debug-console-php')
            : \_x('{old} is deprecated since version {version}.  No alternative available.', 'deprecated.no-replacement', 'debug-console-php')
        ) . '  ' . $message);
        $message = \strtr($message, $messageArgs);
        $this->warn($message);
    }

    /**
     * Called when a deprecated function is called.
     *
     * @param string $old         The deprecated function that was called.
     * @param string $replacement The function that should have been called.
     * @param string $version     The version of WordPress that deprecated the function.
     *
     * @return void
     */
    public function onDeprecatedFunction($old, $replacement, $version)
    {
        $messageArgs = array(
            '{old}' => $old,
            '{replacement}' => $replacement,
            '{version}' => $version,
        );
        $message = $replacement
            ? \_x('{old} is deprecated since version {version}.  Use {replacement} instead.', 'deprecated.has-replacement', 'debug-console-php')
            : \_x('{old} is deprecated since version {version}.  No alternative available.', 'deprecated.no-replacement', 'debug-console-php');
        $message = \strtr($message, $messageArgs);
        $this->warn($message);
    }

    /**
     * Called when a deprecated hook is used.
     *
     * @param string $old         The deprecated hook that was used.
     * @param string $replacement The hook that should be used as a replacement.
     * @param string $version     The version of WordPress that deprecated the argument used.
     * @param string $message     A message regarding the change.
     *
     * @return void
     */
    public function onDeprecatedHook($old, $replacement, $version, $message = '')
    {
        $messageArgs = array(
            '{old}' => $old,
            '{replacement}' => $replacement,
            '{version}' => $version,
        );
        $message = \trim(($replacement
            ? \_x('{old} is deprecated since version {version}.  Use {replacement} instead.', 'deprecated.has-replacement', 'debug-console-php')
            : \_x('{old} is deprecated since version {version}.  No alternative available.', 'deprecated.no-replacement', 'debug-console-php')
        ) . '  ' . $message);
        $message = \strtr($message, $messageArgs);
        $this->warn($message);
    }

    /**
     * Call warn with meta info
     *
     * @param string $message Message to log
     *
     * @return void
     */
    private function warn($message)
    {
        $caller = $this->getCaller();
        $this->debug->warn($message, $this->debug->meta(array(
            'file' => $caller['file'],
            'icon' => 'fa fa-arrow-down',
            'line' => $caller['line'],
        )));
    }

    /**
     * Find the caller of the deprecated whatever
     *
     * @return array|false
     */
    private function getCaller()
    {
        $backtrace = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8);
        $backtrace = \array_slice($backtrace, 3); // we can safely ignore the first few frames
        $foundDeprecated = false;
        foreach ($backtrace as $frame) {
            $function = $frame['function'];
            if (\strpos($function, '_deprecated_') === 0) {
                $foundDeprecated = true;
            } elseif ($foundDeprecated) {
                return $frame;
            }
        }
        return false;
    }
}
