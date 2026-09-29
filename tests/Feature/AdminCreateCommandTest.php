<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminCreateCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_account_can_be_created_interactively(): void
    {
        $email = Str::uuid().'@example.test';
        $password = Str::random(16).'aA1!';

        $this->artisan('admin:create')
            ->expectsQuestion('Nama admin', 'Admin FUMA')
            ->expectsQuestion('Email admin', $email)
            ->expectsQuestion('Password admin', $password)
            ->expectsQuestion('Ulangi password admin', $password)
            ->expectsOutput('Akun admin berhasil dibuat.')
            ->assertExitCode(0);

        $admin = User::query()->where('email', $email)->firstOrFail();

        $this->assertTrue(Hash::check($password, $admin->password));
    }
}
