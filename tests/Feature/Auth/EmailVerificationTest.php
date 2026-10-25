<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Auth\Notifications\VerifyEmail;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_screen_can_be_rendered()
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/verify-email');

        $response->assertStatus(200);
    }

    public function test_verification_email_uses_quickfolio_branding()
    {
        $user = User::factory()->unverified()->create();

        Notification::fake();

        $this->actingAs($user)->post('/email/verification-notification');

        Notification::assertSentTo($user, VerifyEmail::class, function ($notification) use ($user) {
            $mail = $notification->toMail($user);

            $this->assertSame('emails.verify-email', $mail->view);
            $this->assertSame('Verify your quickFolio email address', $mail->subject);
            $this->assertSame($user->name, $mail->viewData['recipientName']);
            $this->assertStringContainsString(
                '/verify-email/'.$user->getKey().'/'.sha1($user->email),
                $mail->viewData['url']
            );
            parse_str((string) parse_url($mail->viewData['url'], PHP_URL_QUERY), $query);
            $this->assertArrayHasKey('signature', $query);

            $html = view($mail->view, $mail->viewData)->render();

            $this->assertStringContainsString('#00695c', $html);
            $this->assertStringContainsString('#eff8f5', $html);
            $this->assertStringContainsString('Verify email address', $html);

            return true;
        });
    }

    public function test_email_can_be_verified()
    {
        $user = User::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
    }

    public function test_email_is_not_verified_with_invalid_hash()
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $this->actingAs($user)->get($verificationUrl);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}
