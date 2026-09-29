<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_login_but_not_registration(): void
    {
        $this->get('/login')->assertOk()->assertSee('Selamat datang');
        $this->get('/register')->assertNotFound();
    }

    public function test_all_application_pages_redirect_guests_to_login(): void
    {
        foreach (['/', '/sesi-main', '/member', '/kas', '/laporan', '/pengaturan'] as $uri) {
            $this->get($uri)->assertRedirect('/login');
        }
    }

    public function test_admin_can_login_with_livewire(): void
    {
        $password = Str::random(16).'aA1!';
        $user = User::factory()->create(['password' => $password]);

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', $password)
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_authenticated_admin_can_open_every_navigation_page(): void
    {
        $user = User::factory()->create();
        $pages = [
            '/' => 'Dashboard',
            '/sesi-main' => 'Sesi Main',
            '/member' => 'Member',
            '/kas' => 'Kas',
            '/laporan' => 'Laporan',
            '/pengaturan' => 'Pengaturan',
        ];

        foreach ($pages as $uri => $heading) {
            $this->actingAs($user)->get($uri)->assertOk()->assertSee($heading);
        }
    }
}
