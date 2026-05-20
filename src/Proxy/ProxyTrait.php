<?php

namespace bdk\Proxy;

use bdk\Proxy\ListenerInterface;
use Closure;
use Exception;
use InvalidArgumentException;
use ReflectionClass;
use RuntimeException;

/**
 * This trait is used by proxied objects
 *
 * @property bool         $proxyExtendOnly             Whether be are unable to proxy an instance of the parent class
 * @property class-string $proxyParentClassName        The class name of the extended class
 * @property array        $proxyParentPublicProperties List of public properties of the parent
 *
 * @internal
 */
trait ProxyTrait
{
    /** @var ListenerInterface|null The listener used to process method calls */
    private $listener;

    /** @var ListenerInterface|null The listener used to process static method calls */
    private static $listenerInstance;

    /** @var object The object we're proxying */
    private $subject;

    /** @var class-string class name */
    private static $subjectClassName = '';

    /**
     * Magic getter
     *
     * Proxy param access to subject (proxied object)
     *
     * @param string $name Property name
     *
     * @return mixed
     *
     * @throws RuntimeException If property is not accessible
     */
    public function __get($name)
    {
        if ($this->subject === $this) {
            throw new RuntimeException('Unable to access property ' . \get_called_class() . '->' . $name);
        }

        return \in_array($name, self::$proxyParentPublicProperties, true)
            ? $this->subject->$name
            : $this->proxyCall('__get', [$name], function () use ($name) {
                return $this->subject->$name;
            });
    }

    /**
     * Magic setter
     *
     * Proxy param access to subject (proxied object)
     *
     * @param string $name  Property name
     * @param mixed  $value Property value
     *
     * @return void
     *
     * @throws RuntimeException If property is not accessible
     */
    public function __set($name, $value)
    {
        if ($this->subject === $this) {
            throw new RuntimeException('Unable to set property ' . \get_called_class() . '->' . $name);
        }

        if (\in_array($name, self::$proxyParentPublicProperties, true)) {
            $this->subject->$name = $value;
            return;
        }

        $this->proxyCall('__set', [$name, $value], function () use ($name, $value) {
            $this->subject->$name = $value;
        });
    }

    /**
     * Create a new instance using subject
     *
     * @param object $subject The object to be proxied
     *
     * @return object
     */
    public static function buildWithSubject($subject)
    {
        $selfClassname = \get_called_class();
        $reflectionClass = new ReflectionClass($selfClassname);
        $proxy = $reflectionClass->newInstanceWithoutConstructor();
        $proxy->setSubject($subject);
        return $proxy;
    }

    /**
     * Gets proxy listener
     *
     * @return ListenerInterface|null The listener instance or null if no listener is set
     */
    public function getListener()
    {
        return $this->listener;
    }

    /**
     * Gets subject (proxied object)
     *
     * @return object The proxied object
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * Set proxy listener
     *
     * @param ListenerInterface|null $listener Object that will receive method call notifications
     *
     * @return self
     *
     * @throws InvalidArgumentException
     */
    public function setListener($listener)
    {
        if ($listener === null) {
            $this->listener = null;
            return $this;
        }
        if ($listener instanceof ListenerInterface) {
            if (self::$listenerInstance === null) {
                self::$listenerInstance = $listener;
            }
            $this->listener = $listener;
            $this->listener->init($this->subject, $this);
            return $this;
        }
        throw new InvalidArgumentException('Listener must be null or implement ListenerInterface');
    }

    /**
     * Sets subject (proxied object)
     *
     * @param object $subject The proxied object
     *
     * @return self
     *
     * @throws RuntimeException If $proxyExtendOnly is true and subject is an instance of the parent class
     */
    public function setSubject($subject)
    {
        $thisClass = __CLASS__;
        if (self::$proxyExtendOnly && !($subject instanceof $thisClass)) {
            throw new RuntimeException('Unable to proxy instance of ' . self::$proxyParentClassName . '.  Must instead instantiate the proxy class (' . \get_called_class() . ') directly');
        }

        $this->subject = $subject;
        self::$subjectClassName = \get_class($subject);

        // Ensure subject properties are proxied
        //   We're likely extending the subject class and accessing public properties will not work as expected
        //   (they won't be proxied to the subject... even with our __get and __set methods)
        //   we need to unset all public properties of the proxy so that __get and __set will be triggered for them and proxied to the subject
        //
        // Note:  this does not work for mysqli
        foreach (self::$proxyParentPublicProperties as $prop) {
            unset($this->{$prop});
        }

        return $this;
    }

