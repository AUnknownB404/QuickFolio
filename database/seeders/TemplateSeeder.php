<?php

namespace Database\Seeders;

use App\Models\FieldDefinition;
use App\Models\Template;
use App\Models\TemplateCategory;
use App\Models\TemplateMedia;
use App\Models\TemplateSection;
use App\Models\TemplateSectionField;
use Illuminate\Database\Seeder;

class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        $devCategory = TemplateCategory::firstOrCreate(
            ['slug' => 'developer'],
            ['name' => 'Developer']
        );

        $designerCategory = TemplateCategory::firstOrCreate(
            ['slug' => 'designer'],
            ['name' => 'Designer']
        );

        $minimalCategory = TemplateCategory::firstOrCreate(
            ['slug' => 'minimal'],
            ['name' => 'Minimal']
        );

        $templatesData = [
            [
                'name' => 'Emerald Minimalist',
                'slug' => 'emerald-minimalist',
                'category_id' => $minimalCategory->id,
                'description' => 'A clean, sophisticated, forest-emerald green portfolio template designed for modern professionals and creatives with 100vh sections.',
                'thumbnail' => 'https://pub-3e54a7c219cc4b1c8b8faf9ad3427681.r2.dev/templates/7C14WReF3WIcjqxhNgnJqlqaQK4cC2cirDhFFs5D.png',
                'preview_url' => '/preview/emerald-minimalist',
                'is_published' => true,
            ],
            [
                'name' => 'Dark Orange Designer',
                'slug' => 'dark-orange-designer',
                'category_id' => $designerCategory->id,
                'description' => 'A dark-themed professional UI/UX designer portfolio with vibrant orange accents, circular skill progress rings, and project filtering.',
                'thumbnail' => 'https://pub-3e54a7c219cc4b1c8b8faf9ad3427681.r2.dev/templates/l8LVEp0f3wpzHpcEDkHOiq17JjobYbXk2nXEcbdB.png',
                'preview_url' => '/preview/dark-orange-designer',
                'is_published' => true,
            ],
            [
                'name' => 'Ivory Editorial',
                'slug' => 'ivory-editorial',
                'category_id' => $minimalCategory->id,
                'description' => 'A refined editorial portfolio with warm paper tones, cobalt details, an asymmetric project gallery, and subtle motion.',
                'thumbnail' => 'https://pub-3e54a7c219cc4b1c8b8faf9ad3427681.r2.dev/templates/hb8PGN7QbSyf1DeMZBAnf4jdQHyV2F96t7LvyQN2.png',
                'preview_url' => '/preview/ivory-editorial',
                'is_published' => true,
            ],
            [
                'name' => 'Bento Grid',
                'slug' => 'bento-grid',
                'category_id' => $devCategory->id,
                'description' => 'A modular portfolio with an expressive bento layout, oversized typography, a focused acid-lime accent, and animated project tiles.',
                'thumbnail' => 'https://pub-3e54a7c219cc4b1c8b8faf9ad3427681.r2.dev/templates/x60h5AdCKlQXmKFbg6Guv9fvHyLmd7GIRet3mx7U.png',
                'preview_url' => '/preview/bento-grid',
                'is_published' => true,
            ],
            [
                'name' => 'Cobalt Brutalist',
                'slug' => 'cobalt-brutalist',
                'category_id' => $devCategory->id,
                'description' => 'A bold neo-brutalist portfolio with cobalt accents, hard-edged cards, skill bars, project links, and a contact form.',
                'thumbnail' => 'https://pub-3e54a7c219cc4b1c8b8faf9ad3427681.r2.dev/templates/GRXToW4zwZwBnHpDmetSrdRntyon7GrZIUAq0At4.png',
                'preview_url' => '/preview/cobalt-brutalist',
                'is_published' => true,
            ],
        ];

        $sections = ['navbar', 'header', 'about', 'journey', 'skills', 'portfolio', 'contact'];
        $fieldDefs = FieldDefinition::all()->keyBy('field_key');

        foreach ($templatesData as $tData) {
            $template = Template::updateOrCreate(
                ['slug' => $tData['slug']],
                $tData
            );

            if ($template->thumbnail) {
                TemplateMedia::updateOrCreate(
                    ['template_id' => $template->id, 'media_path' => $template->thumbnail],
                    ['media_type' => 'thumbnail', 'display_order' => 1]
                );
            }

            $templateSections = $tData['slug'] === 'cobalt-brutalist'
                ? [...$sections, 'services']
                : $sections;

            foreach ($templateSections as $order => $secKey) {
                $section = TemplateSection::updateOrCreate(
                    ['template_id' => $template->id, 'section_key' => $secKey],
                    ['is_enabled' => true, 'display_order' => $order]
                );

                $keysForSection = match ($secKey) {
                    'navbar' => $tData['slug'] === 'cobalt-brutalist'
                        ? ['logo_text', 'logo_image']
                        : ['logo_text'],
                    'header' => ['hero_greeting', 'hero_name', 'hero_role', 'hero_description', 'hero_image', 'hero_cta_primary_text', 'hero_cta_primary_url', 'hero_social_links'],
                    'about' => ['about_title', 'about_description', 'about_image', 'about_cv_file'],
                    'journey' => ['journey_title', 'journey_subtitle', 'journey_list'],
                    'skills' => ['skills_title', 'skills_description', 'skills_list'],
                    'portfolio' => ['projects_title', 'projects_subtitle', 'projects_list'],
                    'contact' => ['contact_title', 'contact_description', 'contact_email', 'contact_phone', 'contact_location'],
                    'services' => ['services_title', 'services_subtitle', 'services_list'],
                    default => [],
                };

                if ($tData['slug'] === 'cobalt-brutalist') {
                    $keysForSection = array_merge($keysForSection, match ($secKey) {
                        'navbar' => [],
                        'header' => ['hero_background_image', 'hero_cta_secondary_text', 'hero_cta_secondary_url'],
                        'about' => ['about_subtitle', 'about_highlights', 'about_cta_text', 'about_cta_url'],
                        'skills' => ['skills_subtitle', 'skills_show_level', 'skills_show_icon'],
                        'portfolio' => ['projects_description', 'projects_show_featured'],
                        'contact' => ['contact_subtitle', 'contact_social_links', 'contact_form_enabled'],
                        default => [],
                    });
                }

                foreach ($keysForSection as $fKey) {
                    if (isset($fieldDefs[$fKey])) {
                        TemplateSectionField::updateOrCreate(
                            [
                                'template_section_id' => $section->id,
                                'field_definition_id' => $fieldDefs[$fKey]->id,
                            ],
                            [
                                'is_required' => in_array($fKey, ['hero_name', 'hero_role', 'contact_email']),
                                'is_visible' => true,
                            ]
                        );
                    }
                }
            }
        }
    }
}
