<?php

namespace Database\Seeders;

use App\Models\Tool;
use Illuminate\Database\Seeder;

class ToolSeeder extends Seeder
{
    public function run(): void
    {
        $tools = [
            [
                'name' => 'Meta Ads Generator',
                'slug' => 'meta-ads-generator',
                'description' => 'Generate AI-powered ad creatives for Facebook and Instagram in 3 sizes automatically.',
                'icon' => 'ad',
                'package_name' => 'juki/meta-ads-generator',
                'is_active' => true,
            ],
            [
                'name' => 'SEO Analyzer',
                'slug' => 'seo-analyzer',
                'description' => 'Analyze and optimize your website for search engines with actionable insights.',
                'icon' => 'seo',
                'package_name' => null,
                'is_active' => false,
            ],
        ];

        foreach ($tools as $tool) {
            Tool::firstOrCreate(
                ['slug' => $tool['slug']],
                $tool
            );
        }
    }
}
