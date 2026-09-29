<div>
    <header class='page-header member-page-header'>
        <div>
            <p class='eyebrow'>DATA MASTER</p>
            <h1>Member</h1>
            <p>Kelola nama, level permainan, dan status keaktifan member {{ $appSettings->app_name }}.</p>
        </div>
        <button class='primary-action' type='button' wire:click='createMember'>
            <span aria-hidden='true'>+</span>
            Tambah Member
        </button>
    </header>

    @if (session('member-message'))
        <div class='member-alert' role='status'>
            <span class='status-dot' aria-hidden='true'></span>
            {{ session('member-message') }}
        </div>
    @endif

    <section class='member-toolbar' aria-label='Pencarian dan filter member'>
        <label class='search-control'>
            <span class='sr-only'>Cari nama member</span>
            <svg viewBox='0 0 24 24' fill='none' aria-hidden='true'>
                <circle cx='11' cy='11' r='7' stroke='currentColor' stroke-width='1.8'/>
                <path d='m16.5 16.5 4 4' stroke='currentColor' stroke-width='1.8' stroke-linecap='round'/>
            </svg>
            <input wire:model.live.debounce.300ms='search' type='search' placeholder='Cari nama member…'>
        </label>

        <label class='filter-control'>
            <span>Level</span>
            <select wire:model.live='levelFilter'>
                <option value=''>Semua level</option>
                <option value='pemula'>Pemula</option>
                <option value='menengah'>Menengah</option>
                <option value='pro'>Pro</option>
            </select>
        </label>

        <label class='filter-control'>
            <span>Status</span>
            <select wire:model.live='statusFilter'>
                <option value=''>Semua status</option>
                <option value='active'>Aktif</option>
                <option value='inactive'>Nonaktif</option>
            </select>
        </label>

        @if ($hasFilters)
            <button class='clear-filter' type='button' wire:click='resetFilters'>Reset filter</button>
        @endif
    </section>

    <section class='member-panel'>
        <div class='member-panel-heading'>
            <div>
                <p class='eyebrow'>DAFTAR MEMBER</p>
                <h2>{{ $members->total() }} member ditemukan</h2>
            </div>
            @if ($members->total() > 0)
                <span>Menampilkan {{ $members->firstItem() }}–{{ $members->lastItem() }} dari {{ $members->total() }}</span>
            @endif
        </div>

        @if ($members->isEmpty())
            <div class='member-empty'>
                <div class='member-empty-icon' aria-hidden='true'>
                    <x-nav-icon name='members' />
                </div>
                @if ($hasFilters)
                    <h3>Member tidak ditemukan</h3>
                    <p>Coba ubah kata pencarian atau filter yang dipilih.</p>
                    <button class='secondary-action' type='button' wire:click='resetFilters'>Hapus filter</button>
                @else
                    <h3>Belum ada member</h3>
                    <p>Tambahkan member pertama untuk mulai mengelola data anggota {{ $appSettings->app_name }}.</p>
                    <button class='secondary-action' type='button' wire:click='createMember'>Tambah member pertama</button>
                @endif
            </div>
        @else
            <div class='member-table-wrap'>
                <table class='member-table'>
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Jenis Kelamin</th>
                            <th>Level</th>
                            <th>Status</th>
                            <th><span class='sr-only'>Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($members as $member)
                            <tr wire:key='member-row-{{ $member->id }}'>
                                <td>
                                    <div class='member-identity'>
                                        <span class='member-initial' aria-hidden='true'>{{ mb_strtoupper(mb_substr($member->name, 0, 1)) }}</span>
                                        <div>
                                            <strong>{{ $member->name }}</strong>
                                            <span class='member-address'>{{ $member->address ?: 'Alamat belum diisi' }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $member->gender === 'laki-laki' ? 'Laki-laki' : ($member->gender === 'perempuan' ? 'Perempuan' : 'Belum diisi') }}</td>
                                <td><span class='level-badge level-badge--{{ $member->skill_level }}'>{{ ucfirst($member->skill_level) }}</span></td>
                                <td>
                                    <span class='status-badge status-badge--{{ $member->is_active ? 'active' : 'inactive' }}'>
                                        <span aria-hidden='true'></span>{{ $member->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td>
                                    <div class='row-actions'>
                                        <button type='button' wire:click='editMember({{ $member->id }})'>Edit</button>
                                        <button class='{{ $member->is_active ? 'deactivate' : 'activate' }}' type='button' wire:click='toggleActive({{ $member->id }})'>
                                            {{ $member->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class='member-card-list'>
                @foreach ($members as $member)
                    <article class='member-card' wire:key='member-card-{{ $member->id }}'>
                        <div class='member-card-main'>
                            <span class='member-initial' aria-hidden='true'>{{ mb_strtoupper(mb_substr($member->name, 0, 1)) }}</span>
                            <div>
                                <h3>{{ $member->name }}</h3>
                                <p class='member-card-profile'>
                                    {{ $member->gender === 'laki-laki' ? 'Laki-laki' : ($member->gender === 'perempuan' ? 'Perempuan' : 'Jenis kelamin belum diisi') }}
                                    <span aria-hidden='true'>·</span>
                                    {{ $member->address ?: 'Alamat belum diisi' }}
                                </p>
                                <div class='member-card-badges'>
                                    <span class='level-badge level-badge--{{ $member->skill_level }}'>{{ ucfirst($member->skill_level) }}</span>
                                    <span class='status-badge status-badge--{{ $member->is_active ? 'active' : 'inactive' }}'>
                                        <span aria-hidden='true'></span>{{ $member->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class='row-actions'>
                            <button type='button' wire:click='editMember({{ $member->id }})'>Edit</button>
                            <button class='{{ $member->is_active ? 'deactivate' : 'activate' }}' type='button' wire:click='toggleActive({{ $member->id }})'>
                                {{ $member->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                            </button>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($members->hasPages())
                <div class='member-pagination'>{{ $members->links('components.pagination') }}</div>
            @endif
        @endif
    </section>

    @if ($showForm)
        <div class='member-modal-backdrop' wire:click.self='closeForm'>
            <section class='member-modal' role='dialog' aria-modal='true' aria-labelledby='member-form-title'>
                <header>
                    <div>
                        <p class='eyebrow'>{{ $editingMemberId ? 'EDIT DATA' : 'MEMBER BARU' }}</p>
                        <h2 id='member-form-title'>{{ $editingMemberId ? 'Edit Member' : 'Tambah Member' }}</h2>
                    </div>
                    <button class='modal-close' type='button' wire:click='closeForm' aria-label='Tutup formulir'>×</button>
                </header>

                <form wire:submit='saveMember'>
                    <label class='member-field'>
                        <span>Nama member</span>
                        <input wire:model='name' type='text' maxlength='255' autocomplete='off' placeholder='Masukkan nama lengkap' autofocus>
                        @error('name') <small>{{ $message }}</small> @enderror
                    </label>

                    <label class='member-field'>
                        <span>Alamat</span>
                        <textarea wire:model='address' rows='3' maxlength='1000' placeholder='Masukkan alamat tempat tinggal'></textarea>
                        @error('address') <small>{{ $message }}</small> @enderror
                    </label>

                    <fieldset class='gender-options'>
                        <legend>Jenis kelamin</legend>
                        <label>
                            <input wire:model='gender' type='radio' value='laki-laki'>
                            <span>Laki-laki</span>
                        </label>
                        <label>
                            <input wire:model='gender' type='radio' value='perempuan'>
                            <span>Perempuan</span>
                        </label>
                        @error('gender') <small class='field-error'>{{ $message }}</small> @enderror
                    </fieldset>

                    <fieldset class='level-options'>
                        <legend>Level permainan</legend>
                        <label class='level-option'>
                            <input wire:model='skillLevel' type='radio' value='pemula'>
                            <span><strong>Pemula</strong><small>Masih belajar atau bermain santai</small></span>
                        </label>
                        <label class='level-option'>
                            <input wire:model='skillLevel' type='radio' value='menengah'>
                            <span><strong>Menengah</strong><small>Sudah konsisten dan memahami strategi dasar</small></span>
                        </label>
                        <label class='level-option'>
                            <input wire:model='skillLevel' type='radio' value='pro'>
                            <span><strong>Pro</strong><small>Berpengalaman dan bermain kompetitif</small></span>
                        </label>
                        @error('skillLevel') <small class='field-error'>{{ $message }}</small> @enderror
                    </fieldset>

                    <div class='modal-actions'>
                        <button class='cancel-action' type='button' wire:click='closeForm'>Batal</button>
                        <button class='primary-action' type='submit' wire:loading.attr='disabled'>
                            <span wire:loading.remove wire:target='saveMember'>{{ $editingMemberId ? 'Simpan Perubahan' : 'Tambah Member' }}</span>
                            <span wire:loading wire:target='saveMember'>Menyimpan…</span>
                        </button>
                    </div>
                </form>
            </section>
        </div>
    @endif
</div>
