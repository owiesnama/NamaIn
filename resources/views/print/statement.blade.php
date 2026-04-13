<x-print>
    <div class="p-4">
        <header>
            <div class="flex items-center justify-center mb-4">
                <img class="img-rounded" height="30px" style="max-width: 120px; object-fit: contain;" src="{{ preference('logo', '/images/logo.svg') }}">
            </div>
            <div class="flex justify-between items-start">
                <div>
                    <b>{{__("Date")}}: </b> {{ now() }}<br />
                    <b>{{__("Statement")}}: </b> {{ $customer->name }}
                </div>
                <div class="text-right">
                    <b>{{__("Period")}}: </b> {{ $start_date }} {{__("to")}} {{ $end_date }}
                </div>
            </div>
            <br />
        </header>

        <h2 class="font-bold text-lg mb-4">{{__('Customer Statement')}}</h2>

        <div class="bg-white border border-gray-200 rounded-lg">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-2 text-left rtl:text-right">{{__('Date')}}</th>
                        <th class="px-4 py-2 text-left rtl:text-right">{{__('Description')}}</th>
                        <th class="px-4 py-2 text-left rtl:text-right">{{__('Debit')}}</th>
                        <th class="px-4 py-2 text-left rtl:text-right">{{__('Credit')}}</th>
                        <th class="px-4 py-2 text-left rtl:text-right">{{__('Balance')}}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr>
                        <td class="px-4 py-2">{{ $start_date }}</td>
                        <td class="px-4 py-2 font-bold">{{__('Opening Balance')}}</td>
                        <td class="px-4 py-2"></td>
                        <td class="px-4 py-2"></td>
                        <td class="px-4 py-2 font-bold text-gray-800">{{ number_format($opening_balance, 2) . ' ' . preference('currency', 'USD') }}</td>
                    </tr>
                    @php
                        $runningBalance = $opening_balance;
                        $activities = collect();
                        foreach($invoices as $invoice) {
                            $activities->push([
                                'date' => $invoice->created_at,
                                'description' => __('Invoice') . ' #' . ($invoice->serial_number ?: $invoice->id),
                                'debit' => $invoice->total,
                                'credit' => 0,
                                'type' => 'invoice'
                            ]);
                        }
                        foreach($payments as $payment) {
                            $activities->push([
                                'date' => $payment->paid_at,
                                'description' => __('Payment') . ' - ' . __($payment->payment_method->label()),
                                'debit' => 0,
                                'credit' => $payment->amount,
                                'type' => 'payment'
                            ]);
                        }
                        $activities = $activities->sortBy('date');
                    @endphp

                    @foreach($activities as $activity)
                        @php
                            $runningBalance += $activity['debit'];
                            $runningBalance -= $activity['credit'];
                        @endphp
                        <tr>
                            <td class="px-4 py-2">{{ $activity['date'] }}</td>
                            <td class="px-4 py-2">{{ $activity['description'] }}</td>
                            <td class="px-4 py-2 text-red-600">{{ $activity['debit'] > 0 ? number_format($activity['debit'], 2) : '' }}</td>
                            <td class="px-4 py-2 text-emerald-600">{{ $activity['credit'] > 0 ? number_format($activity['credit'], 2) : '' }}</td>
                            <td class="px-4 py-2 font-semibold">{{ number_format($runningBalance, 2) . ' ' . preference('currency', 'USD') }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr>
                        <td colspan="4" class="px-4 py-2 text-right rtl:text-left font-bold">{{__('Closing Balance')}}</td>
                        <td class="px-4 py-2 font-bold text-gray-800">{{ number_format($runningBalance, 2) . ' ' . preference('currency', 'USD') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="mt-8 flex justify-end">
            {!! $qr !!}
        </div>
    </div>
</x-print>
