<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     v3.0
 */

namespace bdk;

use ArrayAccess;
use bdk\Container\ObjectBuilder;
use bdk\Container\ServiceProviderInterface;
use bdk\Container\Utility;
use InvalidArgumentException;
use OutOfBoundsException;
use RuntimeException;
use SplObjectStorage;

/**
 * Container
 *
 * Forked from pimple/pimple
 *    adds:
 *       get()
 *       getObject()
 *       has()
 *       needsInvoked()
 *       setCfg()
 *          allowOverride & onInvoke callback
 *       setValues()
 *
 * @author Fabien Potencier
 * @author Brad Kent <bkfake-github@yahoo.com>
 */
class Container implements ArrayAccess
{
    /** @var array */
    private $aliases = array();

    /** @var list<string> list of keys that can be overridden - even if allowOverride is false*/
    private $allowOverrides = [];

    /** @var array */
    private $cfg = array(
        'allowOverride' => false,  // whether can update already built services
        'onInvoke' => null, // callable  callable will receive [value, name, container].
                            //  value is the value returned by the service definition (or factory)
                            //  reminder that value is mixed type (not necessarily an object)
    );

    /**
     * Closures used to modify / extend service definitions when invoked
     *
     * @var array<string,\Closure>
     */
    private $extenders;

    /**
     * Closures flagged as factories
     *
     * @var SplObjectStorage
     */
    private $factories;

    /**
     * Keep track of invoked service closures
     *
     * @var array<string,bool>
     */
    private $invoked = array();

    /** @var ObjectBuilder */
    private $objectBuilder;

    /**
     * Wrap anonymous functions with the protect() method to store them as value
     *  vs treating as service
     *
     * @var SplObjectStorage
     */
    private $protected;

    /**
     * Populated with the original raw service/factory closure when invoked
     *
     * @var array<string,mixed>
     */
    private $raw = array();

    /** @var array<string,mixed> */
    private $values = array();

    /**
     * Instantiates the container.
     *
     * Objects and parameters can be passed as argument to the constructor.
     *
     * @param array $values The parameters or objects
     * @param array $cfg    Config options
     */
    public function __construct(array $values = array(), array $cfg = array())
    {
        $this->factories = new SplObjectStorage();
        $this->protected = new SplObjectStorage();

        $this->setCfg($cfg);
        $this->setValues($values);
    }

    /**
     * Magic method
     *
     * Provide insight into the container
     * exclude raw & values
     *
     * @return array
     */
    public function __debugInfo()
    {
        return array(
            'aliases' => $this->aliases,
            'allowOverrides' => $this->allowOverrides,
            'cfg' => $this->cfg,
            'invoked' => $this->invoked,
            'raw' => "\x00notInspected\x00",
            'values' => "\x00notInspected\x00",
        );
    }

    /**
     * Adds an alias for a value
     *
     * examples
     *    'myUtility' => 'My\Namespace\MyUtility'
     *    'someInterface' => 'My\Namespace\SomeImplementation'
     *
     * @param string $alias  The alias name
     * @param string $actual The resolved name
     *
     * @return void
     *
     * @throws OutOfBoundsException If the $actual is invalid
     */
    public function addAlias($alias, $actual)
    {
        if (isset($this->values[$actual]) === false) {
            throw new OutOfBoundsException(\sprintf(
                'Unable to create alias "%s" for unknown identifier "%s"',
                $alias,
                $actual
            ));
        }
        $this->aliases[$alias] = $actual;
    }

    /**
     * Allow a value to be overridden even if allowOverride is false
     * ie allow storing and updating PSR-7 http-message response
     *
     * @param string $name service or factory name
     *
     * @return $this
     */
    public function allowOverride($name)
    {
        $this->allowOverrides[] = $this->nameActual($name);
        $this->allowOverrides = \array_unique($this->allowOverrides);
        return $this;
    }

    /**
     * Extends an object definition.
     *
     * Useful for
     *  - Extend an existing object definition without necessarily loading that object.
     *  - Ensure user-supplied factory is decorated with additional functionality.
     *
     * The callable should:
     *  - take the value as its first argument and the container as its second argument
     *  - return the modified value
     *
     * @param string   $name     The unique identifier for the object
     * @param callable $callable A callable that will receive the resolved value and the container as arguments
     *
     * @return void
     */
    public function extend($name, $callable)
    {
        $this->assertExists($name);
        Utility::assertInvokable($this->values[$name]);
        Utility::assertInvokable($callable);

        $this->extenders[$name] = $callable;
    }

