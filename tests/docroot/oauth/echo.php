<?php

header('Content-Type: application/x-www-form-urlencoded');

echo \http_build_query(
    $serverRequest->getQueryParams() + ($serverRequest->getParsedBody() ?: array())
);
