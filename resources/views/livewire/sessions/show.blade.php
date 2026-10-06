<div>
    @php($statusLabel = match ($playSession->status) { 'planned' => 'Terencana', 'active' => 'Aktif', 'completed' => 'Selesai' })

    <a class='back-link' href='{{ route('sessions.index') }}' wire:navigate><span aria-hidden='true'>←</span> Semua sesi</a>

    <header class='session-detail-header'>
        <div>
            <div class='session-title-line'>
                <p class='eyebrow'>DETAIL SESI</p>
                <span class='session-kind-badge'>{{ $playSession->typeLabel() }}</span>
                <span class='session-status session-status--{{ $playSession->status }}'>{{ $statusLabel }}</span>
            </div>
            <h1>{{ $playSession->play_date->translatedFormat('l, d F Y') }}</h1>
            @if ($playSession->typeSubtitle())<p class='session-kind-note'>{{ $playSession->typeSubtitle() }}</p>@endif
        </div>

        <div class='session-status-actions'>
            <button class='secondary-action' type='button' wire:click='openSessionTypeForm'>Ubah Jenis</button>
            @if ($playSession->status === 'planned')
                <button class='primary-action' type='button' wire:click='startSession'>Mulai Sesi</button>
                <button class='cancel-action' type='button' wire:click='completeSession'>Tandai Selesai</button>
            @elseif ($playSession->status === 'active')
                <button class='primary-action' type='button' wire:click='completeSession'>Selesaikan Sesi</button>
            @else
                <button class='reopen-action' type='button' wire:click='reopenSession'>Buka Kembali Sesi</button>
            @endif
        </div>
    </header>

    @if (session('session-message'))
        <div class='member-alert' role='status'><span class='status-dot' aria-hidden='true'></span>{{ session('session-message') }}</div>
    @endif
    @error('status') <div class='session-error' role='alert'>{{ $message }}</div> @enderror
    @error('attendance') <div class='session-error' role='alert'>{{ $message }}</div> @enderror
    @error('payment') <div class='session-error' role='alert'>{{ $message }}</div> @enderror
    @error('game') <div class='session-error' role='alert'>{{ $message }}</div> @enderror
    @error('result') <div class='session-error' role='alert'>{{ $message }}</div> @enderror

    <section class='session-summary-grid' aria-label='Ringkasan sesi'>
        <article><span>Member hadir</span><strong>{{ $playSession->session_members_count }}</strong></article>
        <article><span>Iuran per orang</span><strong>Rp{{ number_format($playSession->fee_amount, 0, ',', '.') }}</strong></article>
        <article><span>Jumlah lapangan</span><strong>{{ $playSession->court_count }}</strong></article>
    </section>

    @if ($playSession->status === 'completed')
        <div class='session-lock-notice'>
            <strong>Absensi dikunci</strong>
            <span>Sesi telah selesai. Buka kembali sesi secara sengaja untuk menambah kehadiran baru.</span>
        </div>
    @endif

    <section class='game-workspace'>
        <header class='game-section-heading'>
            <div>
                <p class='eyebrow'>ROTASI PEMAIN</p>
                <h2>Status pemain hadir</h2>
            </div>
            @if ($playSession->status !== 'completed')
                <button class='primary-action' type='button' wire:click='openCreateGameForm' @disabled($attendedMembers->count() < 4)>Atur Game Baru</button>
            @endif
        </header>

        @if ($attendedMembers->count() < 4)
            <div class='game-guidance'>Minimal empat member harus hadir sebelum game dapat diatur.</div>
        @endif

        <div class='player-state-grid'>
            @foreach ([
                ['title' => 'Sedang main', 'players' => $playingPlayers, 'tone' => 'playing'],
                ['title' => 'Menunggu giliran', 'players' => $waitingPlayers, 'tone' => 'waiting'],
                ['title' => 'Belum main', 'players' => $unplayedPlayers, 'tone' => 'unplayed'],
                ['title' => 'Siap dipilih lagi', 'players' => $readyPlayers, 'tone' => 'ready'],
            ] as $group)
                <article class='player-state player-state--{{ $group['tone'] }}'>
                    <header><span>{{ $group['title'] }}</span><strong>{{ $group['players']->count() }}</strong></header>
                    @if ($group['players']->isEmpty())
                        <p class='player-state-empty'>Tidak ada pemain.</p>
                    @else
                        <div class='player-state-list'>
                            @foreach ($group['players'] as $row)
                                <div>
                                    <span class='member-initial' aria-hidden='true'>{{ mb_strtoupper(mb_substr($row['member']->name, 0, 1)) }}</span>
                                    <span>
                                        <strong>{{ $row['member']->name }}</strong>
                                        <small>{{ ucfirst($row['member']->skill_level) }} · {{ $row['completed_games'] }}× main</small>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    </section>

    <section class='rotation-counter'>
        <header class='rotation-counter-heading'>
            <div><p class='eyebrow'>REKOMENDASI GILIRAN</p><h2>Hitung Skip Pemain</h2></div>
            <p><strong>Skip</strong> adalah jumlah game yang selesai sejak pemain terakhir main. Angka terbesar menunggu paling lama.</p>
        </header>
        @if ($rotationQueue->isEmpty())
            <div class='game-empty-state'>Semua pemain sedang main atau sudah masuk antrean.</div>
        @else
            <div class='rotation-counter-list'>
                @foreach ($rotationQueue as $index => $row)
                    <article wire:key='rotation-counter-{{ $row['member']->id }}'>
                        <span class='rotation-rank'>{{ $index + 1 }}</span>
                        <span class='member-initial' aria-hidden='true'>{{ mb_strtoupper(mb_substr($row['member']->name, 0, 1)) }}</span>
                        <div><strong>{{ $row['member']->name }}</strong><small>{{ $row['completed_games'] }}× main{{ $row['last_game_number'] ? ' · terakhir Game '.$row['last_game_number'] : '' }}</small></div>
                        @if (! $row['last_game_number'])
                            <strong class='skip-count is-priority'><span>Belum main</span><small>Prioritas</small></strong>
                        @elseif ($row['rested_games'] === 0)
                            <strong class='skip-count is-zero'><span>0 game</span><small>Baru selesai</small></strong>
                        @else
                            <strong class='skip-count'><span>{{ $row['rested_games'] }} game</span><small>Sudah di-skip</small></strong>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section class='game-board'>
        <div class='game-board-column game-board-column--current'>
            <div class='game-column-heading'><div><p class='eyebrow'>LAPANGAN</p><h2>Sedang berlangsung</h2></div></div>
            @if ($currentGame)
                @php($currentTeamA = $currentGame->gamePlayers->where('team', 'A'))
                @php($currentTeamB = $currentGame->gamePlayers->where('team', 'B'))
                <article class='match-card match-card--playing'>
                    <header><span>Game {{ $currentGame->game_number }} · {{ $currentGame->formatLabel() }}</span><span class='live-indicator'>Berlangsung</span></header>
                    <div class='match-versus'>
                        <div><small>Tim A</small><strong>{{ $currentTeamA->pluck('member.name')->join(' & ') }}</strong></div>
                        <span>VS</span>
                        <div><small>Tim B</small><strong>{{ $currentTeamB->pluck('member.name')->join(' & ') }}</strong></div>
                    </div>
                    <footer>
                        <span>Mulai {{ $currentGame->started_at?->timezone(config('app.display_timezone'))->translatedFormat('H:i') }} WIB</span>
                        <div><button type='button' wire:click='openEditGameForm({{ $currentGame->id }})'>Koreksi pemain</button><button class='finish-game' type='button' wire:click='openResultForm({{ $currentGame->id }})'>Selesaikan Game</button></div>
                    </footer>
                </article>
            @else
                <div class='game-empty-state'>Belum ada game yang sedang dimainkan.</div>
            @endif
        </div>

        <div class='game-board-column'>
            <div class='game-column-heading'><div><p class='eyebrow'>ANTREAN</p><h2>Game berikutnya</h2></div><span>{{ $waitingGames->count() }} menunggu</span></div>
            @if ($waitingGames->isEmpty())
                <div class='game-empty-state'>Belum ada game dalam antrean.</div>
            @else
                <div class='waiting-game-list'>
                    @foreach ($waitingGames as $game)
                        @php($waitingTeamA = $game->gamePlayers->where('team', 'A'))
                        @php($waitingTeamB = $game->gamePlayers->where('team', 'B'))
                        <article class='waiting-game-card' wire:key='waiting-game-{{ $game->id }}'>
                            <div class='waiting-game-number'>{{ $game->game_number }}</div>
                            <div class='waiting-matchup'>
                                <strong>{{ $waitingTeamA->pluck('member.name')->join(' & ') }}</strong>
                                <span>vs</span>
                                <strong>{{ $waitingTeamB->pluck('member.name')->join(' & ') }}</strong>
                                <small>{{ $game->formatLabel() }}</small>
                            </div>
                            <div class='waiting-game-actions'>
                                <button type='button' wire:click='openEditGameForm({{ $game->id }})'>Ubah</button>
                                <button class='start-game' type='button' wire:click='startGame({{ $game->id }})' @disabled($currentGame || $playSession->status === 'completed')>Mulai</button>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section class='game-history'>
        <div class='game-column-heading'><div><p class='eyebrow'>RIWAYAT</p><h2>Game selesai</h2></div><span>{{ $completedGames->count() }} game</span></div>
        @if ($completedGames->isEmpty())
            <div class='game-empty-state'>Belum ada game yang selesai.</div>
        @else
            <div class='game-history-list'>
                @foreach ($completedGames as $game)
                    @php($historyTeamA = $game->gamePlayers->where('team', 'A'))
                    @php($historyTeamB = $game->gamePlayers->where('team', 'B'))
                    <article wire:key='completed-game-{{ $game->id }}'>
                        <div class='history-number'><span>Game</span><strong>{{ $game->game_number }}</strong></div>
                        <div class='history-team {{ $game->winner_team === 'A' ? 'is-winner' : '' }}'><small>Tim A {{ $game->winner_team === 'A' ? '· Menang' : '' }}</small><strong>{{ $historyTeamA->pluck('member.name')->join(' & ') }}</strong></div>
                        <div class='history-score'><strong>{{ $game->scoreSummary() }}</strong><small>{{ $game->resultLabel() }}</small></div>
                        <div class='history-team {{ $game->winner_team === 'B' ? 'is-winner' : '' }}'><small>Tim B {{ $game->winner_team === 'B' ? '· Menang' : '' }}</small><strong>{{ $historyTeamB->pluck('member.name')->join(' & ') }}</strong></div>
                        <div class='history-meta'><span>{{ $game->completed_at?->timezone(config('app.display_timezone'))->translatedFormat('H:i') }} WIB</span><div><button type='button' wire:click='openEditGameForm({{ $game->id }})'>Pemain</button><button type='button' wire:click='openResultForm({{ $game->id }})'>Hasil</button></div></div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <div class='attendance-layout'>
        <section class='attendance-panel'>
            <header>
                <div><p class='eyebrow'>SUDAH HADIR</p><h2>{{ $attendances->count() }} member</h2></div>
            </header>

            @if ($attendances->isEmpty())
                <div class='attendance-empty'><p>Belum ada member yang ditandai hadir.</p></div>
            @else
                <div class='attendance-list'>
                    @foreach ($attendances as $attendance)
                        <article wire:key='attendance-{{ $attendance->id }}'>
                            <span class='member-initial' aria-hidden='true'>{{ mb_strtoupper(mb_substr($attendance->member?->name ?? '?', 0, 1)) }}</span>
                            <div class='attendance-person'>
                                <strong>{{ $attendance->member?->name ?? 'Member terhapus' }}</strong>
                                <span>Hadir {{ $attendance->attended_at->timezone(config('app.display_timezone'))->translatedFormat('H:i') }} WIB · {{ $attendance->is_duty_admin ? 'Bebas iuran' : 'Iuran Rp'.number_format($attendance->fee_amount, 0, ',', '.') }}</span>
                            </div>
                            <span class='payment-status payment-status--{{ $attendance->is_duty_admin ? 'exempt' : ($attendance->paid_at ? 'paid' : 'unpaid') }}'>{{ $attendance->is_duty_admin ? 'Admin bertugas' : ($attendance->paid_at ? 'Sudah bayar' : 'Belum bayar') }}</span>
                            <div class='attendance-actions'>
                                @if ($attendance->is_duty_admin)
                                    <button class='payment-button payment-button--cancel' type='button' wire:click='toggleDutyAdmin({{ $attendance->id }})'>Batal admin</button>
                                @elseif ($attendance->paid_at)
                                    <button class='payment-button payment-button--cancel' type='button' wire:click='cancelPayment({{ $attendance->id }})' wire:loading.attr='disabled' wire:target='cancelPayment({{ $attendance->id }})'>Batal bayar</button>
                                @else
                                    <button class='payment-button' type='button' wire:click='markAsPaid({{ $attendance->id }})' wire:loading.attr='disabled' wire:target='markAsPaid({{ $attendance->id }})'>Tandai bayar</button>
                                    <button class='duty-admin-button' type='button' wire:click='toggleDutyAdmin({{ $attendance->id }})'>Jadikan admin</button>
                                @endif
                                <button class='correction-button' type='button' wire:click='removeAttendance({{ $attendance->id }})'>Koreksi</button>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class='attendance-panel available-panel'>
            <header>
                <div><p class='eyebrow'>BELUM HADIR</p><h2>{{ $availableMembers->count() }} member aktif</h2></div>
            </header>

            @if ($playSession->status === 'completed')
                <div class='attendance-empty'><p>Penambahan absensi terkunci sampai sesi dibuka kembali.</p></div>
            @elseif ($availableMembers->isEmpty())
                <div class='attendance-empty'><p>Semua member aktif sudah tercatat hadir.</p></div>
            @else
                <div class='available-list'>
                    @foreach ($availableMembers as $member)
                        <article wire:key='available-{{ $member->id }}'>
                            <span class='member-initial' aria-hidden='true'>{{ mb_strtoupper(mb_substr($member->name, 0, 1)) }}</span>
                            <div><strong>{{ $member->name }}</strong><span>{{ ucfirst($member->skill_level) }}</span></div>
                            <button type='button' wire:click='addAttendance({{ $member->id }})'>Tandai Hadir</button>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    @if ($showSessionTypeForm)
        <div class='member-modal-backdrop' wire:click.self='closeSessionTypeForm'>
            <section class='member-modal session-details-modal' role='dialog' aria-modal='true' aria-labelledby='session-details-title'>
                <header>
                    <div><p class='eyebrow'>INFORMASI SESI</p><h2 id='session-details-title'>Ubah Jenis Sesi</h2></div>
                    <button class='modal-close' type='button' wire:click='closeSessionTypeForm' aria-label='Tutup formulir'>&times;</button>
                </header>
                <form wire:submit='saveSessionType'>
                    <label class='member-field'><span>Jenis sesi</span><select wire:model='sessionType'>@foreach (\App\Models\PlaySession::typeOptions() as $value => $label)<option value='{{ $value }}'>{{ $label }}</option>@endforeach</select>@error('sessionType')<small>{{ $message }}</small>@enderror</label>
                    <div class='modal-actions'><button class='cancel-action' type='button' wire:click='closeSessionTypeForm'>Batal</button><button class='primary-action' type='submit'>Simpan Jenis</button></div>
                </form>
            </section>
        </div>
    @endif

    @if ($showGameForm)
        @php($memberLookup = $attendedMembers->keyBy('id'))
        @php($previewA = collect($teamA)->map(fn ($id) => $memberLookup->get((int) $id)?->name)->filter()->join(' & '))
        @php($previewB = collect($teamB)->map(fn ($id) => $memberLookup->get((int) $id)?->name)->filter()->join(' & '))
        <div class='member-modal-backdrop' wire:click.self='closeGameForm'>
            <section class='member-modal game-modal' role='dialog' aria-modal='true' aria-labelledby='game-form-title'>
                <header>
                    <div><p class='eyebrow'>SUSUNAN MANUAL</p><h2 id='game-form-title'>{{ $editingGameId ? 'Koreksi Pemain' : 'Atur Game Baru' }}</h2></div>
                    <button class='modal-close' type='button' wire:click='closeGameForm' aria-label='Tutup formulir'>&times;</button>
                </header>
                <form wire:submit='saveGame'>
                    <div class='match-preview'>
                        <strong>{{ $previewA ?: 'Pemain Tim A' }}</strong><span>VS</span><strong>{{ $previewB ?: 'Pemain Tim B' }}</strong>
                    </div>
                    @if (! $editingGameId)
                        <fieldset class='game-format-options'>
                            <legend>Format game</legend>
                            <label><input type='radio' wire:model='gameFormat' value='{{ \App\Models\Game::FORMAT_ONE_SET }}'><span><strong>1 set</strong><small>1 set × 21, pemenang langsung ditentukan</small></span></label>
                            <label><input type='radio' wire:model='gameFormat' value='{{ \App\Models\Game::FORMAT_ROTATION }}'><span><strong>Rotasi cepat</strong><small>2 set × 15 poin, tanpa rubber dan dapat berakhir seri</small></span></label>
                            <label><input type='radio' wire:model='gameFormat' value='{{ \App\Models\Game::FORMAT_BEST_OF_THREE }}'><span><strong>Kompetisi Cepat</strong><small>Best of 3 × 15 poin, rubber hanya saat hasil 1–1</small></span></label>
                        </fieldset>
                    @else
                        <div class='game-format-summary'><span>Format game</span><strong>{{ match ($gameFormat) { \App\Models\Game::FORMAT_ONE_SET => '1 set × 21', \App\Models\Game::FORMAT_ROTATION => 'Rotasi cepat · 2 set × 15 (tanpa rubber)', \App\Models\Game::FORMAT_BEST_OF_THREE => 'Kompetisi Cepat · Best of 3 × 15', default => '1 set (data lama)' } }}</strong></div>
                    @endif
                    <div class='team-picker-grid'>
                        @foreach (['A' => 'teamA', 'B' => 'teamB'] as $team => $model)
                            <fieldset class='team-picker team-picker--{{ strtolower($team) }}'>
                                <legend>Tim {{ $team }}</legend>
                                @foreach ([0, 1] as $slot)
                                    <label class='member-field'>
                                        <span>Pemain {{ $slot + 1 }}</span>
                                        <select wire:model.live='{{ $model }}.{{ $slot }}'>
                                            <option value=''>Pilih pemain</option>
                                            @foreach ($attendedMembers as $member)
                                                <option value='{{ $member->id }}'>{{ $member->name }} &mdash; {{ ucfirst($member->skill_level) }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                @endforeach
                            </fieldset>
                        @endforeach
                    </div>
                    @error('game') <small class='field-error'>{{ $message }}</small> @enderror
                    @error('teamA') <small class='field-error'>{{ $message }}</small> @enderror
                    @error('teamA.*') <small class='field-error'>{{ $message }}</small> @enderror
                    @error('teamB') <small class='field-error'>{{ $message }}</small> @enderror
                    @error('teamB.*') <small class='field-error'>{{ $message }}</small> @enderror
                    @error('gameFormat') <small class='field-error'>{{ $message }}</small> @enderror
                    <div class='modal-actions'>
                        <button class='cancel-action' type='button' wire:click='closeGameForm'>Batal</button>
                        <button class='primary-action' type='submit'>{{ $editingGameId ? 'Simpan Koreksi' : 'Tambahkan ke Antrean' }}</button>
                    </div>
                </form>
            </section>
        </div>
    @endif

    @if ($resultGameId && $resultGame)
        @php($winnerPlayers = $resultGame->winner_team ? $resultGame->gamePlayers->where('team', $resultGame->winner_team)->pluck('member.name')->filter()->join(' & ') : '')
        <div class='member-modal-backdrop' wire:click.self='closeResultForm'>
            <section class='member-modal result-modal' role='dialog' aria-modal='true' aria-labelledby='result-form-title'>
                <header>
                    <div><p class='eyebrow'>HASIL GAME {{ $resultGame->game_number }}</p><h2 id='result-form-title'>{{ $resultGame->status === 'completed' ? 'Hasil Pertandingan' : ($resultGame->game_format === \App\Models\Game::FORMAT_SINGLE_SET ? 'Pilih Pemenang' : 'Catat Skor per Set') }}</h2></div>
                    <button class='modal-close' type='button' wire:click='closeResultForm' aria-label='Tutup formulir'>&times;</button>
                </header>
                <form wire:submit='saveResult'>
                    @if ($resultGame->status === 'completed')
                        @if ($resultGame->winner_team)
                            <div class='result-celebration' role='status'>
                                <span class='celebration-confetti' aria-hidden='true'>@for ($piece = 0; $piece < 12; $piece++)<i></i>@endfor</span>
                                <span class='celebration-trophy' aria-hidden='true'>🏆</span>
                                <p>SELAMAT!</p>
                                <h3>Tim {{ $resultGame->winner_team }} Memenangkan Game</h3>
                                <strong>{{ $winnerPlayers }}</strong>
                                <small>Game {{ $resultGame->game_number }} · {{ $resultGame->scoreSummary() }}</small>
                            </div>
                        @else
                            <div class='result-celebration result-celebration--draw' role='status'>
                                <span class='celebration-trophy' aria-hidden='true'>🤝</span>
                                <p>PERTANDINGAN SELESAI</p>
                                <h3>Game Berakhir Seri</h3>
                                <small>Game {{ $resultGame->game_number }} · {{ $resultGame->scoreSummary() }}</small>
                            </div>
                        @endif
                    @endif
                    <div class='game-format-summary'><span>Format</span><strong>{{ $resultGame->formatLabel() }}</strong></div>
                    @if ($resultGame->game_format === \App\Models\Game::FORMAT_SINGLE_SET)
                        <fieldset class='winner-options'>
                            <legend>Tim pemenang</legend>
                            <label><input type='radio' wire:model='winnerTeam' value='A'><span><strong>Tim A</strong><small>Pemenang game</small></span></label>
                            <label><input type='radio' wire:model='winnerTeam' value='B'><span><strong>Tim B</strong><small>Pemenang game</small></span></label>
                        </fieldset>
                        @error('winnerTeam') <small class='field-error'>{{ $message }}</small> @enderror
                        <div class='score-fields'>
                            <label class='member-field'><span>Skor Tim A <small>Opsional</small></span><input type='number' min='0' max='999' wire:model='teamAScore'></label>
                            <label class='member-field'><span>Skor Tim B <small>Opsional</small></span><input type='number' min='0' max='999' wire:model='teamBScore'></label>
                        </div>
                        @error('teamAScore') <small class='field-error'>{{ $message }}</small> @enderror
                        @error('teamBScore') <small class='field-error'>{{ $message }}</small> @enderror
                    @else
                        @if ($resultGame->status !== 'completed' && in_array($resultGame->game_format, [\App\Models\Game::FORMAT_ONE_SET, \App\Models\Game::FORMAT_ROTATION, \App\Models\Game::FORMAT_BEST_OF_THREE], true))
                            <p class='form-help'>Set dimenangkan pada {{ $resultGame->point_target }} poin. Jika {{ $resultGame->point_target - 1 }}–{{ $resultGame->point_target - 1 }}, permainan dilanjutkan sampai unggul 2 poin, maksimal {{ $resultGame->point_target + 9 }} poin.</p>
                        @endif
                        <div class='set-score-list'>
                            @foreach ($setScores as $index => $setScore)
                                <fieldset class='set-score-row' wire:key='set-score-{{ $index }}'>
                                    <legend>Set {{ $index + 1 }} {{ $resultGame->game_format === \App\Models\Game::FORMAT_BEST_OF_THREE && $index === 2 ? '· Rubber bila 1–1' : '' }}</legend>
                                    <label class='member-field'><span>Tim A</span><input type='number' min='0' max='{{ $resultGame->point_target + 9 }}' wire:model='setScores.{{ $index }}.team_a_score' placeholder='0'></label>
                                    <span>:</span>
                                    <label class='member-field'><span>Tim B</span><input type='number' min='0' max='{{ $resultGame->point_target + 9 }}' wire:model='setScores.{{ $index }}.team_b_score' placeholder='0'></label>
                                    @error('setScores.'.$index.'.team_a_score') <small class='field-error'>{{ $message }}</small> @enderror
                                    @error('setScores.'.$index.'.team_b_score') <small class='field-error'>{{ $message }}</small> @enderror
                                </fieldset>
                            @endforeach
                        </div>
                    @endif
                    @error('result') <small class='field-error'>{{ $message }}</small> @enderror
                    <div class='modal-actions'>
                        <button class='cancel-action' type='button' wire:click='closeResultForm'>Batal</button>
                        <button class='primary-action' type='submit'>Simpan Hasil</button>
                    </div>
                </form>
            </section>
        </div>
    @endif
</div>
