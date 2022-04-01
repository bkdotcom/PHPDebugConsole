<?php

namespace bdk\Test\Debug\Fixture;

/**
 * Implement me!
 *
 * @property      bool $magicProp     I'm avail via __get()
 * @property-read bool $magicReadProp Read Only!
 *
 * @method void presto($foo, integer $int = 1, $bool = true, $null = null) I'm a magic method
 * @method static void prestoStatic(string $noDefault, $arr = array(), $opts=array('a'=>'ay','b'=>'bee')) I'm a static magic method
 *
 * @author  Brad Kent <bkfake-github@yahoo.com> Author desc is non-standard
 * @link    https://github.com/bkdotcom/PHPDebugConsole
 * @see     subclass::method()
 * @unknown Some phpdoc tag
 */
interface SomeInterface
{
    /**
     * SomeInterface summary
     *
     * SomeInterface description
     *
     * @return self
     */
    public function someMethod();
}
