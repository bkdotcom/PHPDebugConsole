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
    protected $classMap = array(
        'bdk\\Backtrace' => '../Backtrace/Backtrace.php',
        'bdk\\Container' => '../Container/Container.php',
        'bdk\\Debug' => './Debug.php',
        'bdk\\Debug\\Utility' => './Utility/Utility.php',
        'bdk\\ErrorHandler' => '../ErrorHandler/ErrorHandler.php',
        'bdk\\I18n' => '../I18n/I18n.php',
        'bdk\\Promise' => '../Promise/Promise.php',
    );

    /** @var array<string,string> */
    protected $psr4Map = array(
        'bdk\\Backtrace\\' => '../Backtrace',
        'bdk\\Container\\' => '../Container',
        'bdk\\CurlHttpMessage\\' => '../CurlHttpMessage',
        'bdk\\Debug\\' => '.',
        'bdk\\ErrorHandler\\' => '../ErrorHandler',
        'bdk\\I18n\\' => '../I18n',
        'bdk\\Promise\\' => '../Promise',
        'bdk\\PubSub\\' => '../PubSub',
        'bdk\\Slack\\' => '../Slack',
        'bdk\\Teams\\' => '../Teams',
        'bdk\\Test\\Debug\\' => '../../tests/Debug',
    );

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

        $this->classMap = \array_map([$this, 'resolveFilepath'], $this->classMap);
        $this->psr4Map = \array_map([$this, 'resolveFilepath'], $this->psr4Map);
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
     * Resolve relative paths
     *
     * @param string $filepath Filepath to resolve
     *
     * @return string
     */
    private function resolveFilepath($filepath)
    {
        if (\strpos($filepath, '..') === 0) {
            $pathParts = \explode(DIRECTORY_SEPARATOR, __DIR__);
            \array_pop($pathParts); // remove last part
            $filepath = implode('/', $pathParts) . '/' . \ltrim(\substr($filepath, 2), '/');
        } elseif (\strpos($filepath, '.') === 0) {
            $filepath = __DIR__ . '/' . \ltrim(\substr($filepath, 1), '/');
        }
        return \rtrim($filepath, '/');
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
