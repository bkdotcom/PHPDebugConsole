<?php

return array(
    'anonymous' => new class () {
    },
    'stdClass' => new class () extends \stdClass {
        /**
         * Anonymous method
         *
         * @return void
         */
        public function myMethod()
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
    }
);
