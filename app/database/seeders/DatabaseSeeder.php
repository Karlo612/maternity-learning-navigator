<?php

namespace Database\Seeders;

use App\Services\GovernanceImporter;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(GovernanceImporter::class)->import();
    }
}
