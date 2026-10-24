<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\TemplateCategory;
use App\Models\User;
use App\Services\TemplateDemoData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_users_cannot_access_the_admin_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_administrators_can_view_the_admin_panel(): void
    {
        $admin = User::factory()->create();
        $admin->role = 'admin';
        $admin->save();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Index')
                ->has('stats.users')
                ->has('users.data', 1));
    }

    public function test_regular_users_cannot_download_a_template_demo_export(): void
    {
        $user = User::factory()->create();
        $template = Template::create([
            'name' => 'Emerald Minimalist',
            'slug' => 'emerald-minimalist',
            'is_published' => true,
        ]);

        $this->actingAs($user)
            ->get(route('admin.templates.download-demo', $template))
            ->assertForbidden();
    }

    public function test_administrators_can_download_a_template_with_demo_content_and_assets_without_creating_a_portfolio(): void
    {
        Http::fake([
            'https://'.TemplateDemoData::ASSET_HOST.'/Demo/*' => Http::response('demo-asset', 200),
        ]);

        $admin = User::factory()->create();
        $admin->role = 'admin';
        $admin->save();
        $template = Template::create([
            'name' => 'Emerald Minimalist',
            'slug' => 'emerald-minimalist',
            'is_published' => true,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.templates.download-demo', $template));

        $response->assertDownload('emerald-minimalist-demo.zip');
        $this->assertDatabaseCount('portfolios', 0);
        Http::assertSentCount(9);

        $archivePath = tempnam(sys_get_temp_dir(), 'template_demo_export_test_');
        $this->assertNotFalse($archivePath);
        file_put_contents($archivePath, $response->streamedContent());

        $archive = new \ZipArchive;
        $this->assertTrue($archive->open($archivePath));
        $index = $archive->getFromName('index.html');
        $this->assertIsString($index);
        $this->assertStringContainsString('Akash Hossen', $index);
        $this->assertSame(9, count(array_filter(
            array_map(fn (int $index): string => $archive->getNameIndex($index), range(0, $archive->numFiles - 1)),
            fn (string $path): bool => str_starts_with($path, 'assets/')
        )));
        $archive->close();
        unlink($archivePath);
    }

    public function test_administrators_can_create_templates_with_thumbnail_images(): void
    {
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
        $admin = User::factory()->create();
        $admin->role = 'admin';
        $admin->save();
        $category = TemplateCategory::create(['name' => 'Designer', 'slug' => 'designer']);

        $this->actingAs($admin)
            ->post(route('admin.templates.store'), [
                'name' => 'Editorial Portfolio',
                'slug' => 'Editorial Portfolio',
                'category_id' => $category->id,
                'description' => 'A clean portfolio layout.',
                'preview_url' => '',
                'is_published' => true,
                'thumbnail' => UploadedFile::fake()->createWithContent(
                    'template.png',
                    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADUlEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC')
                ),
            ])
            ->assertRedirect();

        $template = Template::firstOrFail();
        $this->assertSame('editorial-portfolio', $template->slug);
        $this->assertTrue($template->is_published);
        $this->assertStringStartsWith('https://images.example.test/templates/', $template->thumbnail);
        $oldThumbnailPath = substr($template->thumbnail, strlen('https://images.example.test/'));
        Storage::disk('r2')->assertExists($oldThumbnailPath);

        $this->actingAs($admin)
            ->patch(route('admin.templates.update', $template), [
                'name' => 'Editorial Portfolio Updated',
                'slug' => 'editorial-portfolio',
                'category_id' => $category->id,
                'description' => 'An updated portfolio layout.',
                'preview_url' => '',
                'is_published' => true,
                'thumbnail' => UploadedFile::fake()->createWithContent(
                    'template-updated.png',
                    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADUlEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC')
                ),
            ])
            ->assertRedirect();

        Storage::disk('r2')->assertMissing($oldThumbnailPath);
        $newThumbnailPath = substr(
            $template->fresh()->thumbnail,
            strlen('https://images.example.test/')
        );
        Storage::disk('r2')->assertExists($newThumbnailPath);
    }

    public function test_administrators_cannot_remove_their_own_admin_access(): void
    {
        $admin = User::factory()->create();
        $admin->role = 'admin';
        $admin->save();

        $this->actingAs($admin)
            ->patch(route('admin.users.role', $admin), ['role' => 'user'])
            ->assertRedirect();

        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_administrators_can_grant_admin_access_to_another_user(): void
    {
        $admin = User::factory()->create();
        $admin->role = 'admin';
        $admin->save();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.role', $user), ['role' => 'admin'])
            ->assertRedirect();

        $this->assertSame('admin', $user->fresh()->role);
    }

    public function test_admin_promotion_command_promotes_an_existing_user(): void
    {
        $user = User::factory()->create();

        $this->assertSame(0, Artisan::call('admin:promote', ['email' => $user->email]));
        $this->assertSame('admin', $user->fresh()->role);
    }
}
