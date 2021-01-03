<?php

use Illuminate\Support\Env;

return array(

    'key' => Env::get('PHPDEBUGCONSOLE_KEY', \substr(\md5(\uniqid(\rand(), true)), 0, 10)),

    /*
        Laravel specific
    */
    'laravel' => [
        'auth'           => true,  // Display Laravel authentication status
        'cacheEvents'    => true,  // Display cache events
        'config'         => true,  // Display config settings
        'db'             => true,  // Show database (PDO) queries and bindings
        'events'         => true,  // All events fired
        'gate'           => true,  // Display Laravel Gate checks
        'laravel'        => true,  // Laravel version and environment
        'mail'           => true,  // Catch mail messages
        'models'         => true,  // Display models
        'route'          => true,  // Current route information
        'session'        => true,  // Display initial session data
        'views'          => true,  // Views with their data
    ],

    /*
        Laravel specific options
    */
    'options' => [
        'cacheEvents' => [
            'values' => true,   // collect cache values
        ],
        'route' => [
            'label' => true,    // show complete route
        ],
        'views' => [
            'data' => 'type',    // bool|'type'
        ],
    ],
);
