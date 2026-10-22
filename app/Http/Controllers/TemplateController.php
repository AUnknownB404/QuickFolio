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
        return $this->renderPreview($template);
    }

    public function preview(Template $template)
    {
        return $this->renderPreview($template);
    }

    private function renderPreview(Template $template)
    {
        if (! $template->is_published) {
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
                    ['label' => 'Projects', 'url' => '#projects'],
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
                'hero_cta_primary_url' => '#projects',
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
                'projects_description' => 'A selection of digital products built with thoughtful design and reliable engineering.',
                'projects_list' => [
                    ['title' => 'Nexus SaaS Dashboard', 'category' => 'UI/UX Design', 'image' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=800&q=80', 'url' => 'https://example.com'],
                    ['title' => 'CloudScale Analytics', 'category' => 'Web App', 'image' => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=800&q=80', 'url' => 'https://example.com'],
                    ['title' => 'Cryptographic Design System', 'category' => 'Design System', 'image' => 'https://images.unsplash.com/photo-1507238691740-187a5b1d37b8?auto=format&fit=crop&w=800&q=80', 'url' => 'https://example.com'],
                ],
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
                'contact_title' => 'Let’s Build Something Together',
                'contact_description' => 'Have a project in mind or want to discuss engineering leadership? Drop a line below.',
                'contact_email' => 'alex.morgan@example.com',
                'contact_phone' => '+1 (555) 234-5678',
                'contact_location' => 'San Francisco, CA',
                'contact_form_enabled' => true,
            ],
        ];

        $demoPortfolio = [
            'google_form_url' => 'https://docs.google.com/forms/d/e/1FAIpQLSf3GByS76wY0PTzNualaX5H1MsriklHss5-oaTRDhTJnz95mw/viewform?usp=pp_url&entry.2138340315=Test&entry.2068715591=test@gmail.com&entry.1647174915=Test+messages',
            'google_form_name_entry' => '2138340315',
            'google_form_email_entry' => '2068715591',
            'google_form_message_entry' => '1647174915',
        ];

        return Inertia::render('Marketplace/Show', [
            'template' => $template,
            'demoContent' => $demoContent,
            'designSettings' => [],
            'demoPortfolio' => $demoPortfolio,
        ]);
    }
}
