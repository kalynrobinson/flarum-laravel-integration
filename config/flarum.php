<?php
return [
    // URL to your Flarum forum
    'url' => env('FLARUM_URL'),

    // Domain of your main site (without http://)
    'root' => env('ROOT_DOMAIN'),

    // Create a random key in the api_keys table of your Flarum forum
    'api_key' => env('FLARUM_API_KEY'),

    // Random token to create passwords
    'password_token' => env('FLARUM_PASSWORD_TOKEN'),
    
    // How many days should the login be valid
    'lifetime_in_days' => 99999,
];
