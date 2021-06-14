<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2021 Brad Kent
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
     *     $container['someFactory'] = $container->factory(function () {
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
     * @param string $id Identifier of the entry to look for.
     *
     * @return mixed Entry.
     */
    public function get($id)
    {
        return $this->offsetGet($id);
    }

    /**
     * Do we have an entry for the given identifier.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return bool
     */
    public function has($id)
    {
        return $this->offsetExists($id);
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
     * @param string $id Identifier of entry to check
     *
     * @return bool
     *
     * @throws \OutOfBoundsException If the identifier is not defined
     */
    public function needsInvoked($id)
    {
        $this->assertExists($id);
        $notNeedInvoked = isset($this->invoked[$id]) === true
            || \is_object($this->values[$id]) === false
            || \method_exists($this->values[$id], '__invoke') === false
            || isset($this->protected[$this->values[$id]]) === true;
        return $notNeedInvoked === false;
    }

    /**
     * ArrayAccess
     * Checks if a parameter or an object is set.
     *
     * @param string $id The unique identifier for the parameter or object
     *
     * @return bool
     */
    public function offsetExists($id)
    {
        return isset($this->keys[$id]);
    }

    /**
     * ArrayAccess
     * Gets a parameter or an object.
     *
     * @param string $id The unique identifier for the parameter or object
     *
     * @return mixed The value of the parameter or an object
     * @throws \OutOfBoundsException If the identifier is not defined
     */
    public function offsetGet($id)
    {
        $this->assertExists($id);
        if ($this->needsInvoked($id) === false) {
            return $this->values[$id];
        }
        if (isset($this->factories[$this->values[$id]])) {
            // we're a factory
            $val = $this->values[$id]($this);
            if (\is_callable($this->cfg['onInvoke'])) {
                $this->cfg['onInvoke']($val, $id, $this);
            }
            return $val;
        }
        // we're a service
        $raw = $this->values[$id];
        $this->invoked[$id] = true;
        $this->raw[$id] = $raw;

        $val = $raw($this);
        if (\is_callable($this->cfg['onInvoke'])) {
            $this->cfg['onInvoke']($val, $id, $this);
        }
        $this->values[$id] = $val;

        return $val;
    }

    /**
     * ArrayAccess
     * Sets a parameter or an object.
     *
     * @param string $id    The unique identifier for the parameter or object
     * @param mixed  $value The value of the parameter or a closure to define an object
     *
     * @throws \RuntimeException Prevent override of a already built service
     * @return void
     */
    public function offsetSet($id, $value)
    {
        if (isset($this->invoked[$id]) && $this->cfg['allowOverride'] === false) {
            throw new \RuntimeException(
                \sprintf('Cannot update "%s" after it has been instantiated.', $id)
            );
        }

        $this->keys[$id] = true;
        $this->values[$id] = $value;
        unset(
            $this->invoked[$id],
            $this->raw[$id]
        );
    }

    /**
     * ArrayAccess
     * Unsets a parameter or an object.
     *
     * @param string $id The unique identifier for the parameter or object
     *
     * @return void
     */
    public function offsetUnset($id)
    {
        if ($this->offsetExists($id) === false) {
            return;
        }
        if (\is_object($this->values[$id])) {
            unset(
                $this->factories[$this->values[$id]],
                $this->protected[$this->values[$id]]
            );
        }
        unset(
            $this->invoked[$id],
            $this->keys[$id],
            $this->raw[$id],
            $this->values[$id]
        );
    }

    /**
     * Protects a callable from being interpreted as a service.
     *
     * This is useful when you want to store a callable as a value.
     *
     *     $container['some_func'] = $container->protect(function () {
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
     * @param string $id The unique identifier for the parameter or object
     *
     * @return mixed The value of the parameter or the closure defining an object
     *
     * @throws \OutOfBoundsException If the identifier is not defined
     */
    public function raw($id)
    {
        $this->assertExists($id);

        if (isset($this->raw[$id])) {
            return $this->raw[$id];
        }

        return $this->values[$id];
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
     * @param string $id Identifier of entry to check
     *
     * @return void
     *
     * @throws \OutOfBoundsException If the identifier is not defined
     */
    private function assertExists($id)
    {
        if ($this->offsetExists($id) === false) {
            throw new \OutOfBoundsException(
                \sprintf('Unknown identifier: "%s"', $id)
            );
        }
    }
}
