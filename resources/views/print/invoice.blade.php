<x-print>
    @php
        $currency = $invoice->currency ?: preference('currency', 'SDG');
        $logo     = preference('logo', '/images/logo.svg');
        $isPaid   = $invoice->payment_status === \App\Enums\PaymentStatus::Paid;

        $statusBadgeClass = match($invoice->payment_status) {
            \App\Enums\PaymentStatus::Paid          => 'badge-paid',
            \App\Enums\PaymentStatus::PartiallyPaid => 'badge-partial',
            \App\Enums\PaymentStatus::Overdue       => 'badge-overdue',
            default                                  => 'badge-unpaid',
        };

        $subtotal  = $invoice->subtotal;
        $discount  = (float) $invoice->discount;
        $total     = (float) $invoice->total;
        $paid      = (float) $invoice->paid_amount;
        $balance   = (float) $invoice->remaining_balance;
    @endphp

    <div class="page">

        {{-- ── Paid watermark ─────────────────────────── --}}
        @if($isPaid)
            <div class="stamp stamp-paid">{{ __('Paid') }}</div>
        @elseif($invoice->payment_status === \App\Enums\PaymentStatus::Unpaid)
            <div class="stamp stamp-unpaid">{{ __('Unpaid') }}</div>
        @endif

        {{-- ── Header band ─────────────────────────────── --}}
        <div class="print-header emerald">
            <div class="logo">
                <img src="{{ $logo }}" alt="Logo">
            </div>
            <div class="doc-title">{{ __('Invoice') }}</div>
        </div>

        {{-- ── Meta strip ──────────────────────────────── --}}
        <div class="meta-strip">
            <div class="meta-item">
                <div class="meta-label">{{ __('Invoice #') }}</div>
                <div class="meta-value">{{ $invoice->serial_number ?? '#' . $invoice->id }}</div>
            </div>
            <div class="meta-item">
                <div class="meta-label">{{ __('Date') }}</div>
                <div class="meta-value">{{ $invoice->created_at->format('Y-m-d') }}</div>
            </div>
            <div class="meta-item">
                <div class="meta-label">{{ __('Payment Method') }}</div>
                <div class="meta-value">{{ __($invoice->payment_method?->label() ?? '—') }}</div>
            </div>
            <div class="meta-item">
                <div class="meta-label">{{ __('Status') }}</div>
                <div class="meta-value">
                    <span class="badge {{ $statusBadgeClass }}">
                        {{ __($invoice->payment_status?->label() ?? '—') }}
                    </span>
                </div>
            </div>
        </div>

        {{-- ── Bill to ──────────────────────────────────── --}}
        <div class="section">
            <div class="section-title">{{ __('Bill To') }}</div>
            <div class="info-card" style="max-width: 320px;">
                <strong>{{ $invoice->invocable?->name }}</strong>
                <div class="sub">
                    @if($invoice->invocable?->address)
                        {{ $invoice->invocable->address }}<br>
                    @endif
                    @if($invoice->invocable?->phone_number)
                        {{ $invoice->invocable->phone_number }}
                    @endif
                </div>
            </div>
        </div>

        {{-- ── Items table ──────────────────────────────── --}}
        <div class="section" style="padding-top: 0;">
            <div class="section-title">{{ __('Items') }}</div>
            <table class="table-items">
                <thead>
                    <tr>
                        <th style="width: 28px;">#</th>
                        <th>{{ __('Product') }}</th>
                        <th>{{ __('Qty') }}</th>
                        <th>{{ __('Unit') }}</th>
                        <th>{{ __('Unit Price') }}</th>
                        <th>{{ __('Total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($deliveredRecords as $index => $record)
                        <tr>
                            <td class="text-gray-400">{{ $index + 1 }}</td>
                            <td>
                                <span class="font-semibold">{{ $record->product->name }}</span>
                                @if($record->description)
                                    <br><span class="text-xs text-gray-500">{{ $record->description }}</span>
                                @endif
                            </td>
                            <td class="no-wrap">{{ $record->quantity }}</td>
                            <td class="text-gray-500">{{ $record->unit?->name ?? __('Unit') }}</td>
                            <td class="no-wrap">{{ number_format($record->price, 2) }} {{ $currency }}</td>
                            <td class="no-wrap font-semibold text-emerald">
                                {{ number_format($record->price * $record->quantity, 2) }} {{ $currency }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>

                {{-- Remaining / pending rows --}}
                @if(count($remainingRecords))
                    <tbody>
                        <tr>
                            <td colspan="6" class="text-center text-gray-500 font-semibold bg-gray-100"
                                style="border-top: 2px solid #e5e7eb; padding: 8px;">
                                ── {{ __('Pending Delivery') }} ──
                            </td>
                        </tr>
                        @foreach($remainingRecords as $index => $record)
                            <tr style="opacity: 0.65;">
                                <td class="text-gray-400">{{ $index + 1 }}</td>
                                <td>{{ $record->product->name }}</td>
                                <td>{{ $record->quantity }}</td>
                                <td class="text-gray-500">{{ $record->unit?->name ?? __('Unit') }}</td>
                                <td class="no-wrap">{{ number_format($record->price, 2) }} {{ $currency }}</td>
                                <td class="no-wrap">{{ number_format($record->price * $record->quantity, 2) }} {{ $currency }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                @endif
            </table>
        </div>

        {{-- ── Summary ──────────────────────────────────── --}}
        <div class="section row items-end" style="padding-top: 0;">
            <div class="col">
                @if($invoice->payments->count())
                    <div class="section-title">{{ __('Payment History') }}</div>
                    <table class="table-items" style="max-width: 340px;">
                        <thead>
                            <tr>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Method') }}</th>
                                <th>{{ __('Amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoice->payments as $payment)
                                <tr>
                                    <td class="no-wrap text-gray-500">{{ $payment->paid_at->format('Y-m-d') }}</td>
                                    <td>{{ __($payment->payment_method?->label() ?? '—') }}</td>
                                    <td class="no-wrap font-semibold text-emerald">
                                        {{ number_format($payment->amount, 2) }} {{ $currency }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <div class="col-auto">
                <div class="summary-box">
                    <div class="summary-row">
                        <span class="text-gray-500">{{ __('Subtotal') }}</span>
                        <span class="no-wrap">{{ number_format($subtotal, 2) }} {{ $currency }}</span>
                    </div>
                    @if($discount > 0)
                        <div class="summary-row discount">
                            <span class="text-gray-500">{{ __('Discount') }}</span>
                            <span class="val no-wrap">− {{ number_format($discount, 2) }} {{ $currency }}</span>
                        </div>
                    @endif
                    <div class="summary-row total">
                        <span>{{ __('Total') }}</span>
                        <span class="no-wrap">{{ number_format($total, 2) }} {{ $currency }}</span>
                    </div>
                    @if($paid > 0)
                        <div class="summary-row">
                            <span class="text-gray-500">{{ __('Paid') }}</span>
                            <span class="no-wrap">{{ number_format($paid, 2) }} {{ $currency }}</span>
                        </div>
                    @endif
                    @if($balance > 0)
                        <div class="summary-row balance">
                            <span>{{ __('Balance Due') }}</span>
                            <span class="no-wrap">{{ number_format($balance, 2) }} {{ $currency }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ── Footer ───────────────────────────────────── --}}
        <div class="print-footer">
            <div>
                <p class="text-gray-500 text-xs">{{ __('Thank you for your business') }}</p>
            </div>
            <div class="qr-wrap">{!! $qr !!}</div>
        </div>

    </div>
</x-print>
