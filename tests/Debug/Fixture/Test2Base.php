<?php

namespace bdk\Test\Debug\Fixture;

/**
 * TestBase
 *
 * TestBase description
 *
 * @property      boolean $magicProp     I'm avail via __get()
 * @property-read boolean $magicReadProp Read Only!
 *
 * @method methConstTest($mode = self::WORD) test constant as param
 */
class Test2Base
{
    const WORD = 'bird';

    /**
     * This property is important
     *
     * @var string
     */
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
     * magic method
     *
     * @param string $name Method being called
     * @param array  $args Arguments passed
     *
     * @return mixed
     */
    public function __call($name, $args)
    {
    }

    /**
     * Test constant as default value
     *
     * @param string $param only php >= 5.4.6 can get the name of the constant used
     *
     * @return void
     */
    public function constDefault($param = self::WORD)
    {
    }
}
