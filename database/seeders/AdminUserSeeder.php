<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the single admin user for this personal tool.
     *
     * Credentials come from ADMIN_EMAIL / ADMIN_PASSWORD so real values can be
     * set without editing code. Idempotent: re-running updates the password.
     */
    public function run(): void
    {
        $email = config('social.admin_email') ?: 'admin@example.com';

        $user = User::firstOrNew(['email' => $email]);

        // forceFill so email_verified_at (outside #[Fillable]) is not dropped by
        // mass-assignment guarding. The 'hashed' cast leaves an already-hashed
        // value untouched, so there is no double-hash.
        $user->forceFill([
            'name' => env('ADMIN_NAME', 'Admin'),
            'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
            'email_verified_at' => now(),
        ])->save();
    }
}
