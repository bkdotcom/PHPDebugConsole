<?php

header('Content-Type: application/x-www-form-urlencoded');

echo \http_build_query(array(
    'oauth_token' => 'access_token',
    'oauth_token_secret' => 'access_token_secret',
));
