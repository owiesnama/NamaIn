<x-print>
    @php
        $currency = preference('currency', 'USD');
        $logo     = preference('logo', '/images/logo.svg');

        // Build sorted activity list
        $activities = collect();

        foreach($invoices as $invoice) {
            $activities->push([
                'date'        => \Carbon\Carbon::parse($invoice->getRawOriginal('created_at')),
                'description' => __('Invoice') . ' #' . ($invoice->serial_number ?: $invoice->id),
                'debit'       => (float) $invoice->total,
                'credit'      => 0,
                'type'        => 'invoice',
            ]);
        }

        foreach($payments as $payment) {
            $activities->push([
                'date'        => $payment->paid_at,
                'description' => __('Payment') . ' — ' . __($payment->payment_method->label()),
                'debit'       => 0,
                'credit'      => (float) $payment->amount,
                'type'        => 'payment',
            ]);
        }

        $activities    = $activities->sortBy('date')->values();
        $totalDebits   = $activities->sum('debit');
        $totalCredits  = $activities->sum('credit');
        $closingBalance = $opening_balance + $totalDebits - $totalCredits;
    @endphp

    <div class="page">

        {{-- ── Header band ─────────────────────────────── --}}
        <div class="print-header dark">
            <div class="logo">
                <img src="{{ $logo }}" alt="Logo">
            </div>
            <div class="doc-title">{{ __('Account Statement') }}</div>
        </div>

        {{-- ── Meta strip ──────────────────────────────── --}}
        <div class="meta-strip">
            <div class="meta-item">
                <div class="meta-label">{{ __('Account') }}</div>
                <div class="meta-value">{{ $customer->name }}</div>
            </div>
            @if($customer->phone_number)
                <div class="meta-item">
                    <div class="meta-label">{{ __('Phone') }}</div>
                    <div class="meta-value">{{ $customer->phone_number }}</div>
                </div>
            @endif
            <div class="meta-item">
                <div class="meta-label">{{ __('Period') }}</div>
                <div class="meta-value">{{ $start_date }} — {{ $end_date }}</div>
            </div>
            <div class="meta-item">
                <div class="meta-label">{{ __('Generated') }}</div>
                <div class="meta-value">{{ now()->format('Y-m-d') }}</div>
            </div>
        </div>

        @if($customer->address)
            <div style="padding: 8px 32px; font-size: 10px; color: #6b7280; background: #fafafa; border-bottom: 1px solid #e5e7eb;">
                {{ $customer->address }}
            </div>
        @endif

        {{-- ── Stats row ────────────────────────────────── --}}
        <div class="stats-row">
            <div class="stat-card neutral">
                <div class="stat-label">{{ __('Opening Balance') }}</div>
                <div class="stat-value">{{ number_format($opening_balance, 2) }}</div>
                <div style="font-size: 8px; color: #9ca3af; margin-top: 2px;">{{ $currency }}</div>
            </div>
            <div class="stat-card danger">
                <div class="stat-label">{{ __('Total Invoiced') }}</div>
                <div class="stat-value">{{ number_format($totalDebits, 2) }}</div>
                <div style="font-size: 8px; color: #9ca3af; margin-top: 2px;">{{ $currency }}</div>
            </div>
            <div class="stat-card accent">
                <div class="stat-label">{{ __('Total Paid') }}</div>
                <div class="stat-value">{{ number_format($totalCredits, 2) }}</div>
                <div style="font-size: 8px; color: #9ca3af; margin-top: 2px;">{{ $currency }}</div>
            </div>
            <div class="stat-card {{ $closingBalance > 0 ? 'danger' : 'accent' }}">
                <div class="stat-label">{{ __('Closing Balance') }}</div>
                <div class="stat-value">{{ number_format(abs($closingBalance), 2) }}</div>
                <div style="font-size: 8px; color: #9ca3af; margin-top: 2px;">
                    {{ $currency }}
                    @if($closingBalance < 0) ({{ __('credit') }}) @endif
                </div>
            </div>
        </div>

        {{-- ── Ledger ───────────────────────────────────── --}}
        <div class="section" style="padding-top: 0;">
            <div class="section-title">{{ __('Transaction History') }}</div>

            @php $runningBalance = $opening_balance; @endphp

            <table class="table-ledger">
                <thead>
                    <tr>
                        <th style="width: 90px;">{{ __('Date') }}</th>
                        <th>{{ __('Description') }}</th>
                        <th style="width: 110px; text-align: end;">{{ __('Debit') }}</th>
                        <th style="width: 110px; text-align: end;">{{ __('Credit') }}</th>
                        <th style="width: 120px; text-align: end;">{{ __('Balance') }}</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- Opening row --}}
                    <tr class="opening-row">
                        <td>{{ $start_date }}</td>
                        <td class="font-semibold">{{ __('Opening Balance') }}</td>
                        <td class="text-end"></td>
                        <td class="text-end"></td>
                        <td class="text-end font-semibold">
                            {{ number_format($opening_balance, 2) }} {{ $currency }}
                        </td>
                    </tr>

                    @foreach($activities as $activity)
                        @php
                            $runningBalance += $activity['debit'];
                            $runningBalance -= $activity['credit'];
                        @endphp
                        <tr>
                            <td class="no-wrap text-gray-500">{{ \Carbon\Carbon::parse($activity['date'])->format('Y-m-d') }}</td>
                            <td>{{ $activity['description'] }}</td>
                            <td class="text-end no-wrap text-red">
                                {{ $activity['debit'] > 0 ? number_format($activity['debit'], 2) . ' ' . $currency : '' }}
                            </td>
                            <td class="text-end no-wrap text-emerald">
                                {{ $activity['credit'] > 0 ? number_format($activity['credit'], 2) . ' ' . $currency : '' }}
                            </td>
                            <td class="text-end no-wrap font-semibold">
                                {{ number_format($runningBalance, 2) }} {{ $currency }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" class="text-end">{{ __('Totals') }}</td>
                        <td class="text-end no-wrap text-red">{{ number_format($totalDebits, 2) }} {{ $currency }}</td>
                        <td class="text-end no-wrap text-emerald">{{ number_format($totalCredits, 2) }} {{ $currency }}</td>
                        <td class="text-end no-wrap">{{ number_format($closingBalance, 2) }} {{ $currency }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- ── Footer ───────────────────────────────────── --}}
        <div class="print-footer">
            <div>
                <div class="signature-line">{{ __('Authorized Signature') }}</div>
                <div style="margin-top: 8px; font-size: 8px; color: #9ca3af;">
                    {{ __('Generated on') }} {{ now()->format('Y-m-d H:i') }}
                </div>
            </div>
            <div class="qr-wrap">{!! $qr !!}</div>
        </div>

    </div>
</x-print>