    /**
     * Marks a callable as being a factory service.
     * A new instance will be returned each time it is accessed
     *
     *     $container['someFactory'] = $container->factory(static function () {
     *       return new FactoryThing();
     *     });
     *
     * @param callable $invokable A service definition to be used as a factory
     *
     * @return callable The passed callable
     * @throws InvalidArgumentException Service definition has to be a closure or an invokable object
     */
    public function factory($invokable)
    {
        Utility::assertInvokable($invokable);
        $this->factories->offsetSet($invokable);
        return $invokable;
    }

    /**
     * Finds an entry by its identifier and returns it.
     *
     * @param string $name Identifier of the entry to look for.
     *
     * @return mixed Entry.
     */
    public function get($name)
    {
        return $this->offsetGet($name);
    }

    /**
     * Instantiate an object of the given class.
     *
     * Attempt to resolve any constructor arguments from the container.
     *
     * @param string $classname    Classname of the object to instantiate
     * @param bool   $useContainer (true) Pull from container if available / store obj in container after build.  False is similar to factory behavior.
     *
     * @return object
     */
    public function getObject($classname, $useContainer = true)
    {
        if ($this->objectBuilder === null) {
            $this->objectBuilder = new ObjectBuilder($this);
        }
        return $this->objectBuilder->build($classname, $useContainer);
    }

    /**
     * Do we have an entry for the given identifier.
     *
     * @param string $name Identifier of the entry to look for.
     *
     * @return bool
     */
    public function has($name)
    {
        return $this->offsetExists($name);
    }

    /**
     * Returns all defined value names.
     *
     * @return array An array of value names
     */
    public function keys()
    {
        return \array_keys($this->values);
    }

    /**
     * Is value a service/factory that hasn't been invoked yet?
     *
     * @param string $name Identifier of entry to check
     *
     * @return bool
     *
     * @throws OutOfBoundsException If the identifier is not defined
     */
    public function needsInvoked($name)
    {
        $this->assertExists($name);
        $name = $this->nameActual($name);
        if (isset($this->invoked[$name])) {
            // already invoked
            return false;
        }
        $raw = $this->values[$name];
        if (\is_object($raw) === false) {
            return false;
        }
        if (isset($this->protected[$raw])) {
            // protected
            return false;
        }
        return \method_exists($raw, '__invoke');
    }

    /**
     * ArrayAccess: Checks if a parameter or an object is set.
     *
     * @param string $name The unique identifier for the parameter or object
     *
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($name)
    {
        $name = $this->nameActual($name);
        return \array_key_exists($name, $this->values);
    }

    /**
     * ArrayAccess: Gets a parameter or an object.
     *
     * @param string $name The unique identifier for the parameter or object
     *
     * @return mixed The value of the parameter or an object
     * @throws OutOfBoundsException If the identifier is not defined
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($name)
    {
        $this->assertExists($name);
        $name = $this->nameActual($name);
        $raw = $this->values[$name];

        if ($this->needsInvoked($name) === false) {
            // already invoked, protected, or not a closure
            return $raw;
        }

        if (\is_object($raw) && isset($this->factories[$raw])) {
            // we're a factory
            return $this->onInvoke($name, $raw($this));
        }

        // we're a service
        $this->invoked[$name] = true;
        $this->raw[$name] = $raw;
        $val = $this->onInvoke($name, $raw($this));
        $this->values[$name] = $val;

        return $val;
    }

    /**
     * ArrayAccess: Sets a parameter or an object.
     *
     * @param string $name  The unique identifier for the parameter or object
     * @param mixed  $value The value of the parameter or a closure to define an object
     *
     * @throws RuntimeException Prevent override of a already built service
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($name, $value)
    {
        $name = $this->nameActual($name);

        if (
            isset($this->invoked[$name])
            && $this->cfg['allowOverride'] === false
            && \in_array($name, $this->allowOverrides, true) === false
        ) {
            throw new RuntimeException(
                \sprintf('Cannot update "%s" after it has been instantiated.', $name)
            );
        }

        $this->values[$name] = $value;
        unset(
            $this->invoked[$name],
            $this->raw[$name]
        );
    }

    /**
     * ArrayAccess: Unsets a parameter or an object.
     *
     * if $name is an alias, the alias is removed (but not the actual value)
     *
     * @param string $name The unique identifier for the parameter or object
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($name)
    {
        if ($this->offsetExists($name) === false) {
            return;
        }
        if (isset($this->aliases[$name])) {
            // only remove the alias, not the actual value
            unset($this->aliases[$name]);
            return;
        }
        if (\is_object($this->values[$name])) {
            unset(
                $this->factories[$this->values[$name]],
                $this->protected[$this->values[$name]]
            );
        }
        unset(
            $this->invoked[$name],
            $this->raw[$name],
            $this->values[$name]
        );
        $this->aliases = \array_diff($this->aliases, [$name]);
    }

    /**
     * Protects a callable from being interpreted as a service.
     *
     * This is useful when you want to store a callable as a value.
     *
     *     $container['some_func'] = $container->protect(static function () {
     *       return rand();
     *     });
     *
     * @param callable $invokable A callable to protect from being evaluated
     *
     * @return callable The passed callable
     * @throws InvalidArgumentException Service definition has to be a closure or an invokable object
     */
    public function protect($invokable)
    {
        Utility::assertInvokable($invokable);
        $this->protected->offsetSet($invokable);
        return $invokable;
    }

