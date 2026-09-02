<?php

/*
 * The demo account seeded by DemoAccountSeeder, which doubles as the App
 * Store review account. Nothing outside config/ reads env(), so the seeder
 * reads this key.
 */
return [

    // Password for ellie@example.com, the demo account's owner.
    'password' => env('DEMO_PASSWORD', 'password'),

];
