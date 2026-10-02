<?php

namespace Tests\Feature;

use App\Livewire\Sessions\Index as SessionsIndex;
use App\Livewire\Settings\Index as SettingsIndex;
use App\Models\ApplicationSetting;
use App\Models\PlaySession;
use App\Models\User;
use Database\Seeders\ApplicationSettingSeeder;
use Database\Seeders\CashCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            ApplicationSettingSeeder::class,
            CashCategorySeeder::class,
        ]);
    }

    public function test_settings_page_requires_an_authenticated_admin(): void
    {
        $this->get('/pengaturan')->assertRedirect('/login');

        $this->actingAs(User::factory()->create())
            ->get('/pengaturan')
            ->assertOk()
            ->assertSee('Nama dan Logo')
            ->assertSee('Nilai Default')
            ->assertSee('Profil dan Keamanan');
    }

    public function test_session_defaults_seed_new_form_without_changing_existing_session(): void
    {
        $admin = User::factory()->create();
        $existingSession = PlaySession::query()->create([
            'play_date' => '2026-09-20',
            'fee_amount' => 15000,
            'court_count' => 1,
            'status' => 'planned',
        ]);

        Livewire::actingAs($admin)
            ->test(SettingsIndex::class)
            ->set('defaultFeeAmount', '25.000')
            ->set('defaultCourtCount', 3)
            ->call('saveSessionDefaults')
            ->assertHasNoErrors()
            ->assertSet('defaultFeeAmount', '25.000');

        $this->assertDatabaseHas('application_settings', [
            'default_fee_amount' => 25000,
            'default_court_count' => 3,
        ]);
        $this->assertDatabaseHas('play_sessions', [
            'id' => $existingSession->id,
            'fee_amount' => 15000,
            'court_count' => 1,
        ]);

        Livewire::actingAs($admin)
            ->test(SessionsIndex::class)
            ->call('openCreateForm')
            ->assertSet('feeAmount', '25.000')
            ->assertSet('courtCount', 3)
            ->set('playDate', '2026-09-27')
            ->call('createSession')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('play_sessions', [
            'play_date' => '2026-09-27 00:00:00',
            'fee_amount' => 25000,
            'court_count' => 3,
        ]);
    }

    public function test_identity_accepts_an_image_and_is_used_on_login_page(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(SettingsIndex::class)
            ->set('appName', 'Klub Juvi')
            ->set('logoUpload', UploadedFile::fake()->image('logo.png', 300, 300))
            ->call('saveIdentity')
            ->assertHasNoErrors();

        $settings = ApplicationSetting::current();
        $this->assertSame('Klub Juvi', $settings->app_name);
        Storage::disk('public')->assertExists($settings->logo_path);
        $logoUrl = Storage::disk('public')->url($settings->logo_path);

        auth()->logout();
        $this->get('/login')
            ->assertOk()
            ->assertSee('Klub Juvi')
            ->assertSee("<link rel='icon' href='{$logoUrl}'>", false);

        $this->actingAs($admin)
            ->get('/')
            ->assertOk()
            ->assertSee('<title>Dashboard · Klub Juvi</title>', false)
            ->assertSee("<link rel='icon' href='{$logoUrl}'>", false);
    }

    public function test_admin_account_requires_current_password_before_updating(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin Lama',
            'email' => 'lama@example.test',
            'password' => Hash::make('rahasia-lama'),
        ]);

        Livewire::actingAs($admin)
            ->test(SettingsIndex::class)
            ->set('adminName', 'Admin Baru')
            ->set('adminEmail', 'baru@example.test')
            ->set('currentPassword', 'salah')
            ->call('saveAdminAccount')
            ->assertHasErrors(['currentPassword']);

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'name' => 'Admin Lama',
            'email' => 'lama@example.test',
        ]);

        Livewire::actingAs($admin->fresh())
            ->test(SettingsIndex::class)
            ->set('adminName', 'Admin Baru')
            ->set('adminEmail', 'baru@example.test')
            ->set('currentPassword', 'rahasia-lama')
            ->set('newPassword', 'password-baru')
            ->set('newPasswordConfirmation', 'password-baru')
            ->call('saveAdminAccount')
            ->assertHasNoErrors();

        $admin->refresh();
        $this->assertSame('Admin Baru', $admin->name);
        $this->assertSame('baru@example.test', $admin->email);
        $this->assertTrue(Hash::check('password-baru', $admin->password));
    }
}
