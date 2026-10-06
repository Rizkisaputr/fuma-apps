<div>
    <header class='page-header settings-page-header'>
        <div><p class='eyebrow'>KONFIGURASI</p><h1>Pengaturan</h1><p>Kelola identitas aplikasi, default sesi, dan kategori kas.</p></div>
    </header>

    @if (session('settings-message'))
        <div class='member-alert' role='status'><span class='status-dot' aria-hidden='true'></span>{{ session('settings-message') }}</div>
    @endif

    <div class='settings-grid'>
        <section class='settings-panel'>
            <header><div><p class='eyebrow'>IDENTITAS APLIKASI</p><h2>Nama dan Logo</h2></div></header>
            <form wire:submit='saveIdentity'>
                <div class='logo-setting'>
                    <div class='logo-preview'>
                        <img src='{{ $logoUpload ? $logoUpload->temporaryUrl() : $appSettings->logoUrl() }}' alt='Pratinjau logo {{ $appName }}'>
                    </div>
                    <div><strong>Logo aplikasi</strong><p>Gunakan gambar persegi agar tampil rapi. Maksimal 2 MB.</p><label class='upload-button'>Pilih gambar<input wire:model='logoUpload' type='file' accept='image/png,image/jpeg,image/webp'></label><span wire:loading wire:target='logoUpload'>Memuat pratinjau&hellip;</span>@error('logoUpload')<small class='field-error'>{{ $message }}</small>@enderror</div>
                </div>
                <label class='member-field'><span>Nama aplikasi</span><input wire:model='appName' type='text' maxlength='100' autocomplete='off'>@error('appName')<small>{{ $message }}</small>@enderror</label>
                <div class='settings-actions'><button class='primary-action' type='submit' wire:loading.attr='disabled'>Simpan Identitas</button></div>
            </form>
        </section>

        <section class='settings-panel'>
            <header><div><p class='eyebrow'>PENGATURAN SESI</p><h2>Nilai Default</h2></div></header>
            <form wire:submit='saveSessionDefaults'>
                <p class='settings-note'>Nilai ini hanya menjadi isian awal sesi baru dan tidak mengubah sesi yang sudah tersimpan.</p>
                <div class='settings-form-grid'>
                    <label class='member-field'><span>Iuran per orang</span><div class='money-input'><span>Rp</span><x-formatted-money-input model='defaultFeeAmount' aria-label='Iuran default per orang' /></div>@error('defaultFeeAmount')<small>{{ $message }}</small>@enderror</label>
                    <label class='member-field'><span>Jumlah lapangan</span><input wire:model='defaultCourtCount' type='number' min='1' max='20'>@error('defaultCourtCount')<small>{{ $message }}</small>@enderror</label>
                </div>
                <div class='settings-actions'><button class='primary-action' type='submit'>Simpan Default Sesi</button></div>
            </form>
        </section>

        <section class='settings-panel settings-panel--wide'>
            <header><div><p class='eyebrow'>KATEGORI KAS</p><h2>Nama, Ikon, dan Warna</h2></div></header>
            <form class='new-category-form' wire:submit='addCategory'>
                <p class='settings-note'>Tambahkan kategori baru agar langsung tersedia saat mencatat transaksi kas.</p>
                <div class='settings-form-grid settings-form-grid--category'>
                    <label class='member-field'><span>Jenis</span><select wire:model.live='newCategoryType'><option value='income'>Pemasukan</option><option value='expense'>Pengeluaran</option></select>@error('newCategoryType')<small>{{ $message }}</small>@enderror</label>
                    <label class='member-field'><span>Nama kategori</span><input wire:model='newCategoryName' type='text' maxlength='100' placeholder='Contoh: Donasi'>@error('newCategoryName')<small>{{ $message }}</small>@enderror</label>
                    <label class='member-field'><span>Ikon</span><input wire:model='newCategoryIcon' type='text' maxlength='10' placeholder='Opsional'>@error('newCategoryIcon')<small>{{ $message }}</small>@enderror</label>
                    <label class='color-setting'><span>Warna</span><input wire:model.live='newCategoryColor' type='color'>@error('newCategoryColor')<small>{{ $message }}</small>@enderror</label>
                </div>
                <div class='settings-actions'><button class='secondary-action' type='submit'>Tambah Kategori</button></div>
            </form>
            <form wire:submit='saveCategories'>
                <p class='settings-note'>Jenis pemasukan atau pengeluaran dikunci. Mengubah tampilan tidak mengubah jenis transaksi lama.</p>
                <div class='settings-category-list'>
                    @foreach ($cashCategories as $category)
                        <article wire:key='settings-category-{{ $category->id }}'>
                            <div class='settings-category-type'><span class='category-icon' style='--category-color: {{ $categories[$category->id]['color'] ?? '#65716a' }}'>{{ $categories[$category->id]['icon'] ?: '•' }}</span><div><strong>{{ $category->type === 'income' ? 'Pemasukan' : 'Pengeluaran' }}</strong><small>{{ $category->code }}</small></div></div>
                            <label class='member-field'><span>Nama</span><input wire:model='categories.{{ $category->id }}.name' type='text' maxlength='100'>@error('categories.'.$category->id.'.name')<small>{{ $message }}</small>@enderror</label>
                            <label class='member-field'><span>Ikon</span><input wire:model='categories.{{ $category->id }}.icon' type='text' maxlength='10' placeholder='Contoh: 🏸'>@error('categories.'.$category->id.'.icon')<small>{{ $message }}</small>@enderror</label>
                            <label class='color-setting'><span>Warna</span><input wire:model.live='categories.{{ $category->id }}.color' type='color'>@error('categories.'.$category->id.'.color')<small>{{ $message }}</small>@enderror</label>
                        </article>
                    @endforeach
                </div>
                <div class='settings-actions'><button class='primary-action' type='submit'>Simpan Kategori</button></div>
            </form>
        </section>
    </div>
</div>
