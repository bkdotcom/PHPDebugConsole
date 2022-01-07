<?php

namespace bdk\DebugTests\Fixture;

// \define('SOMECONSTANT', 'Constant value');

/**
 * PhpDoc Summary
 *
 * PhpDoc Description
 *
 * @link http://www.bradkent.com/php/debug PHPDebugConsole Homepage
 */
class Test extends TestBase
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

    /**
     * Private Property.
     *
     * @var string
     */
    private $propPrivate = 'redefined in Test (private)';

    // protected $propProtected = 'redefined in Test (protected)';

    private $propNoDebug = 'not included in __debugInfo';

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
        $this->debug = \bdk\Debug::getInstance();
        $this->toStrThrow = $toStrThrow;
        $this->toString = $toString;
        $this->instance = $this;
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
     * @return string
     */
    public function __toString()
    {
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
     * @param array     $param2 second param
     *
     * @return     void
     * @deprecated this method is bad and should feel bad
     */
    final public function methodPublic(\SomeClass $param1, array $param2 = array())
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
