<?php

namespace App\Services;

use App\Models\Template;

class TemplateDemoData
{
    public const ASSET_HOST = 'pub-3e54a7c219cc4b1c8b8faf9ad3427681.r2.dev';

    public function forTemplate(Template $template): array
    {
        return [
            'content' => [
                'navbar' => [
                    'logo_text' => $template->name,
                    'logo_image' => 'https://'.self::ASSET_HOST.'/Demo/Logo%20Green.png',
                    'logo_url' => 'https://'.self::ASSET_HOST.'/Demo/Logo%20Green.png',
                    'sticky_nav' => true,
                    'show_logo' => true,
                    'nav_links' => [
                        ['label' => 'About', 'url' => '#about'],
                        ['label' => 'Journey', 'url' => '#journey'],
                        ['label' => 'Skills', 'url' => '#skills'],
                        ['label' => 'Projects', 'url' => '#projects'],
                        ['label' => 'Contact', 'url' => '#contact'],
                    ],
                    'cta_text' => 'Let’s Talk',
                    'cta_url' => '#contact',
                ],
                'header' => [
                    'hero_greeting' => 'Hello, World 👋',
                    'hero_name' => 'Akash Hossen',
                    'hero_role' => 'Senior UI/UX & Full-Stack Engineer',
                    'hero_description' => 'I’m Md Akash Hossen, a Software Engineering student at Daffodil International University and an aspiring full-stack developer. I build practical web applications with PHP, Laravel, Vue.js, JavaScript, and MySQL, focusing on clear interfaces and useful functionality.',
                    'hero_image' => 'https://'.self::ASSET_HOST.'/Demo/Hero.png',
                    'hero_background_image' => null,
                    'hero_cta_primary_text' => 'Explore Works',
                    'hero_cta_primary_url' => '#projects',
                    'hero_cta_secondary_text' => 'Let’s Connect',
                    'hero_cta_secondary_url' => '#contact',
                    'hero_social_links' => [
                        ['label' => 'GitHub', 'url' => 'https://github.com/akashhossen', 'icon' => 'github'],
                        ['label' => 'LinkedIn', 'url' => 'https://www.linkedin.com/', 'icon' => 'linkedin'],
                        ['label' => 'Facebook', 'url' => 'https://www.facebook.com/', 'icon' => 'facebook'],
                    ],
                ],
                'about' => [
                    'about_title' => 'I build useful web products from idea to implementation.',
                    'about_subtitle' => 'A little about my background and how I work.',
                    'about_description' => 'I\'m a Software Engineering student and full-stack development learner. My work so far includes CRUD-based systems, authentication, database-driven workflows, and responsive interfaces using Laravel, Vue 3, Inertia.js, and Tailwind CSS.',
                    'about_image' => 'https://'.self::ASSET_HOST.'/Demo/About.png',
                    'about_cv_file' => 'https://'.self::ASSET_HOST.'/Demo/Md_Akash_Hossen_CV.pdf',
                    'about_highlights' => [
                        ['label' => 'Projects Completed', 'value' => '12+'],
                        ['label' => 'Technologies', 'value' => '8+'],
                        ['label' => 'Learning Mindset', 'value' => 'Always'],
                    ],
                    'about_cta_text' => 'Download My CV',
                    'about_cta_url' => 'https://'.self::ASSET_HOST.'/Demo/Md_Akash_Hossen_CV.pdf',
                ],
                'journey' => [
                    'journey_title' => 'My journey so far.',
                    'journey_subtitle' => 'Key milestones and roles across industry-leading teams.',
                    'journey_list' => [
                        ['period' => '2024 - Present', 'title' => 'Software Engineering Student', 'company' => '@ Daffodil International University', 'description' => 'Studying Software Engineering while building practical web applications and strengthening full-stack development skills.'],
                        ['period' => '2023 - 2024', 'title' => 'Full-Stack Development Learner', 'company' => '@Ostad App', 'description' => 'Completed an intensive Ostad training journey in PHP, Laravel, and Vue.js, applying the skills through hands-on projects.'],
                        ['period' => '2022 - 2023', 'title' => 'Junior Full-Stack Development Learner', 'company' => 'Self-taught — nights and weekends', 'description' => 'Started learning web development fundamentals and progressed toward building database-driven applications.'],
                        ['period' => '2022', 'title' => 'Started learning to code', 'company' => 'Self-taught — nights and weekends', 'description' => 'Began learning web development, then advanced through HTML, CSS, JavaScript, PHP, and Laravel.'],
                    ],
                ],
                'skills' => [
                    'skills_title' => 'Technical Expertise',
                    'skills_subtitle' => 'Tools and technologies I use to turn ideas into working products.',
                    'skills_description' => 'What I work with.',
                    'skills_list' => [
                        ['label' => 'Vue 3 & Inertia', 'percent' => 95],
                        ['label' => 'Laravel & PHP', 'percent' => 90],
                        ['label' => 'JavaScript', 'percent' => 88],
                        ['label' => 'Tailwind CSS', 'percent' => 98],
                        ['label' => 'HTML', 'percent' => 92],
                        ['label' => 'MySQL', 'percent' => 78],
                        ['label' => 'UI/UX Design', 'percent' => 85],
                    ],
                    'skills_show_level' => true,
                    'skills_show_icon' => true,
                ],
                'projects' => [
                    'projects_title' => 'Selected work I\'m proud of.',
                    'projects_subtitle' => 'A curated showcase of recent production builds and design systems.',
                    'projects_description' => 'A selection of digital products built with thoughtful design and reliable engineering.',
                    'projects_list' => [
                        ['title' => 'Sales & Invoice Management System', 'category' => 'Web App', 'image' => 'https://'.self::ASSET_HOST.'/Demo/POS.png', 'url' => 'https://example.com'],
                        ['title' => 'Car Rental Management System', 'category' => 'Web App', 'image' => 'https://'.self::ASSET_HOST.'/Demo/Car.webp', 'url' => 'https://example.com'],
                        ['title' => 'Personal Portfolio Website', 'category' => 'Web Design', 'image' => 'https://'.self::ASSET_HOST.'/Demo/Portfolio.png', 'url' => 'https://example.com'],
                        ['title' => 'AI Tourism Platform Concept', 'category' => 'System Design', 'image' => 'https://'.self::ASSET_HOST.'/Demo/Random.png', 'url' => 'https://example.com'],
                        ['title' => 'Library Management System', 'category' => 'Web App', 'image' => 'https://'.self::ASSET_HOST.'/Demo/library.jpg', 'url' => 'https://example.com'],
                    ],
                    'projects_show_featured' => true,
                ],
                'services' => [
                    'services_title' => 'What I Can Do For You',
                    'services_subtitle' => 'End-to-end expertise to bring your next idea to life.',
                    'services_list' => [
                        ['title' => 'Product Design', 'description' => 'Research-led user experiences and polished interfaces that make complex products feel simple.', 'icon' => 'palette'],
                        ['title' => 'Web Development', 'description' => 'Fast, accessible websites and applications built for real users and real-world scale.', 'icon' => 'code'],
                        ['title' => 'Design Systems', 'description' => 'Flexible component systems that help teams ship consistent, high-quality experiences.', 'icon' => 'layers'],
                    ],
                ],
                'contact' => [
                    'contact_title' => 'Get in touch.',
                    'contact_subtitle' => 'Have a project in mind? Let’s build something useful together.',
                    'contact_description' => 'Have an idea, a question, or a project in mind? Send a message and I\'ll get back to you soon.',
                    'contact_email' => 'mdakashhossen.dev@gmail.com',
                    'contact_phone' => '+880 19732-07198',
                    'contact_location' => 'Dhaka, Bangladesh',
                    'contact_form_enabled' => true,
                    'contact_social_links' => [
                        ['label' => 'GitHub', 'url' => 'https://github.com/akashhossen', 'icon' => 'github'],
                        ['label' => 'LinkedIn', 'url' => 'https://www.linkedin.com/', 'icon' => 'linkedin'],
                        ['label' => 'Facebook', 'url' => 'https://www.facebook.com/', 'icon' => 'facebook'],
                    ],
                ],
            ],
            'portfolio' => [
                'google_form_url' => 'https://docs.google.com/forms/d/e/1FAIpQLSf3GByS76wY0PTzNualaX5H1MsriklHss5-oaTRDhTJnz95mw/viewform?usp=pp_url&entry.2138340315=Test&entry.2068715591=test@gmail.com&entry.1647174915=Test+messages',
                'google_form_name_entry' => '2138340315',
                'google_form_email_entry' => '2068715591',
                'google_form_message_entry' => '1647174915',
            ],
        ];
    }
}
