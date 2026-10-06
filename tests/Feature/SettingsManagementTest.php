<?php

namespace Tests\Feature;

use App\Livewire\Sessions\Index as SessionsIndex;
use App\Livewire\Settings\Index as SettingsIndex;
use App\Models\ApplicationSetting;
use App\Models\CashCategory;
use App\Models\PlaySession;
use App\Models\User;
use Database\Seeders\ApplicationSettingSeeder;
use Database\Seeders\CashCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
            ->assertDontSee('Profil Administrator')
            ->assertDontSee('AKUN ADMIN');
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

    public function test_admin_can_add_cash_categories_for_each_transaction_type(): void
    {
        $component = Livewire::actingAs(User::factory()->create())
            ->test(SettingsIndex::class)
            ->set('newCategoryType', 'income')
            ->set('newCategoryName', 'Donasi Member')
            ->set('newCategoryIcon', '+')
            ->set('newCategoryColor', '#336699')
            ->call('addCategory')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cash_categories', [
            'code' => 'custom_donasi_member',
            'name' => 'Donasi Member',
            'type' => 'income',
            'is_active' => true,
        ]);

        $component
            ->set('newCategoryType', 'expense')
            ->set('newCategoryName', 'Transportasi')
            ->call('addCategory')
            ->assertHasNoErrors();

        $this->assertSame(1, CashCategory::query()->where('name', 'Transportasi')->where('type', 'expense')->count());
    }
}
