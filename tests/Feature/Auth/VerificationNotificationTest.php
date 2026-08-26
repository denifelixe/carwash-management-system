<?php

use App\Models\Admin;
use Illuminate\Support\Facades\Notification;

test('email verification notifications are disabled', function () {
    Notification::fake();

    $admin = Admin::factory()->unverified()->create();

    $this->actingAs($admin, 'admin')
        ->post('https://'.config('domains.admin').'/email/verification-notification')
        ->assertNotFound();

    Notification::assertNothingSent();
});
