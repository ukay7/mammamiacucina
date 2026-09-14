<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Admin roles are installed by migration. Create the first account with admin:create.
        // Never seed a public/default administrator password.
    }
}
