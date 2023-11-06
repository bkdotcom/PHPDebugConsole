<?php

namespace bdk\Test\Debug\Fixture;

return array(
    'anonymous' => new class () {
        const A = 'aye';

        public $b = 'bee';

        public function anon()
        {
        }
    },
    'implements' => new class () implements \IteratorAggregate {
        /**
         * Implements Iterator Aggregate
         *
         * @return Traversable
         */
        public function getIterator(): \Traversable
        {
            return new \ArrayIterator($this);
        }
    },
    'stdClass' => new class () extends \stdClass {
        const TWELVE = 12;

        public $thing = 'hammer';

        /**
         * Anonymous method
         *
         * @return void
         */
        public function myMethod()
        {
        }
    },
    /**
     * Test1
     *
     * @method bool magic()
     */
    'test1' => new class () extends AnonBase {
        const PI = 3.14159265359;

        public $color = 'red';

        public $pro = 'go';

        public function test1()
        {
        }
    },
    'test2' => new class () extends AnonBase {
        const PI = 3.14159265359;

        public $color = 'blue';

        public $pro = 'schmo';

        public function test2()
        {
        }
    },
);
