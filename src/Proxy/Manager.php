<?php

namespace bdk\Proxy;

use bdk\Cache\FileSystem as FileSystemCache;
use InvalidArgumentException;
use Psr\SimpleCache\CacheInterface;
use ReflectionClass;
use ReflectionProperty;

/**
 * Proxy Manager
 */
class Manager
{
    /** @var string A suffix appended to proxy class names / files. */
    const PROXY_SUFFIX = 'Proxy';

    /** @var ProxiedClassBuilder A class builder dependency. */
    private $proxiedClassBuilder;

    /** @var ClassDefFactory A class definition factory. */
    private $classDefFactory;

    /** @var FileSystemCache|null */
    private $cache;

    /** @var array List of classes that can not be truly proxied. */
    private $extendOnlyClasses = [
        'mysqli', // cannot proxy mysqli properties
    ];

    /**
     * Constructor
     *
     * @param CacheInterface|null $cache Optional PSR-16 (SimpleCache) instance
     */
    public function __construct($cache = null)
    {
        $this->setCache($cache);
        $this->classDefFactory = new ClassDefFactory();
        $this->proxiedClassBuilder = new ProxiedClassBuilder();
    }

    /**
     * Adds a class to the list of classes that should only be extended.
     *
     * @param string $className The class name to add.
     *
     * @return void
     *
     * @throws InvalidArgumentException If the provided class name is not a string
     */
    public function addExtendOnlyClass($className)
    {
        if (!\is_string($className)) {
            throw new InvalidArgumentException('Class name must be a string');
        }
        $this->extendOnlyClasses[] = $className;
    }

    /**
     * Creates an object that proxies the given subject.
     *
     * @param object $subject The object to be proxied.
     *
     * @return object The created proxy instance.
     *
     * @throws InvalidArgumentException If the provided subject is not an object
     */
    public function buildFromSubject($subject)
    {
        if (!\is_object($subject)) {
            throw new InvalidArgumentException('Subject must be an object');
        }
        $subjectClassName = \get_class($subject);
        return $this->buildFromClassName($subjectClassName)
            ->setSubject($subject);
    }

    /**
     * Get cache instance
     *
     * @return CacheInterface|null PSR-16 (SimpleCache) instance (or null)
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * Set cache instance
     *
     * @param CacheInterface|null $cache PSR-16 (SimpleCache) instance (or null)
     *
     * @return void
     *
     * @throws InvalidArgumentException If provided cache does not implement CacheInterface
     */
    public function setCache($cache)
    {
        $isValidCache = $cache instanceof CacheInterface;
        if ($cache !== null && !$isValidCache) {
            throw new InvalidArgumentException('Cache must implement Psr\SimpleCache\CacheInterface');
        }
        $this->cache = $cache;
    }

    /**
     * Creates an object the proxies the given class
     *
     * Note that the created proxy will not have a subject set,
     * so it will not be functional until a subject is assigned to it.
     *
     * @param class-string $className The class or interface name to be proxied.
     *
     * @return object The created proxy instance.
     *
     * @throws InvalidArgumentException If the provided class name is not a string
     */
    public function buildFromClassName($className)
    {
        if (!\is_string($className)) {
            throw new InvalidArgumentException('Class name must be a string');
        }
        $this->autoloadProxyClass($className);
        $proxyClassName = self::getProxyShortName($className);
        return $this->instantiateProxy($proxyClassName);
    }

    /**
     * Builds and loads proxy class for given subject class name.
     *
     * @param class-string $subjectClassName Fully qualified class or interface name
     *
     * @return void
     */
    public function autoloadProxyClass($subjectClassName)
    {
        $proxyClassName = self::getProxyShortName($subjectClassName);

        if (\class_exists($proxyClassName, false)) {
            // already defined (in memory)
            return;
        }

        $classDeclaration = null;

        if ($this->cache) {
            $classDeclaration = $this->cache->get($proxyClassName);
        }

        if (!empty($GLOBALS['turd']) && $this->cache) {
            echo \bdk\Debug\Utility\Reflection::propGet($this->cache->getSubject(), 'directory') . ' ' . $proxyClassName . "\n";
        }
        if (!$classDeclaration) {
            if (!empty($GLOBALS['turd'])) {
                echo 'not in cache' . "\n";
            }
            $classDef = $this->getClassDef($subjectClassName);
            $classDeclaration = $this->proxiedClassBuilder->build($classDef);
            if ($this->cache) {
                $this->cache->set($proxyClassName, $classDeclaration);
            }
        }

        if (!empty($GLOBALS['turd'])) {
            echo $classDeclaration . "\n\n";
        }

        // @phpcs:ignore Squiz.PHP.Eval.Discouraged
        eval($classDeclaration);
    }

