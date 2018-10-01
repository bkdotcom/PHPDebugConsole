<?php

namespace bdk\DebugTest;

define('SOMECONSTANT', 'Constant value');

/**
 * TestBase gets extended to test that inherited properties / methods get debugged
 *
 * Note that @property phpDoc tags will not be incorporated if useDebugInfo && __debugInfo magic mehod
 *
 * @property      boolean $magicProp     I'm avail via __get()
 * @property-read boolean $magicReadProp Read Only!
 *
 * @method void presto($foo, integer $int = 1, $bool = true, $null = null) I'm a magic method
 * @method static void prestoStatic(string $noDefault, $arr = array(), $opts=array('a'=>'ay','b'=>'bee')) I'm a static magic method
 */
class TestBase
{

    const INHERITED = 'defined in TestBase';
    const MY_CONSTANT = 'defined in TestBase';

    private $testBasePrivate = 'defined in TestBase (private)';
    private $propPrivate = 'defined in TestBase (private)';
    protected $propProtected = 'defined only in TestBase (protected)';
    public $propPublic = 'defined in TestBase (public)';
    protected $magicReadProp = 'not null';

    /**
     * get magic method
     *
     * @param string $key what we're getting
     *
     * @return mixed
     */
    public function __get($key)
    {
    }

    /**
     * call magic method
     *
     * @param string $name Method being called
     * @param array  $args Arguments passed
     *
     * @return mixed
     */
    public function __call($name, $args)
    {
    }

    private function testBasePrivate()
    {
    }

    public function testBasePublic()
    {
        \bdk\Debug::_group();
        \bdk\Debug::_log('this group\'s label should be', get_class($this).'->'.__FUNCTION__);
        \bdk\Debug::_groupEnd();
    }

    public static function testBaseStatic()
    {
        \bdk\Debug::_group();
        \bdk\Debug::_log('this group\'s label will be '.__CLASS__.'::'.__FUNCTION__.' regardless if called from inherited class :(');
        \bdk\Debug::_groupEnd();
    }
}


/**
 * Test
 *
 * @link http://www.bradkent.com/php/debug PHPDebugConsole Homepage
 */
class Test extends TestBase
{

    const MY_CONSTANT = 'redefined in Test';

    public static $propStatic = 'I\'m Static';

    /**
     * Private Property.
     *
     * @var string
     */
    private $propPrivate = 'redefined in Test (private)';

    // protected $propProtected = 'redefined in Test (protected)';

    /**
     * Public Property.
     */
    public $propPublic = 'redefined in Test (public)';

    private $propNoDebug = 'not included in __debugInfo';

    public $someArray = array(
        'int' => 123,
        'numeric' => '123',
        'string' => 'cheese',
        'bool' => true,
        'obj' => null,
    );

    /**
     * Constructor
     */
    public function __construct($toString = 'abracadabra')
    {
        $this->debug = \bdk\Debug::getInstance();
        $this->toString = $toString;
        $this->instance = $this;
    }

    /**
     * magic method
     *
     * @return array
     */
    public function __debugInfo()
    {
        $className = get_class($this);
        $return = array_merge(get_class_vars($className), get_object_vars($this));
        $return['propPrivate'] .= ' (alternate value via __debugInfo)';
        $return['debugValue'] = 'This property is debug only';
        unset($return['propNoDebug']);
        return $return;
    }

    /**
     * toString magic method
     *
     * @return string
     */
    public function __toString()
    {
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
        \bdk\Debug::_group();
        \bdk\Debug::_log('this group\'s label should be', __CLASS__.'::'.__FUNCTION__);
        \bdk\Debug::_groupEnd();
    }
    */

    /**
     * This method is public
     *
     * @param SomeClass $param1 first param
     *                      two-line description!
     * @param array     $param2 third param
     *
     * @return     void
     * @deprecated
     */
    public function methodPublic(\SomeClass $param1, array $param2 = array())
    {
    }

    /**
     * This method is private
     *
     * @param mixed $param1 first param (passed by ref)
     * @param mixed $param2 second param (passed by ref)
     *
     * @return void
     */
    private function methodPrivate(\SomeClass &$param1, &$param2)
    {
    }

    /**
     * This method is protected
     *
     * @param mixed $param1 first param
     *
     * @return void
     */
    protected function methodProtected($param1)
    {
    }
}
