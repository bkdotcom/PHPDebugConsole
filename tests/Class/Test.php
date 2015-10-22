<?php

namespace bdk\DebugTest;

define('SOMECONSTANT', 'Constant value');

/**
 * TestBase gets extended to test that inherited properties / methods get debugged
 */
class TestBase
{

    const INHERITED = 'hello world';
    const MY_CONSTANT = 'defined in TestBase';

    public $inheritedProp = 'Inherited via TestBase';

    public function inheritedFunction()
    {

    }

}


/**
 * Test
 */
class Test extends TestBase
{

    const MY_CONSTANT = 'constant value';

    /**
     * Public Property.
     */
    public $propPublic = 'iAmPublic';
    /**
     * Private Property.
     *
     * @var string
     */
    private $propPrivate = 'iAmPrivate';
    protected $propProtected = 'iAmProtected';

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
    public function __construct()
    {
        $this->debug = \bdk\Debug\Debug::getInstance();
        $this->instance = $this;
    }

    /**
     * toString magic method
     *
     * @return string
     */
    public function __toString()
    {
        return 'abracadabra';
    }

    /**
     * magic method
     *
     * @return array
     */
    public function __debugInfo()
    {
        $return = get_object_vars($this);
        $return['propPrivate'] .= ' (alternate value via __debugInfo)';
        $return['debugValue'] = 'This property is debug only';
        return $return;
    }

    /**
     * This is a static method
     *
     * @return void Nothing is returned
     */
    public static function methodStatic()
    {

    }

    /**
     * This method is public
     *
     * @param SomeClass $param1 first param
     * @param mixed     $param2 second param
     *                      two-line description!
     * @param array     $param3 third param
     *
     * @return void
     * @deprecated
     */
    public function methodPublic(\SomeClass $param1, $param2 = SOMECONSTANT, array $param3 = array())
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
