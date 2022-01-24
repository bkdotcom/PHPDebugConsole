<?php

namespace bdk\Test\Debug\Fixture;

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
    /** Inherited description */
    const INHERITED = 'defined in TestBase';
    const MY_CONSTANT = 'defined in TestBase';

    public $propPublic = 'defined in TestBase (public)';

    /** @var string $testBasePrivate Inherited desc */
    private $testBasePrivate = 'defined in TestBase (private)';
    private $propPrivate = 'defined in TestBase (private)';
    protected $propProtected = 'defined only in TestBase (protected)';
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
        \bdk\Debug::_log('this group\'s label should be', get_class($this) . '->' . __FUNCTION__);
        \bdk\Debug::_groupEnd();
    }

    public static function testBaseStatic()
    {
        \bdk\Debug::_group();
        \bdk\Debug::_log('this group\'s label will be ' . __CLASS__ . '::' . __FUNCTION__ . ' regardless if called from inherited class :(');
        \bdk\Debug::_groupEnd();
    }
}


