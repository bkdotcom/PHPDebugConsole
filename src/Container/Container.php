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

namespace bdk;

use bdk\Container\ServiceProviderInterface;

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
class Container implements \ArrayAccess
{
    private $cfg = array(
        'allowOverride' => false,  // whether can update alreay built service
        'onInvoke' => null, // callable
    );

    /**
     * Closures flagged as factories
     *
     * @var \SplObjectStorage
     */
    private $factories;

    private $invoked = array();  // keep track of invoked service closures

    private $keys = array();

    /**
     * wrap anonymous functions with the protect() method to store them as value
     *  vs treating as service
     *
     * @var \SplObjectStorage
     */
    private $protected;

    private $raw = array();

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
        $this->factories = new \SplObjectStorage();
        $this->protected = new \SplObjectStorage();

        $this->setCfg($cfg);
        $this->setValues($values);
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
     * @throws \InvalidArgumentException Service definition has to be a closure or an invokable object
     */
    public function factory($invokable)
    {
        if (\is_object($invokable) === false || \method_exists($invokable, '__invoke') === false) {
            throw new \InvalidArgumentException('Closure or invokable object expected.');
        }
        $this->factories->attach($invokable);
        return $invokable;
    }

    /**
     * Finds an entry of the container by its identifier and returns it.
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
     * @throws \OutOfBoundsException If the identifier is not defined
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
     * ArrayAccess
     * Checks if a parameter or an object is set.
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
     * ArrayAccess
     * Gets a parameter or an object.
     *
     * @param string $name The unique identifier for the parameter or object
     *
     * @return mixed The value of the parameter or an object
     * @throws \OutOfBoundsException If the identifier is not defined
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
            if (\is_callable($this->cfg['onInvoke'])) {
                $this->cfg['onInvoke']($val, $name, $this);
            }
            return $val;
        }
        // we're a service
        $raw = $this->values[$name];
        $this->invoked[$name] = true;
        $this->raw[$name] = $raw;

        $val = $raw($this);
        if (\is_callable($this->cfg['onInvoke'])) {
            $this->cfg['onInvoke']($val, $name, $this);
        }
        $this->values[$name] = $val;

        return $val;
    }

    /**
     * ArrayAccess
     * Sets a parameter or an object.
     *
     * @param string $name  The unique identifier for the parameter or object
     * @param mixed  $value The value of the parameter or a closure to define an object
     *
     * @throws \RuntimeException Prevent override of a already built service
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($name, $value)
    {
        if (isset($this->invoked[$name]) && $this->cfg['allowOverride'] === false) {
            throw new \RuntimeException(
                \sprintf('Cannot update "%s" after it has been instantiated.', $name)
            );
        }

        $this->keys[$name] = true;
        $this->values[$name] = $value;
        unset(
            $this->invoked[$name],
            $this->raw[$name]
        );
    }

    /**
     * ArrayAccess
     * Unsets a parameter or an object.
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
     * @throws \InvalidArgumentException Service definition has to be a closure or an invokable object
     */
    public function protect($invokable)
    {
        if (\is_object($invokable) === false || \method_exists($invokable, '__invoke') === false) {
            throw new \InvalidArgumentException('Closure or invokable object expected.');
        }
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
     * @throws \OutOfBoundsException If the identifier is not defined
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
     * @throws \OutOfBoundsException If the identifier is not defined
     */
    private function assertExists($name)
    {
        if ($this->offsetExists($name) === false) {
            throw new \OutOfBoundsException(
                \sprintf('Unknown identifier: "%s"', $name)
            );
        }
    }
}
