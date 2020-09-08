<?php

$anonymous = new class () extends \stdClass {
};

echo 'get_class(anonymous) = ' . get_class($anonymous);

return $anonymous;
