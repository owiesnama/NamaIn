 <x-print>
     <header>
         <div style="position:absolute; left:0pt; width:250pt;">
             <img class="img-rounded" height="70px" src="{{ preference('logo', '/imgs/logo.svg') }}">
         </div>
         <div style="margin-left:300pt;">
             <b>Date: </b> {{ $invoice->created_at }}<br />
             <b>Invoice #: </b> {{ $invoice->serial_number }}
             <br />
         </div>
         <br />
         <h2>{{ $invoice->serial_number ? '#' . $invoice->serial_number : '' }}</h2>
     </header>
     <h4>Items:</h4>
     <table class="table table-bordered">
         <thead>
             <tr>
                 <th>#</th>
                 <th>{{ __('Name') }}</th>
                 <th>{{ __('Price') }}</th>
                 <th>{{ __('Amount') }}</th>
                 <th>{{ __('Total') }}</th>
             </tr>
         </thead>
         <tbody>
             @foreach ($invoice->transactions as $transaction)
                 <tr>
                     <td>{{ $loop->iteration }}</td>
                     <td>{{ $transaction->product->name }}</td>
                     <td>{{ $transaction->price }}</td>
                     <td>{{ $transaction->normalizedQuantityHTML() }}</td>
                     <td>{{ $transaction->total() }}</td>
                 </tr>
             @endforeach
         </tbody>
     </table>
 </x-print>
