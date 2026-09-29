<div>
    <header class='page-header cash-page-header'>
        <div>
            <p class='eyebrow'>KEUANGAN KOMUNITAS</p>
            <h1>Kas</h1>
            <p>Catat pemasukan dan pengeluaran serta pantau saldo {{ $appSettings->app_name }}.</p>
        </div>
        <button class='primary-action' type='button' wire:click='openForm'><span aria-hidden='true'>+</span> Catat Transaksi</button>
    </header>

    @if (session('cash-message'))
        <div class='member-alert' role='status'><span class='status-dot' aria-hidden='true'></span>{{ session('cash-message') }}</div>
    @endif

    <section class='cash-summary-grid' aria-label='Ringkasan kas'>
        <article class='cash-summary cash-summary--income'><span>Total pemasukan</span><strong>Rp{{ number_format($totalIncome, 0, ',', '.') }}</strong></article>
        <article class='cash-summary cash-summary--expense'><span>Total pengeluaran</span><strong>Rp{{ number_format($totalExpense, 0, ',', '.') }}</strong></article>
        <article class='cash-summary cash-summary--balance'><span>Saldo</span><strong>Rp{{ number_format($balance, 0, ',', '.') }}</strong></article>
    </section>

    <form class='cash-filter-bar' wire:submit='applyFilters'>
        <label class='filter-control'><span>Dari tanggal</span><input wire:model='dateFrom' type='date'>@error('dateFrom') <small>{{ $message }}</small> @enderror</label>
        <label class='filter-control'><span>Sampai tanggal</span><input wire:model='dateTo' type='date'>@error('dateTo') <small>{{ $message }}</small> @enderror</label>
        <button class='secondary-action' type='submit'>Terapkan</button>
        @if ($hasFilters)<button class='clear-filter' type='button' wire:click='resetFilters'>Reset filter</button>@endif
    </form>

    <section class='cash-panel'>
        <div class='member-panel-heading'>
            <div><p class='eyebrow'>RIWAYAT KAS</p><h2>{{ $transactions->total() }} transaksi</h2></div>
        </div>

        @if ($transactions->isEmpty())
            <div class='member-empty'><div class='member-empty-icon' aria-hidden='true'><x-nav-icon name='cash' /></div><h3>Belum ada transaksi</h3><p>Catat transaksi manual atau tandai pembayaran iuran dari detail sesi.</p></div>
        @else
            <div class='cash-table-wrap'>
                <table class='cash-table'>
                    <thead><tr><th>Tanggal</th><th>Kategori</th><th>Keterangan</th><th>Jenis</th><th>Nominal</th></tr></thead>
                    <tbody>
                        @foreach ($transactions as $transaction)
                            <tr wire:key='cash-row-{{ $transaction->id }}'>
                                <td>{{ $transaction->transaction_date->translatedFormat('d M Y') }}</td>
                                <td><span class='category-icon' style='--category-color: {{ $transaction->cashCategory->color ?: ($transaction->cashCategory->type === 'income' ? '#2a8a5d' : '#b6654a') }}'>{{ $transaction->cashCategory->icon ?: '•' }}</span><strong>{{ $transaction->cashCategory->name }}</strong>@if ($transaction->play_session_member_id)<span class='automatic-badge'>Otomatis</span>@endif</td>
                                <td>{{ $transaction->description ?: '-' }}</td>
                                <td><span class='cash-type cash-type--{{ $transaction->cashCategory->type }}'>{{ $transaction->cashCategory->type === 'income' ? 'Pemasukan' : 'Pengeluaran' }}</span></td>
                                <td class='cash-amount cash-amount--{{ $transaction->cashCategory->type }}'>{{ $transaction->cashCategory->type === 'income' ? '+' : '-' }}Rp{{ number_format($transaction->amount, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class='cash-card-list'>
                @foreach ($transactions as $transaction)
                    <article class='cash-card' wire:key='cash-card-{{ $transaction->id }}'>
                        <header><div><span class='category-icon' style='--category-color: {{ $transaction->cashCategory->color ?: ($transaction->cashCategory->type === 'income' ? '#2a8a5d' : '#b6654a') }}'>{{ $transaction->cashCategory->icon ?: '•' }}</span><strong>{{ $transaction->cashCategory->name }}</strong>@if ($transaction->play_session_member_id)<span class='automatic-badge'>Otomatis</span>@endif</div><span>{{ $transaction->transaction_date->translatedFormat('d M Y') }}</span></header>
                        <p>{{ $transaction->description ?: 'Tanpa keterangan' }}</p>
                        <footer><span class='cash-type cash-type--{{ $transaction->cashCategory->type }}'>{{ $transaction->cashCategory->type === 'income' ? 'Pemasukan' : 'Pengeluaran' }}</span><strong class='cash-amount--{{ $transaction->cashCategory->type }}'>{{ $transaction->cashCategory->type === 'income' ? '+' : '-' }}Rp{{ number_format($transaction->amount, 0, ',', '.') }}</strong></footer>
                    </article>
                @endforeach
            </div>

            @if ($transactions->hasPages())<div class='member-pagination'>{{ $transactions->links('components.pagination') }}</div>@endif
        @endif
    </section>

    @if ($showForm)
        <div class='member-modal-backdrop' wire:click.self='closeForm'>
            <section class='member-modal cash-modal' role='dialog' aria-modal='true' aria-labelledby='cash-form-title'>
                <header><div><p class='eyebrow'>TRANSAKSI MANUAL</p><h2 id='cash-form-title'>Catat Transaksi</h2></div><button class='modal-close' type='button' wire:click='closeForm' aria-label='Tutup formulir'>&times;</button></header>
                <form wire:submit='saveTransaction'>
                    <fieldset class='cash-type-options'>
                        <legend>Jenis transaksi</legend>
                        <label><input wire:model.live='transactionType' type='radio' value='income'><span>Pemasukan</span></label>
                        <label><input wire:model.live='transactionType' type='radio' value='expense'><span>Pengeluaran</span></label>
                        @error('transactionType') <small class='field-error'>{{ $message }}</small> @enderror
                    </fieldset>
                    <label class='member-field'><span>Kategori</span><select wire:model='categoryId'><option value=''>Pilih kategori</option>@foreach ($categories as $category)<option value='{{ $category->id }}'>{{ $category->name }}</option>@endforeach</select>@error('categoryId') <small>{{ $message }}</small> @enderror</label>
                    <div class='cash-form-grid'>
                        <label class='member-field'><span>Tanggal</span><input wire:model='transactionDate' type='date'>@error('transactionDate') <small>{{ $message }}</small> @enderror</label>
                        <label class='member-field'><span>Nominal</span><x-formatted-money-input model='amount' placeholder='Contoh: 150.000' />@error('amount') <small>{{ $message }}</small> @enderror</label>
                    </div>
                    <label class='member-field'><span>Keterangan <small>(opsional)</small></span><textarea wire:model='description' rows='3' maxlength='1000' placeholder='Tambahkan catatan transaksi'></textarea>@error('description') <small>{{ $message }}</small> @enderror</label>
                    <div class='modal-actions'><button class='cancel-action' type='button' wire:click='closeForm'>Batal</button><button class='primary-action' type='submit' wire:loading.attr='disabled'><span wire:loading.remove wire:target='saveTransaction'>Simpan Transaksi</span><span wire:loading wire:target='saveTransaction'>Menyimpan&hellip;</span></button></div>
                </form>
            </section>
        </div>
    @endif

</div>
