<x-print>
    <div class="p-4">
        <header>
            <div class="flex items-center justify-center mb-4">
                <img class="img-rounded" height="70px" src="{{ preference('logo', '/imgs/logo.svg') }}">
            </div>
            <div>
                <b>{{__("Date")}}: </b> {{ $invoice->created_at }}<br />
                <b>{{__("Invoice")}} # </b> {{ $invoice->serial_number }}
                <br />
            </div>
            <br />
        </header>
        <h2 class="font-bold text-lg">{{__('Invoice Details')}}</h2>
        <div class="bg-white border-2 border-dashed rounded-lg mt-4">
            <div class="p-2 md:flex rtl:flex-row-reverse md:items-center md:justify-between ">
                <div class="flex rtl:flex-row-reverse items-center gap-x-4 justify-between">
                    <h3>{{$invoice->invocable->name}}</h3>
                    <div class="flex rtl:flex-row-reverse space-x-2">
                        <label for="totalCost" class="text-sm font-medium text-gray-600">
                            {{__('Total Cost')}}
                        </label>
                        <h2 class="text-base font-semibold text-gray-800 sm:text-lg px-4">{{$invoice->total . ' SDG'}}</h2>
                    </div>
                </div>
            </div>

            <div class="flex flex-col">
                <div class="-my-2 overflow-x-auto md:-mx-6 lg:-mx-8">
                    <div
                        class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8"
                    >
                        <div
                            class="overflow-hidden border border-gray-200 rounded-b-lg dark:border-gray-700"
                        >
                            <table
                                class="min-w-full divide-y divide-gray-200 dark:divide-gray-700"
                            >
                                <thead class="bg-gray-100">
                                <tr>
                                    <th
                                        scope="col"
                                        class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400"
                                    >
                                        {{__('The Product')}}
                                    </th>

                                    <th
                                        scope="col"
                                        class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400"
                                    >
                                        {{__('Quantity')}}
                                    </th>

                                    <th
                                        scope="col"
                                        class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400"
                                    >
                                        {{__('Price')}}
                                    </th>

                                    <th
                                        scope="col"
                                        class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400"
                                    >
                                        {{__('Total Price')}}
                                    </th>
                                </tr>
                                </thead>

                                <tbody
                                    class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-900"
                                >
                                @foreach($deliveredRecords as $record)
                                    <tr
                                        class="{{count($remainingRecords) ? 'bg-gray-100' : ''}}"
                                    >
                                        <td
                                            class="px-8 py-3 text-sm text-left rtl:text-right text-gray-800 whitespace-nowrap"
                                        >
                                            {{$record->product->name}}
                                        </td>

                                        <th
                                            class="px-8 py-3 text-sm text-left rtl:text-right text-gray-800 whitespace-nowrap"
                                        >
                                            {{$record->quantity}}
                                        </th>

                                        <th
                                            class="px-8 py-3 text-sm text-left rtl:text-right text-emerald-500 whitespace-nowrap"

                                        >
                                            {{$record->price}}
                                        </th>

                                        <td
                                            class="px-8 py-3 text-sm font-semibold text-left rtl:text-right text-emerald-500 whitespace-nowrap"

                                        >
                                            {{$record->price * $record->quantity}}
                                        </td>
                                    </tr>
                                @endforeach
                                @if(count($remainingRecords))
                                    @if(count($deliveredRecords))
                                        <tr
                                        >
                                            <td
                                                colspan="4"
                                                class="py-2  text-center text-gray-500 font-semibold bg-gray-100 border-t border-b"
                                            >
                                                {{__('Invoice Remaining')}}
                                            </td>
                                        </tr>
                                    @endif
                                    @foreach($remainingRecords as $record)
                                        <tr
                                        >
                                            <td
                                                class="px-8 py-3 text-sm text-left rtl:text-right text-gray-800 whitespace-nowrap"

                                            >{{$record->product->name}}</td>

                                            <th
                                                class="px-8 py-3 text-sm text-left rtl:text-right text-gray-800 whitespace-nowrap"

                                            >{{$record->quantity}}</th>

                                            <th
                                                class="px-8 py-3 text-sm text-left rtl:text-right text-emerald-500 whitespace-nowrap"

                                            >{{ $record->price}}</th>

                                            <td
                                                class="px-8 py-3 text-sm font-semibold text-left rtl:text-right text-emerald-500 whitespace-nowrap"
                                                v-text="totalPrice(record)"
                                            ></td>
                                        </tr>
                                    @endforeach

                                @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-4 flex justify-end">
            {!! $qr !!}
        </div>
    </div>
</x-print>
