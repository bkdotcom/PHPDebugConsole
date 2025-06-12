<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0b1
 */

namespace bdk\Debug;

/**
 * PHPDebugConsole autoloader
 */
class Autoloader
{
    /** @var array<string,string> */
    protected $classMap = array();

    /** @var array<string,string> */
    protected $psr4Map = array();

    /** @var bool */
    private $isRegistered = false;

    /**
     * Register autoloader
     *
     * @return bool
     */
    public function register()
    {
        if ($this->isRegistered) {
            // already registered
            return true;
        }
        $this->classMap = \array_unique(\array_merge($this->classMap, array(
            'bdk\\Backtrace' => __DIR__ . '/../Backtrace/Backtrace.php',
            'bdk\\Container' => __DIR__ . '/../Container/Container.php',
            'bdk\\Debug' => __DIR__ . '/Debug.php',
            'bdk\\Debug\\Utility' => __DIR__ . '/Utility/Utility.php',
            'bdk\\ErrorHandler' => __DIR__ . '/../ErrorHandler/ErrorHandler.php',
            'bdk\\I18n' => __DIR__ . '/../I18n/I18n.php',
            'bdk\\Promise' => __DIR__ . '/../Promise/Promise.php',
        )));
        $this->psr4Map = \array_unique(\array_merge($this->psr4Map, array(
            'bdk\\Backtrace\\' => __DIR__ . '/../Backtrace',
            'bdk\\Container\\' => __DIR__ . '/../Container',
            'bdk\\CurlHttpMessage\\' => __DIR__ . '/../CurlHttpMessage',
            'bdk\\Debug\\' => __DIR__,
            'bdk\\ErrorHandler\\' => __DIR__ . '/../ErrorHandler',
            'bdk\\I18n\\' => __DIR__ . '/../I18n',
            'bdk\\Promise\\' => __DIR__ . '/../Promise',
            'bdk\\PubSub\\' => __DIR__ . '/../PubSub',
            'bdk\\Slack\\' => __DIR__ . '/../Slack',
            'bdk\\Teams\\' => __DIR__ . '/../Teams',
            'bdk\\Test\\Debug\\' => __DIR__ . '/../../tests/Debug',
        )));
        $this->isRegistered = true;
        $this->sortPsr4();
        return \spl_autoload_register([$this, 'autoload']);
    }

    /**
     * Remove autoloader
     *
     * @return bool
     */
    public function unregister()
    {
        $this->isRegistered = false;
        return \spl_autoload_unregister([$this, 'autoload']);
    }

    /**
     * Add classname to classMap
     *
     * @param string $className ClassName
     * @param string $filepath  Filepath to class' definition
     *
     * @return static
     */
    public function addClass($className, $filepath)
    {
        $this->classMap[$className] = $filepath;
        return $this;
    }

    /**
     * Add Psr4 mapping to autoloader
     *
     * @param string $namespace Namespace prefix
     * @param string $dir       Directory containing namespace
     *
     * @return static
     */
    public function addPsr4($namespace, $dir)
    {
        $this->psr4Map[$namespace] = $dir;
        $this->sortPsr4();
        return $this;
    }

    /**
     * Debug class autoloader
     *
     * @param string $className classname to attempt to load
     *
     * @return void
     */
    protected function autoload($className)
    {
        $className = \ltrim($className, '\\'); // leading backslash _shouldn't_ have been passed
        $filepath = $this->findClass($className);
        if ($filepath) {
            require $filepath;
        }
    }

    /**
     * Find file containing class
     *
     * @param string $className classname to find
     *
     * @return string|false
     */
    private function findClass($className)
    {
        if (isset($this->classMap[$className])) {
            return $this->classMap[$className];
        }
        foreach ($this->psr4Map as $namespace => $dir) {
            if (\strpos($className, $namespace) === 0) {
                $rel = \substr($className, \strlen($namespace));
                $rel = \str_replace('\\', '/', $rel);
                return $dir . '/' . $rel . '.php';
            }
        }
        return false;
    }

    /**
     * Sort PSR-4 mappings by length of namespace descending
     * This ensures that more specific namespaces are matched first
     *
     * @return void
     */
    private function sortPsr4()
    {
        if ($this->isRegistered === false) {
            // no need to sort until registered
            return;
        }
        $keyLengths = \array_map('strlen', \array_keys($this->psr4Map));
        \array_multisort($keyLengths, SORT_DESC, $this->psr4Map);
    }
}
