<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('admin:promote {email}', function (string $email) {
    $user = User::where('email', $email)->first();

    if (! $user) {
        $this->error("No user found with email [{$email}]. Register the account first, then run this command again.");

        return 1;
    }

    $user->role = 'admin';
    $user->save();
    $this->info("{$user->email} is now an administrator.");

    return 0;
})->purpose('Grant administrator access to an existing user');
