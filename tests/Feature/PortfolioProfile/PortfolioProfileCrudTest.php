<?php

use App\Models\Portfolio;
use App\Models\User;

test('authenticated users can create a portfolio profile', function () {
    $user = User::factory()->create();
    $portfolio = Portfolio::factory()->create([
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->post('/portfolios/'.$portfolio->id.'/profile', [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'headline' => 'Mathematician and writer',
        'about' => 'Worked on analytical engines and early computing ideas.',
        'location' => 'London',
        'phone' => '+44 20 1234 5678',
    ]);

    $response->assertRedirect('/portfolios/'.$portfolio->id.'/profile');

    $this->assertDatabaseHas('portfolio_profiles', [
        'portfolio_id' => $portfolio->id,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'headline' => 'Mathematician and writer',
    ]);
});

test('users can update their portfolio profile', function () {
    $user = User::factory()->create();
    $portfolio = Portfolio::factory()->create([
        'user_id' => $user->id,
    ]);
    $portfolio->profile()->create([
        'first_name' => 'Grace',
        'last_name' => 'Hopper',
        'headline' => 'Computer scientist',
    ]);

    $response = $this->actingAs($user)->put('/portfolios/'.$portfolio->id.'/profile', [
        'first_name' => 'Grace',
        'last_name' => 'Murray',
        'headline' => 'Pioneer in computing',
        'about' => 'Developer of COBOL and compiler theory.',
        'location' => 'Arlington',
    ]);

    $response->assertRedirect('/portfolios/'.$portfolio->id.'/profile');

    $portfolio->refresh();

    expect($portfolio->profile->last_name)->toBe('Murray')
        ->and($portfolio->profile->headline)->toBe('Pioneer in computing');
});

test('users cannot access another users portfolio profile', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $portfolio = Portfolio::factory()->create([
        'user_id' => $owner->id,
    ]);

    $this->actingAs($otherUser)
        ->get('/portfolios/'.$portfolio->id.'/profile')
        ->assertForbidden();
});

test('users can delete their portfolio profile', function () {
    $user = User::factory()->create();
    $portfolio = Portfolio::factory()->create([
        'user_id' => $user->id,
    ]);
    $portfolio->profile()->create([
        'first_name' => 'Alan',
        'last_name' => 'Turing',
        'headline' => 'Cryptanalyst',
    ]);

    $this->actingAs($user)
        ->delete('/portfolios/'.$portfolio->id.'/profile')
        ->assertRedirect('/portfolios/'.$portfolio->id);

    expect($portfolio->fresh()->profile)->toBeNull();
});
