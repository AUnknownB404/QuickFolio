<?php

namespace Database\Seeders;

use App\Models\FieldDefinition;
use Illuminate\Database\Seeder;

class FieldDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $fields = [
            // Navbar
            ['field_key' => 'logo_text', 'label' => 'Logo Text', 'data_type' => 'string', 'is_repeatable' => false],
            ['field_key' => 'logo_image', 'label' => 'Logo Image', 'data_type' => 'image', 'is_repeatable' => false],
            ['field_key' => 'sticky_nav', 'label' => 'Sticky Navigation', 'data_type' => 'boolean', 'is_repeatable' => false],
            ['field_key' => 'show_logo', 'label' => 'Show Logo', 'data_type' => 'boolean', 'is_repeatable' => false],

            // Hero / Header
            ['field_key' => 'hero_greeting', 'label' => 'Greeting', 'data_type' => 'string', 'is_repeatable' => false],
            ['field_key' => 'hero_name', 'label' => 'Name', 'data_type' => 'string', 'is_repeatable' => false],
            ['field_key' => 'hero_role', 'label' => 'Role / Title', 'data_type' => 'string', 'is_repeatable' => false],
            ['field_key' => 'hero_description', 'label' => 'Description / Bio', 'data_type' => 'text', 'is_repeatable' => false],
            ['field_key' => 'hero_image', 'label' => 'Profile Image', 'data_type' => 'image', 'is_repeatable' => false],
            ['field_key' => 'hero_background_image', 'label' => 'Background Image', 'data_type' => 'image', 'is_repeatable' => false],
            ['field_key' => 'hero_cta_primary_text', 'label' => 'Primary CTA Text', 'data_type' => 'string', 'is_repeatable' => false],
            ['field_key' => 'hero_cta_primary_url', 'label' => 'Primary CTA URL', 'data_type' => 'string', 'is_repeatable' => false],
            ['field_key' => 'hero_cta_secondary_text', 'label' => 'Secondary CTA Text', 'data_type' => 'string', 'is_repeatable' => false],
            ['field_key' => 'hero_cta_secondary_url', 'label' => 'Secondary CTA URL', 'data_type' => 'string', 'is_repeatable' => false],
            ['field_key' => 'hero_social_links', 'label' => 'Social Links', 'data_type' => 'array', 'is_repeatable' => true],

            // About
            ['field_key' => 'about_title', 'label' => 'About Section Title', 'data_type' => 'string', 'is_repeatable' => false],
            ['field_key' => 'about_subtitle', 'label' => 'About Subtitle', 'data_type' => 'string', 'is_repeatable' => false],
            ['field_key' => 'about_description', 'label' => 'About Description', 'data_type' => 'rich_text', 'is_repeatable' => false],
            ['field_key' => 'about_image', 'label' => 'About Image', 'data_type' => 'image', 'is_repeatable' => false],
            ['field_key' => 'about_highlights', 'label' => 'Highlights / Stats', 'data_type' => 'array', 'is_repeatable' => true],
            ['field_key' => 'about_cv_file', 'label' => 'CV / Resume File', 'data_type' => 'file', 'is_repeatable' => false],
            ['field_key' => 'about_cta_text', 'label' => 'About CTA Text', 'data_type' => 'string', 'is_repeatable' => false],
            ['field_key' => 'about_cta_url', 'label' => 'About CTA URL', 'data_type' => 'string', 'is_repeatable' => false],

            // Professional journey
            ['field_key' => 'journey_title', 'label' => 'Journey Section Title', 'data_type' => 'string', 'is_repeatable' => false],
            ['field_key' => 'journey_subtitle', 'label' => 'Journey Subtitle', 'data_type' => 'string', 'is_repeatable' => false],
            ['field_key' => 'journey_list', 'label' => 'Professional Journey', 'data_type' => 'array', 'is_repeatable' => true],

            // Skills
            ['field_key' => 'skills_title', 'label' => 'Skills Section Title', 'data_type' => 'string', 'is_repeatable' => false],
            ['field_key' => 'skills_subtitle', 'label' => 'Skills Subtitle', 'data_type' => 'string', 'is_repeatable' => false],
            ['field_key' => 'skills_description', 'label' => 'Skills Description', 'data_type' => 'text', 'is_repeatable' => false],
            ['field_key' => 'skills_list', 'label' => 'Skills List', 'data_type' => 'array', 'is_repeatable' => true],
            ['field_key' => 'skills_show_level', 'label' => 'Show Proficiency Level', 'data_type' => 'boolean', 'is_repeatable' => false],
            ['field_key' => 'skills_show_icon', 'label' => 'Show Skill Icons', 'data_type' => 'boolean', 'is_repeatable' => false],

            // Projects
            ['field_key' => 'projects_title', 'label' => 'Projects Section Title', 'data_type' => 'string', 'is_repeatable' => false],
            ['field_key' => 'projects_subtitle', 'label' => 'Projects Subtitle', 'data_type' => 'string', 'is_repeatable' => false],
            ['field_key' => 'projects_description', 'label' => 'Projects Description', 'data_type' => 'text', 'is_repeatable' => false],
            ['field_key' => 'projects_list', 'label' => 'Projects List', 'data_type' => 'array', 'is_repeatable' => true],
            ['field_key' => 'projects_show_featured', 'label' => 'Show Featured Badge', 'data_type' => 'boolean', 'is_repeatable' => false],

            // Contact
            ['field_key' => 'contact_title', 'label' => 'Contact Section Title', 'data_type' => 'string', 'is_repeatable' => false],
            ['field_key' => 'contact_subtitle', 'label' => 'Contact Subtitle', 'data_type' => 'string', 'is_repeatable' => false],
            ['field_key' => 'contact_description', 'label' => 'Contact Description', 'data_type' => 'text', 'is_repeatable' => false],
            ['field_key' => 'contact_email', 'label' => 'Email Address', 'data_type' => 'email', 'is_repeatable' => false],
            ['field_key' => 'contact_phone', 'label' => 'Phone Number', 'data_type' => 'string', 'is_repeatable' => false],
            ['field_key' => 'contact_location', 'label' => 'Location', 'data_type' => 'string', 'is_repeatable' => false],
            ['field_key' => 'contact_form_enabled', 'label' => 'Enable Contact Form', 'data_type' => 'boolean', 'is_repeatable' => false],
            ['field_key' => 'contact_social_links', 'label' => 'Contact Social Links', 'data_type' => 'array', 'is_repeatable' => true],

            // Services Section
            ['field_key' => 'services_title', 'label' => 'Services Section Title', 'data_type' => 'string', 'is_repeatable' => false],
            ['field_key' => 'services_subtitle', 'label' => 'Services Subtitle', 'data_type' => 'text', 'is_repeatable' => false],
            ['field_key' => 'services_list', 'label' => 'Services List', 'data_type' => 'array', 'is_repeatable' => true],
        ];

        foreach ($fields as $field) {
            FieldDefinition::updateOrCreate(['field_key' => $field['field_key']], $field);
        }
    }
}
