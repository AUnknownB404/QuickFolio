<?php

namespace Database\Seeders;

use App\Models\TemplateCategory;
use Illuminate\Database\Seeder;

class TemplateCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Developer', 'slug' => 'developer'],
            ['name' => 'Designer', 'slug' => 'designer'],
            ['name' => 'Student', 'slug' => 'student'],
            ['name' => 'Freelancer', 'slug' => 'freelancer'],
            ['name' => 'Photographer', 'slug' => 'photographer'],
            ['name' => 'Minimal', 'slug' => 'minimal'],
        ];

        foreach ($categories as $category) {
            TemplateCategory::updateOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
