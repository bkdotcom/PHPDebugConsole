<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug;

/**
 * PHPDebugConsole autoloader
 */
class Autoloader
{
    protected $classMap = array();
    protected $psr4Map = array();

    /**
     * Register autoloader
     *
     * @return bool
     */
    public function register()
    {
        $this->psr4Map = array(
            'bdk\\Backtrace\\' => __DIR__ . '/../Backtrace',
            'bdk\\Container\\' => __DIR__ . '/../Container',
            'bdk\\Debug\\' => __DIR__,
            'bdk\\ErrorHandler\\' => __DIR__ . '/../ErrorHandler',
            'bdk\\HttpMessage\\' => __DIR__ . '/../HttpMessage',
            'bdk\\PubSub\\' => __DIR__ . '/../PubSub',
            'bdk\\Test\\Debug\\' => __DIR__ . '/../../tests/Debug',
            'Psr\\Http\\Message\\' => __DIR__ . '/../Psr7',
        );
        $this->classMap = array(
            'bdk\\Backtrace' => __DIR__ . '/../Backtrace/Backtrace.php',
            'bdk\\Container' => __DIR__ . '/../Container/Container.php',
            'bdk\\Debug' => __DIR__ . '/Debug.php',
            'bdk\\Debug\\Utility' => __DIR__ . '/Utility/Utility.php',
            'bdk\\ErrorHandler' => __DIR__ . '/../ErrorHandler/ErrorHandler.php',
        );
        return \spl_autoload_register(array($this, 'autoload'));
    }

    /**
     * Remove autoloader
     *
     * @return bool
     */
    public function unregister()
    {
        return \spl_autoload_unregister(array($this, 'autoload'));
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
}
