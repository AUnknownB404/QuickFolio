<?php

namespace Tests\Feature;

use App\Models\FieldDefinition;
use App\Models\PortfolioFieldValue;
use App\Models\PortfolioSection;
use App\Models\Template;
use App\Models\User;
use App\Services\PortfolioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class PortfolioBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketplace_index_can_be_rendered()
    {
        $this->seed();
        $response = $this->get('/templates');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Marketplace/Index'));
    }

    public function test_template_details_can_be_viewed()
    {
        $this->seed();
        $template = Template::published()->first();

        $response = $this->get("/templates/{$template->slug}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Marketplace/Show')
            ->where('template.slug', $template->slug)
            ->where('demoContent.header.hero_name', 'Alex Morgan')
            ->has('demoContent.projects.projects_list')
            ->where('demoPortfolio.google_form_url', 'https://docs.google.com/forms/d/e/1FAIpQLSf3GByS76wY0PTzNualaX5H1MsriklHss5-oaTRDhTJnz95mw/viewform?usp=pp_url&entry.2138340315=Test&entry.2068715591=test@gmail.com&entry.1647174915=Test+messages')
            ->where('demoPortfolio.google_form_name_entry', '2138340315')
            ->where('demoPortfolio.google_form_email_entry', '2068715591')
            ->where('demoPortfolio.google_form_message_entry', '1647174915')
        );
    }

    public function test_ivory_editorial_template_is_seeded_and_previewable()
    {
        $this->seed();

        $response = $this->get('/preview/ivory-editorial');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Marketplace/Show')
            ->where('template.slug', 'ivory-editorial')
            ->where('demoContent.header.hero_name', 'Alex Morgan')
        );
    }

    public function test_bento_grid_template_is_seeded_and_previewable()
    {
        $this->seed();

        $response = $this->get('/preview/bento-grid');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Marketplace/Show')
            ->where('template.slug', 'bento-grid')
            ->where('demoContent.header.hero_name', 'Alex Morgan')
        );
    }

    public function test_cobalt_brutalist_preview_includes_projects_and_services_demo_content()
    {
        $this->seed();

        $response = $this->get('/preview/cobalt-brutalist');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Marketplace/Show')
            ->where('template.slug', 'cobalt-brutalist')
            ->has('demoContent.projects.projects_list', 3)
            ->where('demoContent.projects.projects_title', 'Selected Works')
            ->has('demoContent.services.services_list', 3)
            ->where('demoContent.services.services_title', 'What I Can Do For You')
        );
    }

    public function test_dark_orange_template_uses_the_designer_category_without_a_missing_thumbnail()
    {
        $this->seed();
        $template = Template::with('category', 'media')
            ->where('slug', 'dark-orange-designer')
            ->firstOrFail();

        $this->assertSame('designer', $template->category->slug);
        $this->assertNull($template->thumbnail);
        $this->assertFalse($template->media->contains('media_type', 'thumbnail'));
    }

    public function test_cobalt_brutalist_template_is_seeded_with_editable_template_fields()
    {
        $this->seed();
        $template = Template::with('sections.fields.fieldDefinition')
            ->where('slug', 'cobalt-brutalist')
            ->firstOrFail();

        $this->assertSame('Cobalt Brutalist', $template->name);
        $this->assertEqualsCanonicalizing(
            ['navbar', 'header', 'about', 'journey', 'skills', 'projects', 'contact', 'services'],
            $template->sections->pluck('section_key')->all()
        );

        $fieldKeys = $template->sections
            ->flatMap(fn ($section) => $section->fields->pluck('fieldDefinition.field_key'))
            ->all();

        foreach ([
            'logo_text',
            'logo_image',
            'hero_social_links',
            'skills_list',
            'journey_list',
            'projects_list',
            'services_title',
            'services_subtitle',
            'services_list',
            'hero_cta_secondary_text',
            'about_highlights',
        ] as $fieldKey) {
            $this->assertContains($fieldKey, $fieldKeys);
        }

        $navbarFields = $template->sections
            ->firstWhere('section_key', 'navbar')
            ->fields
            ->pluck('fieldDefinition.field_key')
            ->all();
        $this->assertEqualsCanonicalizing(['logo_text', 'logo_image'], $navbarFields);

        $portfolio = app(PortfolioService::class)->createForUser(
            User::factory()->create(),
            $template,
            'Cobalt Portfolio'
        );

        $this->assertTrue($portfolio->sections->contains('section_key', 'services'));
    }

    public function test_navbar_templates_do_not_expose_navigation_or_cta_fields()
    {
        $this->seed();

        $templates = Template::with('sections.fields.fieldDefinition')->get();
        $this->assertNotEmpty($templates);

        foreach ($templates as $template) {
            $navbarFields = $template->sections
                ->firstWhere('section_key', 'navbar')
                ?->fields
                ->pluck('fieldDefinition.field_key')
                ->all() ?? [];

            $this->assertNotContains('nav_links', $navbarFields, $template->slug);
            $this->assertNotContains('cta_text', $navbarFields, $template->slug);
            $this->assertNotContains('cta_url', $navbarFields, $template->slug);
        }
    }

    public function test_fields_not_configured_for_the_template_and_boolean_values_are_saved()
    {
        $this->seed();
        $user = User::factory()->create();
        $template = Template::where('slug', 'emerald-minimalist')->firstOrFail();
        $portfolio = app(PortfolioService::class)->createForUser($user, $template, 'Field Value Persistence');
        $sections = $portfolio->sections->keyBy('section_key');
        $navbar = $sections->get('navbar');
        $skills = $sections->get('skills');
        $projects = $sections->get('projects');
        $logoImage = FieldDefinition::where('field_key', 'logo_image')->firstOrFail();
        $stickyNav = FieldDefinition::where('field_key', 'sticky_nav')->firstOrFail();
        $showLogo = FieldDefinition::where('field_key', 'show_logo')->firstOrFail();
        $showSkillLevel = FieldDefinition::where('field_key', 'skills_show_level')->firstOrFail();
        $showSkillIcon = FieldDefinition::where('field_key', 'skills_show_icon')->firstOrFail();
        $showFeaturedProject = FieldDefinition::where('field_key', 'projects_show_featured')->firstOrFail();

        $this->actingAs($user)->patch(route('portfolios.update', $portfolio), [
            'sections' => [
                [
                    'id' => $navbar->id,
                    'is_enabled' => true,
                    'field_values' => [
                        ['field_definition_id' => $logoImage->id, 'value' => '/storage/logo.png'],
                        ['field_definition_id' => $stickyNav->id, 'value' => true],
                        ['field_definition_id' => $showLogo->id, 'value' => false],
                    ],
                ],
                [
                    'id' => $skills->id,
                    'is_enabled' => true,
                    'field_values' => [
                        ['field_definition_id' => $showSkillLevel->id, 'value' => true],
                        ['field_definition_id' => $showSkillIcon->id, 'value' => false],
                    ],
                ],
                [
                    'id' => $projects->id,
                    'is_enabled' => true,
                    'field_values' => [
                        ['field_definition_id' => $showFeaturedProject->id, 'value' => true],
                    ],
                ],
            ],
        ])->assertRedirect();

        foreach ([
            [$navbar, $logoImage, '/storage/logo.png'],
            [$navbar, $stickyNav, true],
            [$navbar, $showLogo, false],
            [$skills, $showSkillLevel, true],
            [$skills, $showSkillIcon, false],
            [$projects, $showFeaturedProject, true],
        ] as [$section, $fieldDefinition, $expected]) {
            $savedValue = $section->fieldValues()
                ->where('field_definition_id', $fieldDefinition->id)
                ->firstOrFail()
                ->value;

            $this->assertSame($expected, $savedValue, $fieldDefinition->field_key);
        }
    }

    public function test_cta_url_string_accepts_in_page_fragment_links()
    {
        $this->seed();
        $user = User::factory()->create();
        $template = Template::where('slug', 'emerald-minimalist')->firstOrFail();
        $portfolio = app(PortfolioService::class)->createForUser($user, $template, 'CTA Link Test');
        $header = $portfolio->sections->firstWhere('section_key', 'header');
        $ctaUrl = FieldDefinition::where('field_key', 'hero_cta_primary_url')->firstOrFail();

        $this->assertSame('string', $ctaUrl->data_type);

        $response = $this->actingAs($user)->patch(route('portfolios.update', $portfolio), [
            'sections' => [[
                'id' => $header->id,
                'is_enabled' => true,
                'field_values' => [
                    ['field_definition_id' => $ctaUrl->id, 'value' => '#contact'],
                ],
            ]],
        ]);

        $response->assertRedirect();
        $this->assertSame(
            '#contact',
            PortfolioFieldValue::where('portfolio_section_id', $header->id)
                ->where('field_definition_id', $ctaUrl->id)
                ->firstOrFail()
                ->value
        );
    }

    public function test_authenticated_user_can_create_portfolio_from_template()
    {
        $this->seed();
        $user = User::factory()->create();
        $template = Template::published()->first();

        $response = $this->actingAs($user)
            ->post("/templates/{$template->slug}/use", [
                'title' => 'My Test Portfolio',
            ]);

        $this->assertDatabaseHas('portfolios', [
            'user_id' => $user->id,
            'template_id' => $template->id,
            'title' => 'My Test Portfolio',
        ]);

        $portfolio = $user->portfolios()->first();
        $response->assertRedirect(route('portfolios.edit', $portfolio));
    }

    public function test_user_can_switch_template_and_preserve_compatible_data()
    {
        $this->seed();
        $user = User::factory()->create();
        $templates = Template::published()->get();

        if ($templates->count() < 2) {
            $cat = $templates->first()->category_id;
            $t2 = Template::create([
                'name' => 'Alt Template',
                'slug' => 'alt-template',
                'category_id' => $cat,
                'description' => 'Alt description',
                'thumbnail' => '/img.png',
                'is_published' => true,
            ]);
            $templates->push($t2);
        }

        $portfolio = app(PortfolioService::class)->createForUser($user, $templates[0], 'Switch Test');

        $response = $this->actingAs($user)
            ->post(route('portfolios.switch-template', $portfolio), [
                'template_id' => $templates[1]->id,
            ]);

        $response->assertRedirect(route('portfolios.edit', $portfolio));

        $this->assertEquals($templates[1]->id, $portfolio->fresh()->template_id);
    }

    public function test_export_after_switching_templates_includes_assets_from_the_previous_template_directory()
    {
        $this->seed();
        $user = User::factory()->create();
        $bento = Template::where('slug', 'bento-grid')->firstOrFail();
        $cobalt = Template::where('slug', 'cobalt-brutalist')->firstOrFail();
        $portfolio = app(PortfolioService::class)->createForUser($user, $bento, 'Switched Portfolio');
        Storage::fake('public');

        $assetPath = "users/{$user->id}/templates/{$bento->id}/portfolios/{$portfolio->id}/profile.png";
        Storage::disk('public')->put($assetPath, 'previous-template-image');
        $header = $portfolio->sections()->where('section_key', 'header')->firstOrFail();
        PortfolioFieldValue::updateOrCreate(
            [
                'portfolio_section_id' => $header->id,
                'field_definition_id' => FieldDefinition::where('field_key', 'hero_image')->firstOrFail()->id,
            ],
            ['value' => '/storage/'.$assetPath]
        );

        $this->actingAs($user)
            ->post(route('portfolios.switch-template', $portfolio), ['template_id' => $cobalt->id])
            ->assertRedirect(route('portfolios.edit', $portfolio));

        $this->assertSame($cobalt->id, $portfolio->fresh()->template_id);

        $response = $this->actingAs($user)->get(route('portfolios.download', $portfolio));
        $response->assertDownload("portfolio-{$portfolio->slug}.zip");

        $archivePath = tempnam(sys_get_temp_dir(), 'portfolio_export_test_');
        $this->assertNotFalse($archivePath);
        file_put_contents($archivePath, $response->streamedContent());

        $archive = new ZipArchive;
        $this->assertTrue($archive->open($archivePath));
        try {
            $html = $archive->getFromName('index.html');
            $assetArchivePath = 'assets/'.substr(hash('sha256', $assetPath), 0, 12).'-profile.png';
            $this->assertIsString($html);
            $this->assertStringContainsString($assetArchivePath, $html);
            $this->assertSame('previous-template-image', $archive->getFromName($assetArchivePath));
        } finally {
            $archive->close();
            unlink($archivePath);
        }
    }

    public function test_user_can_save_google_form_settings_for_their_portfolio()
    {
        $this->seed();
        $user = User::factory()->create();
        $template = Template::published()->firstOrFail();
        $portfolio = app(PortfolioService::class)->createForUser($user, $template, 'Google Form Test');

        $sections = $portfolio->sections->map(fn (PortfolioSection $section) => [
            'id' => $section->id,
            'is_enabled' => $section->is_enabled,
            'field_values' => [],
        ])->all();

        $response = $this->actingAs($user)->patch(route('portfolios.update', $portfolio), [
            'sections' => $sections,
            'google_form_url' => 'https://docs.google.com/forms/d/e/form-id/viewform?usp=pp_url&entry.123=Sample+Name',
            'google_form_name_entry' => '123',
            'google_form_email_entry' => '456',
            'google_form_message_entry' => '789',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('portfolios', [
            'id' => $portfolio->id,
            'google_form_url' => 'https://docs.google.com/forms/d/e/form-id/viewform',
            'google_form_name_entry' => '123',
            'google_form_email_entry' => '456',
            'google_form_message_entry' => '789',
        ]);
        $portfolio->update(['status' => 'published']);
        $this->get(route('portfolios.published', $portfolio->slug))
            ->assertInertia(fn ($page) => $page
                ->component('Portfolios/PublicShow')
                ->where('portfolio.google_form_url', 'https://docs.google.com/forms/d/e/form-id/viewform')
            );

        $invalidResponse = $this->actingAs($user)->patch(route('portfolios.update', $portfolio), [
            'sections' => $sections,
            'google_form_url' => 'https://example.com/forms/d/e/form-id/viewform',
            'google_form_name_entry' => '123',
            'google_form_email_entry' => '456',
            'google_form_message_entry' => '789',
        ]);
        $invalidResponse->assertSessionHasErrors('google_form_url');
    }

    public function test_publishing_blocked_when_required_fields_missing()
    {
        $this->seed();
        $user = User::factory()->create();
        $template = Template::published()->first();

        $portfolio = app(PortfolioService::class)->createForUser($user, $template, 'Required Test');

        $response = $this->actingAs($user)
            ->post(route('portfolios.publish', $portfolio));

        $response->assertSessionHasErrors('publish');
        $this->assertEquals('draft', $portfolio->fresh()->status);
    }

    public function test_publishing_succeeds_when_required_fields_present()
    {
        $this->seed();
        $user = User::factory()->create();
        $template = Template::published()->with('sections.fields.fieldDefinition')->first();

        $portfolio = app(PortfolioService::class)->createForUser($user, $template, 'Publish Success Test');

        // Fill required fields
        foreach ($template->sections as $tSec) {
            $pSec = $portfolio->sections()->where('section_key', $tSec->section_key)->first();
            if ($pSec) {
                foreach ($tSec->fields as $tField) {
                    if ($tField->is_required) {
                        PortfolioFieldValue::create([
                            'portfolio_section_id' => $pSec->id,
                            'field_definition_id' => $tField->field_definition_id,
                            'value' => 'Test Value',
                        ]);
                    }
                }
            }
        }

        $response = $this->actingAs($user)
            ->post(route('portfolios.publish', $portfolio));

        $response->assertSessionHasNoErrors();
        $this->assertEquals('published', $portfolio->fresh()->status);
    }

    public function test_user_can_upload_image_to_portfolio_storage()
    {
        $this->seed();
        $user = User::factory()->create();
        $template = Template::published()->first();
        $portfolio = app(PortfolioService::class)->createForUser($user, $template, 'Upload Test');
        Storage::fake('r2');
        $r2Root = Storage::disk('r2')->path('');
        config(['filesystems.disks.r2' => [
            'driver' => 'local',
            'root' => $r2Root,
            'url' => 'https://images.example.test',
            'throw' => true,
            'report' => false,
        ]]);
        Storage::forgetDisk('r2');

        $file = $this->fakeImage();

        $response = $this->actingAs($user)
            ->postJson(route('portfolios.upload', $portfolio), [
                'file' => $file,
                'type' => 'image',
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['url', 'path', 'success']);

        $path = $response->json('path');
        $this->assertSame('https://images.example.test/'.$path, $response->json('url'));
        $this->assertStringStartsWith(
            "users/{$user->id}/templates/{$template->id}/portfolios/{$portfolio->id}/",
            $path
        );
        Storage::disk('r2')->assertExists($path);
    }

    public function test_user_can_upload_document_to_portfolio_storage()
    {
        $this->seed();
        $user = User::factory()->create();
        $template = Template::published()->first();
        $portfolio = app(PortfolioService::class)->createForUser($user, $template, 'Document Upload Test');
        Storage::fake('r2');
        $r2Root = Storage::disk('r2')->path('');
        config(['filesystems.disks.r2' => [
            'driver' => 'local',
            'root' => $r2Root,
            'url' => 'https://images.example.test',
            'throw' => true,
            'report' => false,
        ]]);
        Storage::forgetDisk('r2');

        $response = $this->actingAs($user)
            ->postJson(route('portfolios.upload', $portfolio), [
                'file' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
                'type' => 'file',
            ]);

        $response->assertOk();
        $path = $response->json('path');
        $this->assertStringStartsWith(
            "users/{$user->id}/templates/{$template->id}/portfolios/{$portfolio->id}/",
            $path
        );
        $this->assertSame('https://images.example.test/'.$path, $response->json('url'));
        Storage::disk('r2')->assertExists($path);
    }

    public function test_portfolio_download_includes_images_from_r2()
    {
        $this->seed();
        $user = User::factory()->create();
        $template = Template::where('slug', 'dark-orange-designer')->firstOrFail();
        $portfolio = app(PortfolioService::class)->createForUser($user, $template, 'R2 Export Test');
        Storage::fake('r2');
        $r2Root = Storage::disk('r2')->path('');
        config(['filesystems.disks.r2' => [
            'driver' => 'local',
            'root' => $r2Root,
            'url' => 'https://images.example.test',
            'throw' => true,
            'report' => false,
        ]]);
        Storage::forgetDisk('r2');

        $assetPath = "users/{$user->id}/templates/{$template->id}/portfolios/{$portfolio->id}/r2-profile.png";
        Storage::disk('r2')->put($assetPath, 'r2-profile-image');
        $header = $portfolio->sections()->where('section_key', 'header')->firstOrFail();
        PortfolioFieldValue::updateOrCreate(
            [
                'portfolio_section_id' => $header->id,
                'field_definition_id' => FieldDefinition::where('field_key', 'hero_image')->firstOrFail()->id,
            ],
            ['value' => "https://images.example.test/{$assetPath}"]
        );

        $response = $this->actingAs($user)->get(route('portfolios.download', $portfolio));
        $response->assertDownload("portfolio-{$portfolio->slug}.zip");
        $archivePath = tempnam(sys_get_temp_dir(), 'portfolio_export_test_');
        $this->assertNotFalse($archivePath);
        file_put_contents($archivePath, $response->streamedContent());

        $archive = new ZipArchive;
        $this->assertTrue($archive->open($archivePath));
        try {
            $exportedImagePath = 'assets/'.substr(hash('sha256', $assetPath), 0, 12).'-r2-profile.png';
            $this->assertStringContainsString($exportedImagePath, $archive->getFromName('index.html'));
            $this->assertSame('r2-profile-image', $archive->getFromName($exportedImagePath));
        } finally {
            $archive->close();
            unlink($archivePath);
        }
    }

    public function test_portfolio_download_contains_template_files_and_local_assets()
    {
        $this->seed();
        $user = User::factory()->create();
        $template = Template::where('slug', 'dark-orange-designer')->firstOrFail();
        $portfolio = app(PortfolioService::class)->createForUser($user, $template, 'Export Test');
        $portfolio->update([
            'google_form_url' => 'https://docs.google.com/forms/d/e/form-id/viewform?usp=pp_url',
            'google_form_name_entry' => '123',
            'google_form_email_entry' => '456',
            'google_form_message_entry' => '789',
        ]);
        Storage::fake('public');

        $assetPath = "users/{$user->id}/templates/{$template->id}/portfolios/{$portfolio->id}/profile.png";
        Storage::disk('public')->put($assetPath, 'profile-image');

        $header = $portfolio->sections()->where('section_key', 'header')->firstOrFail();
        $projects = $portfolio->sections()->where('section_key', 'projects')->firstOrFail();
        PortfolioFieldValue::updateOrCreate(
            [
                'portfolio_section_id' => $header->id,
                'field_definition_id' => FieldDefinition::where('field_key', 'hero_image')->firstOrFail()->id,
            ],
            ['value' => '/storage/'.$assetPath]
        );
        PortfolioFieldValue::updateOrCreate(
            [
                'portfolio_section_id' => $projects->id,
                'field_definition_id' => FieldDefinition::where('field_key', 'projects_list')->firstOrFail()->id,
            ],
            ['value' => [[
                'title' => 'Exported project',
                'category' => 'Design',
                'image' => '/storage/'.$assetPath,
            ]]]
        );
        $this->assertTrue($header->fresh()->is_enabled);
        $this->assertSame(
            '/storage/'.$assetPath,
            $header->fieldValues()->whereHas('fieldDefinition', fn ($query) => $query->where('field_key', 'hero_image'))->firstOrFail()->value
        );
        $this->assertSame(
            'Exported project',
            $projects->fieldValues()->whereHas('fieldDefinition', fn ($query) => $query->where('field_key', 'projects_list'))->firstOrFail()->value[0]['title']
        );

        $response = $this->actingAs($user)->get(route('portfolios.download', $portfolio));
        $response->assertDownload("portfolio-{$portfolio->slug}.zip");

        $archivePath = tempnam(sys_get_temp_dir(), 'portfolio_export_test_');
        $this->assertNotFalse($archivePath);
        file_put_contents($archivePath, $response->streamedContent());

        $archive = new ZipArchive;
        $this->assertTrue($archive->open($archivePath));
        try {
            $html = $archive->getFromName('index.html');
            $stylesheet = $archive->getFromName('styles.css');
            $script = $archive->getFromName('script.js');
            $svgLogo = $archive->getFromName('Logo.svg');
            $pngLogo = $archive->getFromName('Logo.png');
            $this->assertIsString($html);
            $this->assertIsString($stylesheet);
            $this->assertIsString($script);
            $this->assertIsString($svgLogo);
            $this->assertIsString($pngLogo);
            $this->assertSame(file_get_contents(storage_path('app/public/Logo.svg')), $svgLogo);
            $this->assertSame(file_get_contents(public_path('Logo.png')), $pngLogo);
            $this->assertStringContainsString('data-template="dark-orange-designer"', $html);
            $this->assertStringContainsString('<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>', $html);
            $this->assertStringContainsString('id="projects" data-section', $html);
            $this->assertStringContainsString('<nav class="nav" aria-label="Main navigation">', $html);
            $this->assertStringContainsString('id="mobile-navigation"', $html);
            $this->assertStringContainsString('aria-controls="mobile-navigation"', $html);
            $this->assertStringContainsString('<form action="https://docs.google.com/forms/d/e/form-id/formResponse"', $html);
            $this->assertStringContainsString('name="entry.123"', $html);
            $this->assertStringContainsString('name="entry.456"', $html);
            $this->assertStringContainsString('name="entry.789"', $html);
            $this->assertStringContainsString('Exported project', $html);
            $this->assertStringContainsString('assets/'.substr(hash('sha256', $assetPath), 0, 12).'-profile.png', $html);
            $this->assertStringContainsString('.portrait-wrap', $stylesheet);
            $this->assertStringContainsString('.work-row', $stylesheet);
            $this->assertStringContainsString('.work-preview', $stylesheet);
            $this->assertStringNotContainsString('.theme-dark-orange', $stylesheet);
            $this->assertSame(
                'profile-image',
                $archive->getFromName('assets/'.substr(hash('sha256', $assetPath), 0, 12).'-profile.png')
            );
            $this->assertStringContainsString('#ff5a1f', $stylesheet);
            $this->assertStringContainsString('menu-toggle', $script);
            $this->assertStringContainsString('nav?.classList.add("nav-in")', $script);
            $this->assertStringContainsString('data-project-filter', $script);
            $this->assertStringContainsString('pointermove', $script);
        } finally {
            $archive->close();
            unlink($archivePath);
        }
    }

    public function test_ivory_editorial_download_uses_its_own_stylesheet()
    {
        $this->seed();
        $user = User::factory()->create();
        $template = Template::where('slug', 'ivory-editorial')->firstOrFail();
        $portfolio = app(PortfolioService::class)->createForUser($user, $template, 'Editorial Export');
        $portfolio->update([
            'google_form_url' => 'https://docs.google.com/forms/d/e/ivory-form/viewform?usp=pp_url',
            'google_form_name_entry' => '135',
            'google_form_email_entry' => '246',
            'google_form_message_entry' => '357',
        ]);
        Storage::fake('public');

        $assetPath = "users/{$user->id}/templates/{$template->id}/portfolios/{$portfolio->id}/editorial-portrait.png";
        Storage::disk('public')->put($assetPath, 'editorial-portrait-image');
        $header = $portfolio->sections()->where('section_key', 'header')->firstOrFail();
        $about = $portfolio->sections()->where('section_key', 'about')->firstOrFail();
        $skills = $portfolio->sections()->where('section_key', 'skills')->firstOrFail();
        $projects = $portfolio->sections()->where('section_key', 'projects')->firstOrFail();
        $contact = $portfolio->sections()->where('section_key', 'contact')->firstOrFail();
        foreach ([
            [$header, 'hero_name', 'Editorial Designer'],
            [$header, 'hero_image', '/storage/'.$assetPath],
            [$header, 'hero_role', 'Designer and maker'],
            [$about, 'about_title', 'A little about me'],
            [$about, 'about_description', 'I make clear and thoughtful digital experiences.'],
            [$about, 'about_highlights', [['title' => 'Thoughtful design'], ['title' => 'Accessible systems']]],
            [$skills, 'skills_title', 'Selected expertise'],
            [$skills, 'skills_list', [['name' => 'Typography', 'level' => '4'], ['name' => 'Research', 'level' => 'Advanced']]],
            [$projects, 'projects_title', 'Selected work'],
            [$projects, 'projects_list', [[
                'title' => 'Editorial project',
                'category' => 'Branding',
                'description' => 'A considered identity system.',
                'image' => '/storage/'.$assetPath,
                'url' => 'https://example.com/project',
                'repo_url' => 'https://example.com/source',
            ]]],
            [$contact, 'contact_title', 'Let us work together'],
            [$contact, 'contact_email', 'hello@example.com'],
        ] as [$section, $fieldKey, $value]) {
            PortfolioFieldValue::updateOrCreate(
                [
                    'portfolio_section_id' => $section->id,
                    'field_definition_id' => FieldDefinition::where('field_key', $fieldKey)->firstOrFail()->id,
                ],
                ['value' => $value]
            );
        }

        $response = $this->actingAs($user)->get(route('portfolios.download', $portfolio));
        $response->assertDownload("portfolio-{$portfolio->slug}.zip");

        $archivePath = tempnam(sys_get_temp_dir(), 'portfolio_export_test_');
        $this->assertNotFalse($archivePath);
        file_put_contents($archivePath, $response->streamedContent());

        $archive = new ZipArchive;
        $this->assertTrue($archive->open($archivePath));
        try {
            $html = $archive->getFromName('index.html');
            $stylesheet = $archive->getFromName('styles.css');
            $script = $archive->getFromName('script.js');
            $this->assertIsString($html);
            $this->assertIsString($stylesheet);
            $this->assertIsString($script);
            $this->assertStringContainsString('data-template="ivory-editorial"', $html);
            $this->assertStringContainsString('<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>', $html);
            $this->assertStringContainsString('Editorial Designer', $html);
            $this->assertStringContainsString('A little about me', $html);
            $this->assertStringContainsString('Thoughtful design', $html);
            $this->assertStringContainsString('Typography', $html);
            $this->assertStringContainsString('Editorial project', $html);
            $this->assertStringContainsString('https://docs.google.com/forms/d/e/ivory-form/formResponse', $html);
            $this->assertStringContainsString('name="entry.135"', $html);
            $this->assertStringContainsString('name="entry.246"', $html);
            $this->assertStringContainsString('name="entry.357"', $html);
            $this->assertStringContainsString('assets/'.substr(hash('sha256', $assetPath), 0, 12).'-editorial-portrait.png', $html);
            $this->assertStringContainsString('.ie-hero-name', $stylesheet);
            $this->assertStringContainsString('.ie-ticks', $stylesheet);
            $this->assertStringContainsString('.ie-form', $stylesheet);
            $this->assertStringContainsString('IntersectionObserver', $script);
            $this->assertStringContainsString('data-google-form', $script);
            $this->assertSame(
                'editorial-portrait-image',
                $archive->getFromName('assets/'.substr(hash('sha256', $assetPath), 0, 12).'-editorial-portrait.png')
            );
        } finally {
            $archive->close();
            unlink($archivePath);
        }
    }

    public function test_bento_grid_download_uses_its_own_stylesheet()
    {
        $this->seed();
        $user = User::factory()->create();
        $template = Template::where('slug', 'bento-grid')->firstOrFail();
        $portfolio = app(PortfolioService::class)->createForUser($user, $template, 'Bento Export');
        $portfolio->update([
            'google_form_url' => 'https://docs.google.com/forms/d/form-id/viewform',
            'google_form_name_entry' => '112',
            'google_form_email_entry' => '224',
            'google_form_message_entry' => '336',
        ]);
        Storage::fake('public');

        $assetPath = "users/{$user->id}/templates/{$template->id}/portfolios/{$portfolio->id}/bento-project.png";
        Storage::disk('public')->put($assetPath, 'bento-project-image');

        $header = $portfolio->sections()->where('section_key', 'header')->firstOrFail();
        $projects = $portfolio->sections()->where('section_key', 'projects')->firstOrFail();
        PortfolioFieldValue::updateOrCreate(
            [
                'portfolio_section_id' => $header->id,
                'field_definition_id' => FieldDefinition::where('field_key', 'hero_name')->firstOrFail()->id,
            ],
            ['value' => 'Bento Designer']
        );
        PortfolioFieldValue::updateOrCreate(
            [
                'portfolio_section_id' => $header->id,
                'field_definition_id' => FieldDefinition::where('field_key', 'hero_image')->firstOrFail()->id,
            ],
            ['value' => '/storage/'.$assetPath]
        );
        PortfolioFieldValue::updateOrCreate(
            [
                'portfolio_section_id' => $projects->id,
                'field_definition_id' => FieldDefinition::where('field_key', 'projects_list')->firstOrFail()->id,
            ],
            ['value' => [[
                'title' => 'Bento project tile',
                'category' => 'Product design',
                'image' => '/storage/'.$assetPath,
                'url' => 'https://example.com/project',
            ]]]
        );

        $response = $this->actingAs($user)->get(route('portfolios.download', $portfolio));
        $response->assertDownload("portfolio-{$portfolio->slug}.zip");

        $archivePath = tempnam(sys_get_temp_dir(), 'portfolio_export_test_');
        $this->assertNotFalse($archivePath);
        file_put_contents($archivePath, $response->streamedContent());

        $archive = new ZipArchive;
        $this->assertTrue($archive->open($archivePath));
        try {
            $html = $archive->getFromName('index.html');
            $stylesheet = $archive->getFromName('styles.css');
            $script = $archive->getFromName('script.js');
            $this->assertIsString($html);
            $this->assertIsString($stylesheet);
            $this->assertIsString($script);
            $this->assertStringContainsString('data-template="bento-grid"', $html);
            $this->assertStringContainsString('<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>', $html);
            $this->assertStringContainsString('<form action="https://docs.google.com/forms/d/form-id/formResponse" method="POST" target="bg-gform-frame"', $html);
            $this->assertStringContainsString('name="entry.112"', $html);
            $this->assertStringContainsString('name="entry.224"', $html);
            $this->assertStringContainsString('name="entry.336"', $html);
            $this->assertStringContainsString('Send a message', $html);
            $this->assertStringContainsString('name="bg-gform-frame"', $html);
            $this->assertStringContainsString('class="rail relative min-h-screen overflow-x-clip font-body"', $html);
            $this->assertStringContainsString('class="project group mb-14 lg:sticky lg:mb-[34vh]', $html);
            $this->assertStringContainsString('Bento Designer', $html);
            $this->assertStringContainsString('Bento project tile', $html);
            $this->assertStringContainsString('assets/'.substr(hash('sha256', $assetPath), 0, 12).'-bento-project.png', $html);
            $this->assertStringContainsString('.rail-panel', $stylesheet);
            $this->assertStringContainsString('.journey-panel', $stylesheet);
            $this->assertStringContainsString('@media(min-width:768px)', $stylesheet);
            $this->assertStringNotContainsString('.theme-bento', $stylesheet);
            $this->assertStringContainsString('IntersectionObserver', $script);
            $this->assertStringContainsString('scaleX', $script);
            $this->assertStringContainsString('Your message was submitted.', $script);
            $this->assertSame(
                'bento-project-image',
                $archive->getFromName('assets/'.substr(hash('sha256', $assetPath), 0, 12).'-bento-project.png')
            );
        } finally {
            $archive->close();
            unlink($archivePath);
        }
    }

    public function test_cobalt_brutalist_download_uses_its_own_design_and_form_integration()
    {
        $this->seed();
        $user = User::factory()->create();
        $template = Template::where('slug', 'cobalt-brutalist')->firstOrFail();
        $portfolio = app(PortfolioService::class)->createForUser($user, $template, 'Cobalt Export');
        $portfolio->update([
            'google_form_url' => 'https://docs.google.com/forms/d/cobalt-form/viewform',
            'google_form_name_entry' => '101',
            'google_form_email_entry' => '202',
            'google_form_message_entry' => '303',
        ]);

        $header = $portfolio->sections()->where('section_key', 'header')->firstOrFail();
        $about = $portfolio->sections()->where('section_key', 'about')->firstOrFail();
        $skills = $portfolio->sections()->where('section_key', 'skills')->firstOrFail();
        $projects = $portfolio->sections()->where('section_key', 'projects')->firstOrFail();
        $contact = $portfolio->sections()->where('section_key', 'contact')->firstOrFail();
        foreach ([
            [$header, 'hero_name', 'Cobalt Designer'],
            [$header, 'hero_role', 'Product designer'],
            [$about, 'about_title', 'About me'],
            [$skills, 'skills_title', 'Skills'],
            [$skills, 'skills_list', [
                ['name' => 'Design', 'level' => 4],
                ['name' => 'Prototyping', 'level' => '80%'],
            ]],
            [$projects, 'projects_title', 'Selected work'],
            [$projects, 'projects_list', [[
                'title' => 'Cobalt project',
                'description' => 'A strong graphic project card.',
                'category' => 'Design',
                'url' => 'https://example.com/cobalt',
            ]]],
            [$contact, 'contact_title', 'Let us collaborate'],
            [$contact, 'contact_email', 'hello@example.com'],
        ] as [$section, $fieldKey, $value]) {
            PortfolioFieldValue::updateOrCreate(
                [
                    'portfolio_section_id' => $section->id,
                    'field_definition_id' => FieldDefinition::where('field_key', $fieldKey)->firstOrFail()->id,
                ],
                ['value' => $value]
            );
        }

        $response = $this->actingAs($user)->get(route('portfolios.download', $portfolio));
        $response->assertDownload("portfolio-{$portfolio->slug}.zip");

        $archivePath = tempnam(sys_get_temp_dir(), 'portfolio_export_test_');
        $this->assertNotFalse($archivePath);
        file_put_contents($archivePath, $response->streamedContent());

        $archive = new ZipArchive;
        $this->assertTrue($archive->open($archivePath));
        try {
            $html = $archive->getFromName('index.html');
            $stylesheet = $archive->getFromName('styles.css');
            $script = $archive->getFromName('script.js');
            $this->assertIsString($html);
            $this->assertIsString($stylesheet);
            $this->assertIsString($script);
            $this->assertStringContainsString('data-template="cobalt-brutalist"', $html);
            $this->assertStringContainsString('<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>', $html);
            $this->assertStringContainsString('class="nb-hero"', $html);
            $this->assertStringContainsString('Cobalt Designer', $html);
            $this->assertStringContainsString('Cobalt project', $html);
            $this->assertStringContainsString('nb-container nb-two-col nb-layout-about', $html);
            $this->assertStringContainsString('nb-container nb-two-col nb-layout-skills', $html);
            $this->assertStringContainsString('About me', $html);
            $this->assertStringContainsString('Prototyping', $html);
            $this->assertStringContainsString('class="nb-contact"', $html);
            $this->assertStringContainsString('https://docs.google.com/forms/d/cobalt-form/formResponse', $html);
            $this->assertStringContainsString('name="entry.101"', $html);
            $this->assertStringContainsString('name="entry.202"', $html);
            $this->assertStringContainsString('name="entry.303"', $html);
            $this->assertStringContainsString('.nb-portrait', $stylesheet);
            $this->assertStringContainsString('.nb-timeline', $stylesheet);
            $this->assertStringContainsString('grid-template-columns:repeat(12,minmax(0,1fr))', $stylesheet);
            $this->assertStringContainsString('.nb-layout-journey>.nb-sticky-copy,.nb-layout-skills>.nb-sticky-copy{grid-column:span 4}', $stylesheet);
            $this->assertStringContainsString('.nb-skills{grid-column:span 8', $stylesheet);
            $this->assertStringNotContainsString('.theme-cobalt', $stylesheet);
            $this->assertStringContainsString('IntersectionObserver', $script);
            $this->assertStringContainsString('nb-gform-frame', $script);
        } finally {
            $archive->close();
            unlink($archivePath);
        }
    }

    public function test_emerald_minimalist_download_uses_its_own_design_and_form_integration()
    {
        $this->seed();
        $user = User::factory()->create();
        $template = Template::where('slug', 'emerald-minimalist')->firstOrFail();
        $portfolio = app(PortfolioService::class)->createForUser($user, $template, 'Emerald Export');
        $portfolio->update([
            'google_form_url' => 'https://docs.google.com/forms/d/e/emerald-form/viewform?usp=pp_url',
            'google_form_name_entry' => '123',
            'google_form_email_entry' => '456',
            'google_form_message_entry' => '789',
        ]);
        Storage::fake('public');

        $assetPath = "users/{$user->id}/templates/{$template->id}/portfolios/{$portfolio->id}/emerald-profile.png";
        Storage::disk('public')->put($assetPath, 'emerald-profile-image');
        $header = $portfolio->sections()->where('section_key', 'header')->firstOrFail();
        $skills = $portfolio->sections()->where('section_key', 'skills')->firstOrFail();
        $projects = $portfolio->sections()->where('section_key', 'projects')->firstOrFail();
        $contact = $portfolio->sections()->where('section_key', 'contact')->firstOrFail();
        PortfolioFieldValue::updateOrCreate(
            [
                'portfolio_section_id' => $header->id,
                'field_definition_id' => FieldDefinition::where('field_key', 'hero_name')->firstOrFail()->id,
            ],
            ['value' => 'Emerald Designer']
        );
        PortfolioFieldValue::updateOrCreate(
            [
                'portfolio_section_id' => $header->id,
                'field_definition_id' => FieldDefinition::where('field_key', 'hero_image')->firstOrFail()->id,
            ],
            ['value' => '/storage/'.$assetPath]
        );
        PortfolioFieldValue::updateOrCreate(
            [
                'portfolio_section_id' => $skills->id,
                'field_definition_id' => FieldDefinition::where('field_key', 'skills_list')->firstOrFail()->id,
            ],
            ['value' => [
                ['label' => 'Vue', 'percent' => 85],
                ['label' => 'Laravel', 'percent' => 90],
            ]]
        );
        PortfolioFieldValue::updateOrCreate(
            [
                'portfolio_section_id' => $projects->id,
                'field_definition_id' => FieldDefinition::where('field_key', 'projects_list')->firstOrFail()->id,
            ],
            ['value' => [
                ['title' => 'Emerald project', 'category' => 'Design', 'image' => '/storage/'.$assetPath, 'url' => 'https://example.com/project'],
                ['title' => 'Emerald case study', 'category' => 'Development'],
            ]]
        );
        foreach ([
            ['contact_email', 'hello@example.com'],
            ['contact_phone', '+1 555 0100'],
            ['contact_location', 'Seattle'],
        ] as [$fieldKey, $value]) {
            PortfolioFieldValue::updateOrCreate(
                [
                    'portfolio_section_id' => $contact->id,
                    'field_definition_id' => FieldDefinition::where('field_key', $fieldKey)->firstOrFail()->id,
                ],
                ['value' => $value]
            );
        }

        $response = $this->actingAs($user)->get(route('portfolios.download', $portfolio));
        $response->assertDownload("portfolio-{$portfolio->slug}.zip");

        $archivePath = tempnam(sys_get_temp_dir(), 'portfolio_export_test_');
        $this->assertNotFalse($archivePath);
        file_put_contents($archivePath, $response->streamedContent());

        $archive = new ZipArchive;
        $this->assertTrue($archive->open($archivePath));
        try {
            $html = $archive->getFromName('index.html');
            $stylesheet = $archive->getFromName('styles.css');
            $script = $archive->getFromName('script.js');
            $this->assertIsString($html);
            $this->assertIsString($stylesheet);
            $this->assertIsString($script);
            $this->assertStringContainsString('data-template="emerald-minimalist"', $html);
            $this->assertStringContainsString('<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>', $html);
            $this->assertStringContainsString('<nav class="em-navbar" aria-label="Main navigation">', $html);
            $this->assertStringContainsString('id="em-mobile-nav"', $html);
            $this->assertStringContainsString('<form action="https://docs.google.com/forms/d/e/emerald-form/formResponse"', $html);
            $this->assertStringContainsString('name="entry.123"', $html);
            $this->assertStringContainsString('name="entry.456"', $html);
            $this->assertStringContainsString('name="entry.789"', $html);
            $this->assertStringContainsString('Emerald Designer', $html);
            $this->assertStringContainsString('Emerald project', $html);
            $emailPosition = strpos($html, 'hello@example.com');
            $phonePosition = strpos($html, 'Phone: +1 555 0100');
            $locationPosition = strpos($html, 'Location: Seattle');
            $formPosition = strpos($html, '<form action="https://docs.google.com/forms/d/e/emerald-form/formResponse"');
            $this->assertIsInt($emailPosition);
            $this->assertIsInt($phonePosition);
            $this->assertIsInt($locationPosition);
            $this->assertIsInt($formPosition);
            $this->assertTrue($emailPosition < $phonePosition);
            $this->assertTrue($phonePosition < $locationPosition);
            $this->assertTrue($locationPosition < $formPosition);
            $this->assertStringContainsString('data-project-filter="All"', $html);
            $this->assertStringContainsString('assets/'.substr(hash('sha256', $assetPath), 0, 12).'-emerald-profile.png', $html);
            $this->assertStringContainsString('.em-portrait-wrap', $stylesheet);
            $this->assertStringContainsString('.em-skills-grid', $stylesheet);
            $this->assertStringContainsString('.em-project-card', $stylesheet);
            $this->assertStringContainsString('IntersectionObserver', $script);
            $this->assertStringContainsString('em-progress-bar', $script);
            $this->assertStringContainsString('data-project-filter', $script);
            $this->assertStringContainsString('Your message was submitted.', $script);
            $this->assertSame(
                'emerald-profile-image',
                $archive->getFromName('assets/'.substr(hash('sha256', $assetPath), 0, 12).'-emerald-profile.png')
            );
        } finally {
            $archive->close();
            unlink($archivePath);
        }
    }

    public function test_replacing_an_uploaded_file_removes_the_previous_file_after_saving()
    {
        $this->seed();
        $user = User::factory()->create();
        $template = Template::published()->first();
        $portfolio = app(PortfolioService::class)->createForUser($user, $template, 'Replace Upload Test');
        Storage::fake('public');
        Storage::fake('r2');
        $r2Root = Storage::disk('r2')->path('');
        config(['filesystems.disks.r2' => [
            'driver' => 'local',
            'root' => $r2Root,
            'url' => 'https://images.example.test',
            'throw' => true,
            'report' => false,
        ]]);
        Storage::forgetDisk('r2');

        $oldPath = "users/{$user->id}/templates/{$template->id}/portfolios/{$portfolio->id}/old-resume.pdf";
        Storage::disk('public')->put($oldPath, 'old resume');

        $aboutSection = $portfolio->sections()->where('section_key', 'about')->firstOrFail();
        $fileField = FieldDefinition::where('field_key', 'about_cv_file')->firstOrFail();
        PortfolioFieldValue::updateOrCreate(
            [
                'portfolio_section_id' => $aboutSection->id,
                'field_definition_id' => $fileField->id,
            ],
            ['value' => '/storage/'.$oldPath]
        );

        $uploadResponse = $this->actingAs($user)->postJson(route('portfolios.upload', $portfolio), [
            'file' => UploadedFile::fake()->create('new-resume.pdf', 100, 'application/pdf'),
            'type' => 'file',
        ]);
        $uploadResponse->assertOk();
        Storage::disk('public')->assertExists($oldPath);

        $sections = $portfolio->sections()->get()->map(fn ($section) => [
            'id' => $section->id,
            'is_enabled' => $section->is_enabled,
            'field_values' => [],
        ])->all();
        $aboutIndex = array_search($aboutSection->id, array_column($sections, 'id'));
        $sections[$aboutIndex]['field_values'][] = [
            'field_definition_id' => $fileField->id,
            'value' => $uploadResponse->json('url'),
        ];

        $this->patch(route('portfolios.update', $portfolio), ['sections' => $sections])
            ->assertSessionHasNoErrors();

        $savedValue = PortfolioFieldValue::where('portfolio_section_id', $aboutSection->id)
            ->where('field_definition_id', $fileField->id)
            ->firstOrFail()
            ->value;
        $this->assertSame($uploadResponse->json('url'), $savedValue);
        $this->assertSame('https://images.example.test/'.$uploadResponse->json('path'), $savedValue);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('r2')->assertExists($uploadResponse->json('path'));
    }

    public function test_replacing_an_uploaded_image_removes_the_previous_r2_object_after_saving()
    {
        $this->seed();
        $user = User::factory()->create();
        $template = Template::published()->first();
        $portfolio = app(PortfolioService::class)->createForUser($user, $template, 'Replace Image Test');
        Storage::fake('r2');
        $r2Root = Storage::disk('r2')->path('');
        config(['filesystems.disks.r2' => [
            'driver' => 'local',
            'root' => $r2Root,
            'url' => 'https://images.example.test',
            'throw' => true,
            'report' => false,
        ]]);
        Storage::forgetDisk('r2');

        $directory = "users/{$user->id}/templates/{$template->id}/portfolios/{$portfolio->id}";
        $oldPath = "{$directory}/old-image.png";
        Storage::disk('r2')->put($oldPath, 'old image');

        $aboutSection = $portfolio->sections()->where('section_key', 'about')->firstOrFail();
        $imageField = FieldDefinition::where('field_key', 'about_image')->first();
        if (! $imageField) {
            $imageField = FieldDefinition::where('data_type', 'image')->firstOrFail();
        }
        PortfolioFieldValue::updateOrCreate(
            [
                'portfolio_section_id' => $aboutSection->id,
                'field_definition_id' => $imageField->id,
            ],
            ['value' => "https://images.example.test/{$oldPath}"]
        );

        $uploadResponse = $this->actingAs($user)->postJson(route('portfolios.upload', $portfolio), [
            'file' => $this->fakeImage(),
            'type' => 'image',
        ]);
        $uploadResponse->assertOk();
        Storage::disk('r2')->assertExists($oldPath);

        $sections = $portfolio->sections()->get()->map(fn ($section) => [
            'id' => $section->id,
            'is_enabled' => $section->is_enabled,
            'field_values' => [],
        ])->all();
        $aboutIndex = array_search($aboutSection->id, array_column($sections, 'id'));
        $sections[$aboutIndex]['field_values'][] = [
            'field_definition_id' => $imageField->id,
            'value' => $uploadResponse->json('url'),
        ];

        $this->patch(route('portfolios.update', $portfolio), ['sections' => $sections])
            ->assertSessionHasNoErrors();

        Storage::disk('r2')->assertMissing($oldPath);
        Storage::disk('r2')->assertExists($uploadResponse->json('path'));
    }

    public function test_user_cannot_upload_to_another_users_portfolio()
    {
        $this->seed();
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $template = Template::published()->first();
        $portfolio = app(PortfolioService::class)->createForUser($owner, $template, 'Private Portfolio');

        $this->actingAs($otherUser)
            ->postJson(route('portfolios.upload', $portfolio), [
                'file' => $this->fakeImage(),
                'type' => 'image',
            ])
            ->assertForbidden();
    }

    public function test_image_upload_rejects_non_image_files()
    {
        $this->seed();
        $user = User::factory()->create();
        $template = Template::published()->first();
        $portfolio = app(PortfolioService::class)->createForUser($user, $template, 'Invalid Upload Test');

        $this->actingAs($user)
            ->postJson(route('portfolios.upload', $portfolio), [
                'file' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
                'type' => 'image',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    private function fakeImage(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'profile.gif',
            base64_decode('R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=')
        );
    }
}
