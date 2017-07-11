<?php

namespace bdk\DebugTest;

define('SOMECONSTANT', 'Constant value');

/**
 * TestBase gets extended to test that inherited properties / methods get debugged
 */
class TestBase
{

    const INHERITED = 'defined in TestBase';
    const MY_CONSTANT = 'defined in TestBase';

    private $testBasePrivate = 'defined in TestBase (private)';
    private $propPrivate = 'defined in TestBase (private)';
    protected $propProtected = 'defined in TestBase (protected)';
    public $propPublic = 'defined in TestBase (public)';

    private function testBasePrivate()
    {

    }

    public function testBasePublic()
    {

    }
}


/**
 * Test
 */
class Test extends TestBase
{

    const MY_CONSTANT = 'redefined in Test';

    /**
     * Private Property.
     *
     * @var string
     */
    private $propPrivate = 'redefined in Test (private)';

    protected $propProtected = 'redefined in Test (protected)';

    /**
     * Public Property.
     */
    public $propPublic = 'redefined in Test (public)';

    private $propNoDebug = 'hidden via __debugInfo';

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
        $this->debug = \bdk\Debug::getInstance();
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
        unset($return['propNoDebug']);
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
     * @return     void
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
