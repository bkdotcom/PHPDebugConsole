<?php

namespace bdk\Test\Debug\Fixture;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstraction;
use stdClass;

/**
 * PhpDoc Summary
 *
 * PhpDoc Description
 *
 * @link http://www.bradkent.com/php/debug PHPDebugConsole Homepage
 */
#[\AllowDynamicProperties]
class TestObj extends TestBase
{
    /** constant documentation */
    const MY_CONSTANT = 'redefined in Test';

    public $someArray = array(
        'int' => 123,
        'numeric' => '123',
        'string' => 'cheese',
        'bool' => true,
        'obj' => null,
    );

    public static $propStatic = 'I\'m Static';

    /**
     * Public Property.
     */
    public $propPublic = 'redefined in Test (public)';

    private $debug;
    private $instance;

    /**
     * Private Property.
     *
     * @var string
     */
    private $propPrivate = 'redefined in Test (private)';

    // protected $propProtected = 'redefined in Test (protected)';

    private $propNoDebug = 'not included in __debugInfo';

    private $toString;
    private $toStrThrow;

    /**
     * Constructor
     *
     * Constructor description
     *
     * @param string $toString   value __toString will return;
     * @param int    $toStrThrow 0: don't, 1: throw, 2: throw & catch
     */
    public function __construct($toString = 'abracadabra', $toStrThrow = 0)
    {
        $this->debug = Debug::getInstance();
        $this->toStrThrow = $toStrThrow;
        $this->toString = $toString;
        $this->instance = $this;
        $this->dynamic = 'dynomite!';
        $this->someArray['obj'] = (object) array('foo' => 'bar');
        parent::__construct();
    }

    /**
     * magic method
     *
     * @return array property=>value array
     */
    public function __debugInfo()
    {
        $className = \get_class($this);
        $return = \array_merge(\get_class_vars($className), \get_object_vars($this));
        $return['propPrivate'] .= ' (alternate value via __debugInfo)';
        $return['debugValue'] = 'This property is debug only';
        unset($return['propNoDebug']);
        return $return;
    }

    /**
     * toString magic method
     *
     * Long Description
     *
     * @return string
     *
     * @throws \Exception when toStrThrow is `1`
     */
    public function __toString()
    {
        static $static = 'I\'m static';
        if ($this->toStrThrow === 1) {
            throw new \Exception('thown exception');
        }
        if ($this->toStrThrow === 2) {
            try {
                throw new \Exception('[exception trigger_error]');
            } catch (\Exception $e) {
                return \trigger_error($e, E_USER_ERROR);
            }
        }
        return $this->toString;
    }

    /**
     * This is a static method
     *
     * @return void Nothing is returned
     */
    /*
    public static function methodStatic()
    {
        \bdk\Debug::group();
        \bdk\Debug::log('this group\'s label should be', __CLASS__.'::'.__FUNCTION__);
        \bdk\Debug::groupEnd();
    }
    */

    /**
     * This method is public
     *
     * @param stdClass $param1 first param
     *                     two-line description!
     * @param array    $param2 second param
     *
     * @return     void
     * @deprecated this method is bad and should feel bad
     */
    final public function methodPublic(stdClass $param1, array $param2 = array())
    {
        static $foo = 42;
        static $bar = 'test';
        static $baz = null;
        $baz = $this; // test for recursion
    }

    /**
     * This method is private
     *
     * @param mixed               $param1     first param (passed by ref)
     * @param \bdk\PubSub\Event[] $param2     second param (passed by ref)
     * @param bool                $param3,... 3rd param not in method signature
     *
     * @return void
     */
    private function methodPrivate(\SomeClass &$param1, &$param2)
    {
    }

    /**
     * This method is protected
     *
     * @param Abstraction[] $param1 first param
     *
     * @return void
     */
    protected function methodProtected($param1)
    {
    }
}
