<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0b1
 */

namespace bdk\Debug;

/**
 * PHPDebugConsole autoloader
 *
 * Initial mapping assumes bdk/http-message has been installed as if via composer
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
        'SqlFormatter' => '{vendor}/jdorn/sql-formatter/lib/SqlFormatter.php',
    );

    /** @var array<string,string|list<string>> */
    protected $psr4Map = array(
        'bdk\\Backtrace\\' => '../Backtrace',
        'bdk\\Container\\' => '../Container',
        'bdk\\CurlHttpMessage\\' => '../CurlHttpMessage',
        'bdk\\Debug\\' => '.',
        'bdk\\ErrorHandler\\' => '../ErrorHandler',
        'bdk\\HttpMessage\\' => '{vendor}/bdk/http-message/src/HttpMessage',
        'bdk\\I18n\\' => '../I18n',
        'bdk\\Promise\\' => '../Promise',
        'bdk\\PubSub\\' => '../PubSub',
        'bdk\\Slack\\' => '../Slack',
        'bdk\\Teams\\' => '../Teams',
        'bdk\\Test\\Debug\\' => '../../tests/Debug',
        'Psr\\Http\\Message\\' => [
            '{vendor}/psr/http-message/src',
            '{vendor}/psr/http-factory/src',
        ],
    );

    /** @var bool */
    private $isRegistered = false;

    /** @var string */
    private $vendorDir;

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
        $this->classMap[$className] = $this->resolveFilepath($filepath);
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
        $this->psr4Map[$namespace] = $this->resolveFilepath($dir);
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
                return $this->findRelativePath($rel, $dir);
            }
        }
        return false;
    }

    /**
     * Search for file in PSR-4 dir(s)
     *
     * @param string              $rel Relative namespace path
     * @param string|list<string> $dir Directory or directories to search
     *
     * @return string|false
     */
    private function findRelativePath($rel, $dir)
    {
        $rel = \str_replace('\\', '/', $rel);
        foreach ((array) $dir as $dir) {
            $dir = \rtrim($dir, '/');
            $filepath = $dir . '/' . $rel . '.php';
            if (\is_file($filepath)) {
                return $filepath;
            }
        }
        return false;
    }

    /**
     * Resolve relative paths
     *
     * @param string|list<string> $filepath Filepath(s) to resolve
     *
     * @return string
     */
    private function resolveFilepath($filepath)
    {
        if (\is_array($filepath)) {
            return \array_map([$this, 'resolveFilepath'], $filepath);
        }
        if (\strpos($filepath, '..') === 0) {
            $pathParts = \explode(DIRECTORY_SEPARATOR, __DIR__);
            \array_pop($pathParts); // remove last part
            $filepath = \implode('/', $pathParts) . '/' . \ltrim(\substr($filepath, 2), '/');
            return \rtrim($filepath, '/');
        }
        if (\strpos($filepath, '.') === 0) {
            $filepath = __DIR__ . '/' . \ltrim(\substr($filepath, 1), '/');
            return \rtrim($filepath, '/');
        }
        $filepath = \strtr($filepath, array(
            '{vendor}' => $this->resolveVendorDir(),
        ));
        return \rtrim($filepath, '/');
    }

    /**
     * Find vendor directory
     *
     * @return string
     */
    private function resolveVendorDir()
    {
        if ($this->vendorDir) {
            return $this->vendorDir;
        }
        $path = __DIR__;
        do {
            $path = \dirname($path);
        } while ($path && \is_dir($path . '/vendor') === false);
        $this->vendorDir = $path . '/vendor';
        return $this->vendorDir;
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