    /**
     * Get class definition with proxy-specific modifications for given subject class name.
     *
     * @param class-string $subjectClassName Fully qualified class or interface name
     *
     * @return array
     */
    public function getClassDef($subjectClassName)
    {
        $classDef = $this->classDefFactory->getClassDef($subjectClassName);
        return $this->modifyClassDef($classDef);
    }

    /**
     * Transforms full class / interface name with namespace to short class name used by proxy class.
     *
     * @param string $className Initial class name.
     *
     * @return string Proxy class name.
     */
    public static function getProxyShortName($className)
    {
        return \str_replace('\\', '_', $className) . self::PROXY_SUFFIX;
    }

    /**
     * Instantiate proxy with given subject
     *
     * @param string $proxyClassName Proxy class name
     *
     * @return object The created proxy instance.
     */
    private function instantiateProxy($proxyClassName)
    {
        $reflectionClass = new ReflectionClass($proxyClassName);
        return $reflectionClass->newInstanceWithoutConstructor();
    }

    /**
     * Modifies the class definition to include proxy-specific properties.
     *
     * @param array $classDef Class definition
     *
     * @return array Modified class definition
     */
    private function modifyClassDef(array $classDef)
    {
        // ProxyTrait has __get and __set / Proxy class would overwrite them, so we need to remove them from the class definition if they exist
        unset($classDef['methods']['__get']);
        unset($classDef['methods']['__set']);

        $classDef = $this->modifyAddProxyProperties($classDef);

        if ($classDef['isInterface']) {
            // subject (interface implementation) may have methods that are not declared in the interface,
            // so we need to add __call and __callStatic to the proxy class to handle those cases
            $classDef['methods'] = \array_merge(array(
                array(
                    'name' => '__call',
                    'parameters' => [
                        array( 'name' => 'method' ),
                        array( 'name' => 'args' ),
                    ],
                ),
                array(
                    'modifiers' => ['public', 'static'],
                    'name' => '__callStatic',
                    'parameters' => [
                        array( 'name' => 'method' ),
                        array( 'name' => 'args' ),
                    ],
                ),
            ), $classDef['methods']);
        }

        return $classDef;
    }

    /**
     * Adds proxy-specific properties to the class definition.
     *
     * @param array<string,mixed> $classDef Class definition
     *
     * @return array<string,mixed> Modified class definition
     */
    private function modifyAddProxyProperties($classDef)
    {
        $classDef['properties'][] = array(
            'hasValue' => true,
            'modifiers' => ['public', 'static'],
            'name' => 'proxyExtendOnly',
            'type' => PHP_VERSION_ID >= 70400 ? 'bool' : null,
            'value' => \in_array($classDef['name'], $this->extendOnlyClasses, true),
        );
        $classDef['properties'][] = array(
            'hasValue' => true,
            'modifiers' => ['private', 'static'],
            'name' => 'proxyParentClassName',
            'type' => PHP_VERSION_ID >= 70400 ? '?string' : null,
            'value' => !$classDef['isInterface'] && $classDef['name']
                ? $classDef['name']
                : null,
        );
        $classDef['properties'][] = array(
            'hasValue' => true,
            'modifiers' => ['private', 'static'],
            'name' => 'proxyParentPublicProperties',
            'type' => PHP_VERSION_ID >= 70400 ? 'array' : null,
            'value' => $this->getPublicProperties($classDef['name']),
        );

        return $classDef;
    }

    /**
     * Get list of public properties for given class name, excluding static properties.
     *
     * @param class-string $className Class name to get public properties for
     *
     * @return list<string>
     */
    private function getPublicProperties($className)
    {
        $reflectionClass = new ReflectionClass($className);
        $properties = [];
        foreach ($reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC) as $refProp) {
            if ($refProp->isStatic()) {
                continue;
            }
            $properties[] = $refProp->getName();
        }
        return $properties;
    }
}
