<?php

use App\Models\User;

it('redirects guests to the panel login', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('lets the configured admin access the panel', function () {
    config()->set('social.admin_email', 'admin@example.com');
    $admin = User::factory()->create(['email' => 'admin@example.com']);

    expect($admin->canAccessPanel(app('filament')->getPanel('admin')))->toBeTrue();
});

it('denies panel access to non-admin users when an admin email is configured', function () {
    config()->set('social.admin_email', 'admin@example.com');
    $other = User::factory()->create(['email' => 'someone@example.com']);

    expect($other->canAccessPanel(app('filament')->getPanel('admin')))->toBeFalse();
});

it('treats a blank admin email as unrestricted access', function (mixed $blank) {
    config()->set('social.admin_email', $blank);
    $user = User::factory()->create();

    expect($user->canAccessPanel(app('filament')->getPanel('admin')))->toBeTrue();
})->with([
    'null' => [null],
    'empty string' => [''],
]);
