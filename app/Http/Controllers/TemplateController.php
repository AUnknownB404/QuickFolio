<?php

namespace App\Http\Controllers;

use App\Models\Template;
use App\Models\TemplateCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TemplateController extends Controller
{
    public function index(Request $request)
    {
        $query = Template::with(['category', 'media'])
            ->where('is_published', true);

        if ($request->filled('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $templates = $query->latest()->paginate(9)->withQueryString();
        $categories = TemplateCategory::all();

        return Inertia::render('Marketplace/Index', [
            'templates' => $templates,
            'categories' => $categories,
            'filters' => $request->only(['search', 'category']),
        ]);
    }

    public function show(Template $template)
    {
        if (!$template->is_published) {
            abort(404);
        }

        $template->load(['category', 'media', 'sections.fields.fieldDefinition']);

        // Find other published templates for recommendations
        $relatedTemplates = Template::with(['category', 'media'])
            ->where('is_published', true)
            ->where('id', '!=', $template->id)
            ->limit(3)
            ->get();

        return Inertia::render('Marketplace/Show', [
            'template' => $template,
            'relatedTemplates' => $relatedTemplates,
        ]);
    }

    public function preview(Template $template)
    {
        if (!$template->is_published) {
            abort(404);
        }

        $template->load(['category', 'media', 'sections.fields.fieldDefinition']);

        // Supply demo content for live preview
        $demoContent = [
            'navbar' => [
                'logo_text' => $template->name,
                'nav_links' => [
                    ['label' => 'About', 'url' => '#about'],
                    ['label' => 'Journey', 'url' => '#journey'],
                    ['label' => 'Skills', 'url' => '#skills'],
                    ['label' => 'Portfolio', 'url' => '#portfolio'],
                    ['label' => 'Contact', 'url' => '#contact'],
                ],
                'cta_text' => 'Let’s Talk',
                'cta_url' => '#contact',
            ],
            'header' => [
                'hero_greeting' => 'Hello, World 👋',
                'hero_name' => 'Alex Morgan',
                'hero_role' => 'Senior UI/UX & Full-Stack Engineer',
                'hero_description' => 'Crafting high-performance web applications and exceptional user interfaces with precision and passion.',
                'hero_image' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=800&q=80',
                'hero_cta_primary_text' => 'Explore Works',
                'hero_cta_primary_url' => '#portfolio',
            ],
            'about' => [
                'about_title' => 'Design-driven engineering for ambitious products.',
                'about_description' => 'With over 8 years of experience building modern web architectures, I bridge the gap between complex backend systems and intuitive, delightful front-end experiences.',
                'about_image' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=800&q=80',
                'about_cv_file' => '#',
            ],
            'journey' => [
                'journey_title' => 'Professional Journey',
                'journey_subtitle' => 'Key milestones and roles across industry-leading teams.',
                'journey_list' => [
                    ['period' => '2023 - Present', 'title' => 'Staff Product Engineer', 'company' => 'Acme Technologies', 'description' => 'Leading design systems and frontend architecture for enterprise SaaS products.'],
                    ['period' => '2020 - 2023', 'title' => 'Senior Full-Stack Developer', 'company' => 'Venture Studio', 'description' => 'Built high-throughput APIs and reactive Vue 3 applications for global clients.'],
                ],
            ],
            'skills' => [
                'skills_title' => 'Technical Expertise',
                'skills_description' => 'Core competencies and proficiency levels across modern web stacks.',
                'skills_list' => [
                    ['label' => 'Vue 3 & Inertia', 'percent' => 95],
                    ['label' => 'Laravel & PHP', 'percent' => 90],
                    ['label' => 'TypeScript', 'percent' => 88],
                    ['label' => 'Tailwind CSS', 'percent' => 98],
                    ['label' => 'UI/UX Design', 'percent' => 85],
                ],
            ],
            'projects' => [
                'projects_title' => 'Selected Works',
                'projects_subtitle' => 'A curated showcase of recent production builds and design systems.',
                'projects_list' => [
                    ['title' => 'Nexus SaaS Dashboard', 'category' => 'UI/UX Design', 'image' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=800&q=80', 'url' => 'https://example.com'],
                    ['title' => 'CloudScale Analytics', 'category' => 'Web App', 'image' => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=800&q=80', 'url' => 'https://example.com'],
                    ['title' => 'Cryptographic Design System', 'category' => 'Design System', 'image' => 'https://images.unsplash.com/photo-1507238691740-187a5b1d37b8?auto=format&fit=crop&w=800&q=80', 'url' => 'https://example.com'],
                ],
            ],
            'contact' => [
                'contact_title' => 'Let’s Build Something Together',
                'contact_description' => 'Have a project in mind or want to discuss engineering leadership? Drop a line below.',
                'contact_email' => 'alex.morgan@example.com',
                'contact_phone' => '+1 (555) 234-5678',
                'contact_location' => 'San Francisco, CA',
            ],
        ];

        return Inertia::render('Marketplace/Preview', [
            'template' => $template,
            'demoContent' => $demoContent,
            'designSettings' => [],
        ]);
    }
}
