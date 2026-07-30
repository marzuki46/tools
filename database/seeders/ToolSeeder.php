<?php

namespace Database\Seeders;

use App\Models\Tools\Tool;
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
                'description' => 'Analisis on-page SEO gratis: title, meta, heading, konten, gambar, link, OG tags, canonical, robots.',
                'icon' => 'seo',
                'package_name' => null,
                'is_active' => true,
            ],
            [
                'name' => 'Meta Title & Description Generator',
                'slug' => 'meta-generator',
                'description' => 'Generate SEO meta title & description high-CTR dari konten yang sudah dibuat.',
                'icon' => 'meta',
                'package_name' => null,
                'is_active' => true,
            ],
            [
                'name' => 'Schema Markup Generator',
                'slug' => 'schema-markup',
                'description' => 'Buat JSON-LD schema.org untuk Article, FAQ, Product, LocalBusiness, BreadcrumbList, Review, Recipe, Video, HowTo, Event — auto-fill dari konten & AI.',
                'icon' => 'schema',
                'package_name' => null,
                'is_active' => true,
            ],
            [
                'name' => 'Keyword Research',
                'slug' => 'keyword-research',
                'description' => 'AI-powered keyword research with LSI keywords and entity extraction.',
                'icon' => 'keyword',
                'package_name' => null,
                'is_active' => true,
            ],
            [
                'name' => 'Content Generator',
                'slug' => 'content-generator',
                'description' => 'Multi-phase AI content generation with SEO optimization.',
                'icon' => 'content',
                'package_name' => null,
                'is_active' => true,
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