    /**
     * Gets a parameter or the closure defining an object.
     *
     * @param string $name The unique identifier for the parameter or object
     *
     * @return mixed The value of the parameter or the closure defining an object
     *
     * @throws OutOfBoundsException If the identifier is not defined
     */
    public function raw($name)
    {
        $this->assertExists($name);

        if (isset($this->raw[$name])) {
            return $this->raw[$name];
        }

        return $this->values[$name];
    }

    /**
     * Registers a service provider.
     *
     * @param ServiceProviderInterface $provider A ServiceProviderInterface instance
     *
     * @return $this
     */
    public function registerProvider(ServiceProviderInterface $provider)
    {
        $provider->register($this);
        return $this;
    }

    /**
     * Set one or more config values
     *
     *    setCfg('key', 'value')
     *    setCfg(array('k1'=>'v1', 'k2'=>'v2'))
     *
     * @param array|string $mixed key=>value array or key
     * @param mixed        $value new value
     *
     * @return $this
     */
    public function setCfg($mixed, $value = null)
    {
        if (\is_array($mixed) === false) {
            $mixed = array($mixed => $value);
        }
        $this->cfg = \array_replace($this->cfg, $mixed);
        return $this;
    }

    /**
     * Set multiple values
     *
     * @param array $values values to set
     *
     * @return $this
     */
    public function setValues(array $values)
    {
        foreach ($values as $key => $value) {
            $this->offsetSet($key, $value);
        }
        return $this;
    }

    /**
     * Assert that the identifier exists
     *
     * @param string $name Identifier of entry to check
     *
     * @return void
     *
     * @throws OutOfBoundsException If the identifier is not defined
     */
    private function assertExists(&$name)
    {
        if ($this->offsetExists($name) === false) {
            throw new OutOfBoundsException(
                \sprintf('Unknown identifier: "%s"', $name)
            );
        }
    }

    /**
     * Get the non-aliased name
     *
     * @param string $name The service or factory name
     *
     * @return string
     */
    private function nameActual($name)
    {
        return isset($this->aliases[$name])
            ? $this->aliases[$name]
            : $name;
    }

    /**
     * Called when service or factory is invoked
     *
     * @param string $name  The service or factory name
     * @param mixed  $value The value returned by the definition
     *
     * @return mixed the value (possibly modified by extenders)
     */
    private function onInvoke($name, $value)
    {
        if (isset($this->extenders[$name])) {
            $callable = $this->extenders[$name];
            $value = $callable($value, $this);
        }
        if (\is_callable($this->cfg['onInvoke'])) {
            $this->cfg['onInvoke']($value, $name, $this);
        }
        return $value;
    }
}
