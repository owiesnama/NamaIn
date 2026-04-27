<?php

namespace App\Http\Controllers\Payments;

use App\Actions\Treasury\RecordTreasuryMovementAction;
use App\Enums\ChequeStatus;
use App\Enums\ChequeType;
use App\Enums\TreasuryAccountType;
use App\Enums\TreasuryMovementReason;
use App\Filters\ChequeFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChequeRequest;
use App\Models\Bank;
use App\Models\Cheque;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\TreasuryAccount;
use App\Queries\ChequeIndexQuery;
use Illuminate\Support\Facades\DB;

class ChequesController extends Controller
{
    public function index(ChequeFilter $filter, ChequeIndexQuery $query)
    {
        $this->authorize('viewAny', Cheque::class);

        return inertia('Cheques/Index', [
            'initialCheques' => Cheque::with('payee')
                ->filter($filter)
                ->when(request('sort_by'), function ($query, $sortBy) {
                    $query->orderBy(in_array($sortBy, ['id', 'amount', 'due', 'reference_number', 'created_at']) ? $sortBy : 'due', request('sort_order', 'asc'));
                }, function ($query) {
                    $query->orderBy('type')
                        ->oldest('due')
                        ->orderBy('created_at');
                })
                ->paginate(10)
                ->withQueryString(),
            'status' => ChequeStatus::casesWithLabels(),
            'bank_treasury_accounts' => TreasuryAccount::ofType(TreasuryAccountType::Bank)->active()->get(['id', 'name', 'bank_id']),
            'summary' => $query->summary(),
        ]);
    }

    public function create()
    {
        return inertia('Cheques/Create', [
            'payees' => $this->getPayees(),
            'banks' => Bank::orderBy('name')->get(),
        ]);
    }

    private function getPayees()
    {
        $customers = Customer::select('id', 'name')->orderBy('name')->get()->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'type' => Customer::class,
            'type_string' => 'Customer',
        ]);

        $suppliers = Supplier::select('id', 'name')->orderBy('name')->get()->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'type' => Supplier::class,
            'type_string' => 'Supplier',
        ]);

        return $customers->concat($suppliers);
    }

    public function store(ChequeRequest $request, RecordTreasuryMovementAction $recordMovement)
    {
        $this->authorize('create', Cheque::class);

        $validated = $request->validated();

        if (ChequeType::from((int) $validated['type']) === ChequeType::Receivable) {
            $clearingAccount = TreasuryAccount::ofType(TreasuryAccountType::ChequeClearing)->active()->first();

            if (! $clearingAccount) {
                return redirect()->route('treasury.create')
                    ->with('error', __('A Cheque Clearing treasury account is required before registering receivable cheques. Please create one first.'));
            }
        }

        DB::transaction(function () use ($validated, $recordMovement) {
            $cheque = Cheque::forPayee($validated['payee_id'], $validated['payee_type'])
                ->register([
                    'type' => $validated['type'],
                    'amount' => $validated['amount'],
                    'bank_id' => $this->resolveBankId($validated['bank_id']),
                    'due' => $validated['due'],
                    'reference_number' => $validated['reference_number'],
                    'invoice_id' => $validated['invoice_id'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                ]);

            if (ChequeType::from((int) $validated['type']) === ChequeType::Receivable) {
                $clearingAccount = TreasuryAccount::ofType(TreasuryAccountType::ChequeClearing)->active()->first();

                $recordMovement->handle(
                    account: $clearingAccount,
                    amount: (int) round($validated['amount'] * 100),
                    reason: TreasuryMovementReason::ChequeDeposited,
                    movable: $cheque,
                    actor: auth()->user(),
                );
            }
        });

        return redirect()->route('cheques.index')->with('success', __('New cheque registered successfully'));
    }

    public function edit(Cheque $cheque)
    {
        if (! $cheque->isEditable()) {
            return redirect()->route('cheques.index')->with('error', 'This cheque can only be modified while in Drafted status.');
        }

        return inertia('Cheques/Edit', [
            'cheque' => $cheque,
            'payees' => $this->getPayees(),
            'banks' => Bank::orderBy('name')->get(),
            'invoices' => $this->getPayeeInvoices($cheque->chequeable_id, $cheque->chequeable_type),
        ]);
    }

    public function update(Cheque $cheque, ChequeRequest $request)
    {
        if (! $cheque->isEditable()) {
            abort(403, 'This cheque can only be modified while in Drafted status.');
        }

        $validated = $request->validated();

        $cheque->update([
            'chequeable_id' => $validated['payee_id'],
            'chequeable_type' => $validated['payee_type'],
            'invoice_id' => $validated['invoice_id'] ?? null,
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'bank_id' => $this->resolveBankId($validated['bank_id']),
            'due' => $validated['due'],
            'reference_number' => $validated['reference_number'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('cheques.index')->with('success', __('Cheque updated successfully'));
    }

    private function getPayeeInvoices($id, $type)
    {
        $model = ($type === 'Customer' || $type === Customer::class) ? Customer::find($id) : Supplier::find($id);

        if (! $model) {
            return [];
        }

        return $model->invoices()
            ->outstanding()
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'serial_number' => $i->serial_number,
                'remaining_balance' => $i->remaining_balance,
                'total' => $i->total - $i->discount,
            ]);
    }

    private function resolveBankId(int|string $bankId): int
    {
        if (is_numeric($bankId)) {
            return (int) $bankId;
        }

        return Bank::firstOrCreate(['name' => trim($bankId)])->id;
    }

    public function destroy(Cheque $cheque)
    {
        $this->authorize('delete', $cheque);

        if ($cheque->status !== ChequeStatus::Drafted && $cheque->status !== ChequeStatus::Cancelled) {
            return redirect()->route('cheques.index')->with('error', 'Only Drafted or Cancelled cheques can be deleted.');
        }

        $cheque->delete();

        return redirect()->route('cheques.index')->with('success', __('Cheque deleted successfully'));
    }
}
