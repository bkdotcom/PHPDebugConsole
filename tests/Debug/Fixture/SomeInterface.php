<?php

namespace bdk\Test\Debug\Fixture;

use foo\bar\baz;

/**
 * Implement me!
 *
 * @property      bool $magicProp     I'm avail via __get()
 * @property-read bool $magicReadProp Read Only!
 *
 * @method void presto($foo, integer $int = 1, $bool = true, $null = null) I'm a magic method
 * @method static void prestoStatic(string $noDefault, $arr = array(), $opts=array('a'=>'a\'y','b'=>'bee')) I'm a static magic method
 *
 * @author  Brad Kent <bkfake-github@yahoo.com> Author desc is non-standard
 * @link    https://github.com/bkdotcom/PHPDebugConsole
 * @see     subclass::method()
 * @unknown Some phpdoc tag
 */
interface SomeInterface
{
    /**
     * Interface summary
     */
    const SOME_CONSTANT = 'dingle';

    /**
     * SomeInterface summary
     *
     * Tests that self resolves to fully qualified SomeInterface
     *
     * @return self
     */
    public function someMethod();

    /**
     * SomeInterface summary
     *
     * @return InterfaceReturn
     */
    public function someMethod2();

    /**
     * Test that baz resolves to foo\bar\baz
     *
     * @return baz
     */
    public function someMethod4();

    /**
     * Test that TestObj resolves to bdk\Test\Debug\Fixture\TestObj
     *
     * @return TestObj
     */
    public function someMethod5();
}
