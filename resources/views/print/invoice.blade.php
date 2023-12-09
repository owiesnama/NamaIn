 <x-print>
     <div>
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="text-left">Product</th>
                        <th class="text-left">Quantity</th>
                        <th class="text-left">Price</th>
                        <th class="text-left">Total Price</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice->transactions as $record)
                        <tr>
                            <td>{{ $record->product->name }}</td>
                            <td>{!! $record->normalizedQuantity() !!}</td>
                            <td>{{ $record->price }}</td>
                            <td>{{ $record->total() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
 </x-print>
