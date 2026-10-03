<?php

namespace App\Livewire\Settings;

use App\Models\ApplicationSetting;
use App\Models\CashCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app', ['title' => 'Pengaturan'])]
class Index extends Component
{
    use WithFileUploads;

    public string $appName = '';

    public $logoUpload;

    public string $defaultFeeAmount = '15.000';

    public int|string $defaultCourtCount = 1;

    /** @var array<int, array{name: string, icon: string, color: string}> */
    public array $categories = [];

    public function mount(): void
    {
        $settings = ApplicationSetting::current();
        $this->appName = $settings->app_name;
        $this->defaultFeeAmount = number_format($settings->default_fee_amount, 0, ',', '.');
        $this->defaultCourtCount = $settings->default_court_count;
        $this->loadCategories();
    }

    public function saveIdentity(): void
    {
        $validated = $this->validate([
            'appName' => ['required', 'string', 'min:2', 'max:100'],
            'logoUpload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'appName.required' => 'Nama aplikasi wajib diisi.',
            'logoUpload.image' => 'Logo harus berupa gambar.',
            'logoUpload.mimes' => 'Logo harus berformat JPG, PNG, atau WebP.',
            'logoUpload.max' => 'Ukuran logo maksimal 2 MB.',
        ]);
        $settings = ApplicationSetting::current();
        $oldLogo = $settings->logo_path;
        $newLogo = $oldLogo;

        if ($this->logoUpload) {
            $newLogo = $this->logoUpload->store('branding', 'public');
        }

        $settings->fill([
            'app_name' => trim($validated['appName']),
            'logo_path' => $newLogo,
        ])->save();

        if ($this->logoUpload && $oldLogo && $oldLogo !== $newLogo) {
            Storage::disk('public')->delete($oldLogo);
        }

        $this->logoUpload = null;
        session()->flash('settings-message', 'Identitas aplikasi berhasil diperbarui.');
    }

    public function saveSessionDefaults(): void
    {
        $this->defaultFeeAmount = str_replace('.', '', trim($this->defaultFeeAmount));
        $validated = $this->validate([
            'defaultFeeAmount' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'defaultCourtCount' => ['required', 'integer', 'min:1', 'max:20'],
        ], [
            'defaultFeeAmount.required' => 'Iuran default wajib diisi.',
            'defaultFeeAmount.integer' => 'Iuran default harus berupa angka.',
            'defaultFeeAmount.min' => 'Iuran default minimal Rp1.',
            'defaultCourtCount.required' => 'Jumlah lapangan default wajib diisi.',
            'defaultCourtCount.min' => 'Jumlah lapangan minimal 1.',
            'defaultCourtCount.max' => 'Jumlah lapangan maksimal 20.',
        ]);

        ApplicationSetting::current()->fill([
            'default_fee_amount' => $validated['defaultFeeAmount'],
            'default_court_count' => $validated['defaultCourtCount'],
        ])->save();
        $this->defaultFeeAmount = number_format((int) $validated['defaultFeeAmount'], 0, ',', '.');

        session()->flash('settings-message', 'Default sesi berhasil diperbarui.');
    }

    public function saveCategories(): void
    {
        $this->validate([
            'categories' => ['required', 'array'],
            'categories.*.name' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'categories.*.icon' => ['nullable', 'string', 'max:10'],
            'categories.*.color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ], [
            'categories.*.name.required' => 'Nama kategori wajib diisi.',
            'categories.*.name.distinct' => 'Nama kategori tidak boleh sama.',
            'categories.*.icon.max' => 'Ikon maksimal 10 karakter.',
            'categories.*.color.regex' => 'Warna kategori tidak valid.',
        ]);

        DB::transaction(function (): void {
            CashCategory::query()->lockForUpdate()->get()->each(function (CashCategory $category): void {
                $input = $this->categories[$category->id] ?? null;

                if (! $input) {
                    return;
                }

                $category->update([
                    'name' => trim($input['name']),
                    'icon' => trim($input['icon']) ?: null,
                    'color' => $input['color'],
                ]);
            });
        });

        $this->loadCategories();
        session()->flash('settings-message', 'Kategori kas berhasil diperbarui tanpa mengubah jenis transaksi.');
    }

    private function loadCategories(): void
    {
        $this->categories = CashCategory::query()
            ->orderBy('type')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (CashCategory $category): array => [$category->id => [
                'name' => $category->name,
                'icon' => $category->icon ?? '',
                'color' => $category->color ?? ($category->type === 'income' ? '#2a8a5d' : '#b6654a'),
            ]])
            ->all();
    }

    public function render(): View
    {
        return view('livewire.settings.index', [
            'appSettings' => ApplicationSetting::current(),
            'cashCategories' => CashCategory::query()->orderBy('type')->orderBy('id')->get(),
        ]);
    }
}