    /**
     * Calls a method on the {@see $subject} object.
     * Sends result to listener's afterCall method (if listener is set) before returning result
     *
     * @param string  $methodName Method being called
     * @param array   $arguments  List of arguments passed to a called method
     * @param Closure $closure    (optional) Closure to call vs calling method on subject
     *
     * @throws Exception In case of error happen during the method call.
     *
     * @return mixed
     */
    protected function proxyCall($methodName, array $arguments = array(), $closure = null)
    {
        $exception = null;
        $initValues = array(
            'memoryStart' => \memory_get_usage(false),
            'timeStart' => \microtime(true),
        );
        $result = null;
        try {
            $result = $closure
                ? $closure()
                : $this->proxyCallMethod($methodName, $arguments);
        } catch (Exception $e) {
            $exception = $e;
        }
        if ($this->listener) {
            $result = $this->listener->afterCall($methodName, $arguments, $result, $initValues, $exception);
        }
        if ($exception !== null) {
            throw $exception;
        }
        return $this->proxyProcessResult($result, $this, $this->listener);
    }

    /**
     * Call the given method on the proxied object
     *
     * @param string $methodName Method being called
     * @param array  $arguments  List of arguments passed to a called method
     *
     * @return mixed
     */
    private function proxyCallMethod($methodName, array $arguments)
    {
        $class = __CLASS__;
        $subject = $this->subject;
        if ($subject instanceof $class) {
            // we're proxying "our self" (ie we're unable to proxy mysqli's public properties)
            $subject = self::$proxyParentClassName;
        }
        return $methodName === '__construct'
            ? $this->proxyCallConstructor($arguments)
            : \call_user_func_array([$subject, $methodName], $arguments);
    }

    /**
     * Proxy call to constructor
     *
     * @param array $arguments List of arguments passed to a called method
     *
     * @return null
     */
    protected function proxyCallConstructor(array $arguments)
    {
        $result = null;
        if (self::$proxyExtendOnly) {
            // call parent::__construct
            \call_user_func_array([self::$proxyParentClassName, '__construct'], $arguments);
            $this->setSubject($this);
            return $result;
        }
        $reflectionClass = new ReflectionClass(self::$proxyParentClassName);
        $subject = $reflectionClass->newInstanceArgs($arguments);
        $this->setSubject($subject);
        return $result;
    }

    /**
     * Proxies a static method call to the {@see $subjectClassName} class.
     * Sends result to instance listener's afterCall method (if listener is set) before returning result
     *
     * @param string $methodName Method being called
     * @param array  $arguments  A list of arguments passed to a called method
     *
     * @throws Exception In case of error happen during the method call.
     *
     * @return mixed
     */
    protected static function proxyCallStatic($methodName, array $arguments)
    {
        $subjectClassName = self::$subjectClassName ?: self::$proxyParentClassName;
        $result = \call_user_func_array([$subjectClassName, $methodName], $arguments);
        return self::proxyProcessResult($result, null, self::$listenerInstance);
    }

    /**
     * Processes return value of a called method
     *
     * If result is an instance of {@see $subject}, we will return existing proxy instance or create a new one
     *
     * @param mixed                  $result        Return value of a called method.
     * @param self|null              $proxyInstance The proxy instance (or null for static calls)
     * @param ListenerInterface|null $listener      The listener instance (null if none is set)
     *
     * @return $this|mixed Either a new subject of {@see $subject} class or return value of a called method.
     */
    private static function proxyProcessResult($result, $proxyInstance = null, $listener = null)
    {
        if ($proxyInstance && $result === $proxyInstance->getSubject()) {
            // method call is returning $this->subject
            return $proxyInstance;
        }
        $subjectClassName = self::$subjectClassName ?: self::$proxyParentClassName;
        if (\is_object($result) && \get_class($result) === $subjectClassName) {
            // method call is returning a new instance of subject
            return self::buildWithSubject($result)
                ->setListener($listener);
        }
        return $result;
    }
}
