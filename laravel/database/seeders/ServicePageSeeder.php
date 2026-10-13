<?php

namespace Database\Seeders;

use App\Models\ServicePage;
use Illuminate\Database\Seeder;

class ServicePageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = json_decode(file_get_contents(database_path('seeders/data/service-pages.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($pages as $page) {
            ServicePage::firstOrCreate(['slug' => $page['slug']], $page);
        }
    }
}
