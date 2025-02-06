<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     v3.0
 */

namespace bdk;

use ArrayAccess;
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
    private $cfg = array(
        'allowOverride' => false,  // whether can update already built service
        'onInvoke' => null, // callable
    );

    /**
     * Closures used to modify / extend service definitions when invoked
     *
     * @var array<string,Closure>
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

    /** @var array<string,bool> */
    private $keys = array();

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
    public function __construct($values = array(), $cfg = array())
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
            'cfg' => $this->cfg,
            'invoked' => $this->invoked,
            'keys' => $this->keys,
            'raw' => "\x00notInspected\x00",
            'values' => "\x00notInspected\x00",
        );
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
     * @param callable $callable A service definition to extend the original
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
        $this->factories->attach($invokable);
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
        $notNeedInvoked = isset($this->invoked[$name]) === true
            || \is_object($this->values[$name]) === false
            || \method_exists($this->values[$name], '__invoke') === false
            || isset($this->protected[$this->values[$name]]) === true;
        return $notNeedInvoked === false;
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
        return isset($this->keys[$name]);
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

        if ($this->needsInvoked($name) === false) {
            return $this->values[$name];
        }

        if (isset($this->factories[$this->values[$name]])) {
            // we're a factory
            $val = $this->values[$name]($this);
            return $this->onInvoke($name, $val);
        }

        // we're a service
        $raw = $this->values[$name];
        $this->invoked[$name] = true;
        $this->raw[$name] = $raw;

        $val = $raw($this);
        $val = $this->onInvoke($name, $val);
        $this->values[$name] = $val;

        return $val;
    }

    /**
     * ArrayAccess: Sets a parameter or an object.
     *
     * @param string $offset The unique identifier for the parameter or object
     * @param mixed  $value  The value of the parameter or a closure to define an object
     *
     * @throws RuntimeException Prevent override of a already built service
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        if (isset($this->invoked[$offset]) && $this->cfg['allowOverride'] === false) {
            throw new RuntimeException(
                \sprintf('Cannot update "%s" after it has been instantiated.', $offset)
            );
        }

        $this->keys[$offset] = true;
        $this->values[$offset] = $value;
        unset(
            $this->invoked[$offset],
            $this->raw[$offset]
        );
    }

    /**
     * ArrayAccess: Unsets a parameter or an object.
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
        if (\is_object($this->values[$name])) {
            unset(
                $this->factories[$this->values[$name]],
                $this->protected[$this->values[$name]]
            );
        }
        unset(
            $this->invoked[$name],
            $this->keys[$name],
            $this->raw[$name],
            $this->values[$name]
        );
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
        $this->protected->attach($invokable);
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
     * @param mixed        $val   new value
     *
     * @return $this
     */
    public function setCfg($mixed, $val = null)
    {
        if (\is_string($mixed)) {
            $this->cfg[$mixed] = $val;
            return $this;
        }
        if (\is_array($mixed)) {
            $this->cfg = \array_merge($this->cfg, $mixed);
        }
        return $this;
    }

    /**
     * Set multiple values
     *
     * @param array $values values to set
     *
     * @return $this
     */
    public function setValues($values)
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
    private function assertExists($name)
    {
        if ($this->offsetExists($name) === false) {
            throw new OutOfBoundsException(
                \sprintf('Unknown identifier: "%s"', $name)
            );
        }
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
