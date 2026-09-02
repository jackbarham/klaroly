<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeds the system defaults every account relies on, then one realistic demo
 * account. Both seeders are safe to run more than once.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SystemDefaultsSeeder::class,
            DemoAccountSeeder::class,
        ]);
    }
}
