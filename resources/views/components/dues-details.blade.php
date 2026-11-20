@props(['transactions'])

<details class='dues-details'>
    <summary>Iuran {{ $transactions->count() }} member</summary>
    <ul>
        @foreach ($transactions as $dues)
            <li>
                <span>{{ $dues->playSessionMember?->member?->name ?? 'Member tidak tersedia' }}</span>
                <strong>Rp{{ number_format($dues->amount, 0, ',', '.') }}</strong>
            </li>
        @endforeach
    </ul>
</details>
