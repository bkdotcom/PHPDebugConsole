<?php

header('Content-Type: application/x-www-form-urlencoded');

echo \http_build_query(array(
    'oauth_token' => 'request_token',
    'oauth_token_secret' => 'request_token_secret',
));
