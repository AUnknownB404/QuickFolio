<?php

use App\Models\Portfolio;
use App\Models\User;

test('authenticated users can view the portfolio index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/portfolios')
        ->assertOk();
});

test('authenticated users can create a portfolio', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/portfolios', [
        'title' => 'My Developer Portfolio',
        'status' => 'draft',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('portfolios', [
        'user_id' => $user->id,
        'title' => 'My Developer Portfolio',
        'status' => 'draft',
    ]);
});

test('users can update their own portfolio', function () {
    $user = User::factory()->create();
    $portfolio = Portfolio::factory()->create([
        'user_id' => $user->id,
        'title' => 'Original Title',
        'slug' => 'original-title',
        'status' => 'draft',
    ]);

    $response = $this->actingAs($user)->put('/portfolios/'.$portfolio->id, [
        'title' => 'Updated Title',
        'status' => 'published',
    ]);

    $response->assertRedirect('/portfolios/'.$portfolio->id);

    $portfolio->refresh();

    expect($portfolio->title)->toBe('Updated Title')
        ->and($portfolio->status)->toBe('published')
        ->and($portfolio->published_at)->not->toBeNull();
});

test('users cannot update another users portfolio', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $portfolio = Portfolio::factory()->create([
        'user_id' => $owner->id,
        'title' => 'Someone Else Portfolio',
        'slug' => 'someone-else-portfolio',
    ]);

    $this->actingAs($otherUser)
        ->put('/portfolios/'.$portfolio->id, [
            'title' => 'Hacked Portfolio',
            'status' => 'draft',
        ])
        ->assertForbidden();
});
