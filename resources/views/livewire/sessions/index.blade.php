<div>
    <header class='page-header session-page-header'>
        <div>
            <p class='eyebrow'>OPERASIONAL</p>
            <h1>Sesi Main</h1>
            <p>Atur jadwal, nominal iuran, lapangan, dan kehadiran setiap sesi badminton.</p>
        </div>
        <button class='primary-action' type='button' wire:click='openCreateForm'>
            <span aria-hidden='true'>+</span>
            Buat Sesi
        </button>
    </header>

    @if (session('session-message'))
        <div class='member-alert' role='status'><span class='status-dot' aria-hidden='true'></span>{{ session('session-message') }}</div>
    @endif
    @error('deleteSession') <div class='session-error session-error--standalone' role='alert'>{{ $message }}</div> @enderror

    <section class='session-filter-bar'>
        <label class='filter-control'>
            <span>Status sesi</span>
            <select wire:model.live='statusFilter'>
                <option value=''>Semua status</option>
                <option value='planned'>Terencana</option>
                <option value='active'>Aktif</option>
                <option value='completed'>Selesai</option>
            </select>
        </label>
        <label class='filter-control'>
            <span>Jenis sesi</span>
            <select wire:model.live='sessionTypeFilter'>
                <option value=''>Semua jenis</option>
                @foreach ($sessionTypes as $value => $label)<option value='{{ $value }}'>{{ $label }}</option>@endforeach
            </select>
        </label>
    </section>

    <section class='session-panel'>
        <div class='session-panel-heading'>
            <div>
                <p class='eyebrow'>DAFTAR SESI</p>
                <h2>{{ $sessions->total() }} sesi ditemukan</h2>
            </div>
        </div>

        @if ($sessions->isEmpty())
            <div class='member-empty'>
                <div class='member-empty-icon' aria-hidden='true'><x-nav-icon name='sessions' /></div>
                <h3>{{ $statusFilter || $sessionTypeFilter ? 'Sesi tidak ditemukan' : 'Belum ada sesi main' }}</h3>
                <p>{{ $statusFilter || $sessionTypeFilter ? 'Tidak ada sesi dengan filter yang dipilih.' : 'Buat sesi pertama untuk mulai mencatat kehadiran member.' }}</p>
                @if (! $statusFilter && ! $sessionTypeFilter)
                    <button class='secondary-action' type='button' wire:click='openCreateForm'>Buat sesi pertama</button>
                @endif
            </div>
        @else
            <div class='session-table-wrap'>
                <table class='session-table'>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Jenis Sesi</th>
                            <th>Status</th>
                            <th>Hadir</th>
                            <th>Iuran</th>
                            <th>Lapangan</th>
                            <th><span class='sr-only'>Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sessions as $session)
                            @php($statusLabel = match ($session->status) { 'planned' => 'Terencana', 'active' => 'Aktif', 'completed' => 'Selesai' })
                            <tr wire:key='session-row-{{ $session->id }}'>
                                <td>
                                    <strong>{{ $session->play_date->translatedFormat('d F Y') }}</strong>
                                    <span>{{ $session->play_date->translatedFormat('l') }}</span>
                                </td>
                                <td class='session-kind-cell'><strong>{{ $session->typeLabel() }}</strong>@if($session->typeSubtitle())<em>{{ $session->typeSubtitle() }}</em>@endif</td>
                                <td><span class='session-status session-status--{{ $session->status }}'>{{ $statusLabel }}</span></td>
                                <td>{{ $session->session_members_count }} member</td>
                                <td>Rp{{ number_format($session->fee_amount, 0, ',', '.') }}</td>
                                <td>{{ $session->court_count }}</td>
                                <td><div class='session-row-actions'><a class='detail-link' href='{{ route('sessions.show', $session) }}' wire:navigate>Kelola <span aria-hidden='true'>→</span></a><button class='session-delete-button' type='button' wire:click='deleteSession({{ $session->id }})' wire:confirm='Hapus sesi ini? Absensi yang belum dibayar juga akan dihapus.'>Hapus</button></div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class='session-card-list'>
                @foreach ($sessions as $session)
                    @php($statusLabel = match ($session->status) { 'planned' => 'Terencana', 'active' => 'Aktif', 'completed' => 'Selesai' })
                    <article class='session-card' wire:key='session-card-{{ $session->id }}'>
                        <a class='session-card-main' href='{{ route('sessions.show', $session) }}' wire:navigate>
                            <div class='session-card-top'>
                                <div><strong>{{ $session->play_date->translatedFormat('d F Y') }}</strong><span>{{ $session->play_date->translatedFormat('l') }}</span></div>
                                <span class='session-status session-status--{{ $session->status }}'>{{ $statusLabel }}</span>
                            </div>
                            <div class='session-card-kind'><strong>{{ $session->typeLabel() }}</strong>@if($session->typeSubtitle())<em>{{ $session->typeSubtitle() }}</em>@endif</div>
                            <dl>
                                <div><dt>Hadir</dt><dd>{{ $session->session_members_count }} member</dd></div>
                                <div><dt>Iuran</dt><dd>Rp{{ number_format($session->fee_amount, 0, ',', '.') }}</dd></div>
                                <div><dt>Lapangan</dt><dd>{{ $session->court_count }}</dd></div>
                            </dl>
                        </a>
                        <button class='session-delete-button session-delete-button--mobile' type='button' wire:click='deleteSession({{ $session->id }})' wire:confirm='Hapus sesi ini? Absensi yang belum dibayar juga akan dihapus.'>Hapus sesi</button>
                    </article>
                @endforeach
            </div>

            @if ($sessions->hasPages())
                <div class='member-pagination'>{{ $sessions->links('components.pagination') }}</div>
            @endif
        @endif
    </section>

    @if ($showCreateForm)
        <div class='member-modal-backdrop' wire:click.self='closeCreateForm'>
            <section class='member-modal session-modal' role='dialog' aria-modal='true' aria-labelledby='session-form-title'>
                <header>
                    <div><p class='eyebrow'>SESI BARU</p><h2 id='session-form-title'>Buat Sesi Main</h2></div>
                    <button class='modal-close' type='button' wire:click='closeCreateForm' aria-label='Tutup formulir'>×</button>
                </header>

                <form wire:submit='createSession'>
                    <div class='session-form-grid'>
                        <label class='member-field session-date-field'>
                            <span>Tanggal main</span>
                            <input wire:model='playDate' type='date'>
                            @error('playDate') <small>{{ $message }}</small> @enderror
                        </label>
                        <label class='member-field'>
                            <span>Jenis sesi</span>
                            <select wire:model='sessionType'>
                                @foreach ($sessionTypes as $value => $label)<option value='{{ $value }}'>{{ $label }}</option>@endforeach
                            </select>
                            @error('sessionType') <small>{{ $message }}</small> @enderror
                        </label>
                        <label class='member-field'>
                            <span>Iuran per orang</span>
                            <div class='money-input'><span>Rp</span><x-formatted-money-input model='feeAmount' aria-label='Nominal iuran per orang' /></div>
                            @error('feeAmount') <small>{{ $message }}</small> @enderror
                        </label>
                        <label class='member-field'>
                            <span>Jumlah lapangan</span>
                            <input wire:model='courtCount' type='number' min='1' max='20'>
                            @error('courtCount') <small>{{ $message }}</small> @enderror
                        </label>
                    </div>

                    <fieldset class='attendance-picker'>
                        <legend>Member yang langsung hadir <span>Opsional</span></legend>
                        @if ($activeMembers->isEmpty())
                            <div class='picker-empty'>Belum ada member aktif. Tambahkan atau aktifkan member terlebih dahulu.</div>
                        @else
                            <div class='attendance-options'>
                                @foreach ($activeMembers as $member)
                                    <label>
                                        <input wire:model='selectedMemberIds' type='checkbox' value='{{ $member->id }}'>
                                        <span class='member-initial' aria-hidden='true'>{{ mb_strtoupper(mb_substr($member->name, 0, 1)) }}</span>
                                        <span><strong>{{ $member->name }}</strong><small>{{ ucfirst($member->skill_level) }}</small></span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                        @error('selectedMemberIds.*') <small class='field-error'>{{ $message }}</small> @enderror
                    </fieldset>

                    <div class='modal-actions'>
                        <button class='cancel-action' type='button' wire:click='closeCreateForm'>Batal</button>
                        <button class='primary-action' type='submit' wire:loading.attr='disabled'>
                            <span wire:loading.remove wire:target='createSession'>Buat Sesi</span>
                            <span wire:loading wire:target='createSession'>Menyimpan…</span>
                        </button>
                    </div>
                </form>
            </section>
        </div>
    @endif
</div>
