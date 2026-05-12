<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Computer Science',
                'slug' => 'computer-science',
                'description' => 'Research in computer science, algorithms, and software engineering',
                'status' => 'active',
            ],
            [
                'name' => 'Artificial Intelligence',
                'slug' => 'artificial-intelligence',
                'description' => 'Machine learning, deep learning, and AI applications',
                'status' => 'active',
            ],
            [
                'name' => 'Data Science',
                'slug' => 'data-science',
                'description' => 'Big data analytics, data mining, and statistical analysis',
                'status' => 'active',
            ],
            [
                'name' => 'Information Technology',
                'slug' => 'information-technology',
                'description' => 'IT infrastructure, networking, and cybersecurity',
                'status' => 'active',
            ],
            [
                'name' => 'Software Engineering',
                'slug' => 'software-engineering',
                'description' => 'Software development, testing, and project management',
                'status' => 'active',
            ],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }

        echo "✓ " . count($categories) . " categories seeded successfully!\n";
    }
}