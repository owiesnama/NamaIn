<?php

namespace App\Http\Controllers\Treasury;

use App\Enums\TreasuryAccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\TreasuryAccountRequest;
use App\Models\Bank;
use App\Models\Storage;
use App\Models\TreasuryAccount;
use App\Models\TreasuryMovement;

class TreasuryAccountsController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', TreasuryAccount::class);

        $accounts = TreasuryAccount::with(['storage', 'bank'])
            ->active()
            ->withSum('movements', 'amount')
            ->addSelect([
                'last_movement_at' => TreasuryMovement::query()
                    ->whereColumn('treasury_account_id', 'treasury_accounts.id')
                    ->latest('occurred_at')
                    ->select('occurred_at')
                    ->limit(1),
            ])
            ->get();

        $cashDrawers = $accounts
            ->where('type', TreasuryAccountType::Cash)
            ->whereNotNull('sale_point_id')
            ->values()
            ->map(fn (TreasuryAccount $account) => $this->formatAccount($account));

        $sharedAccounts = $accounts
            ->filter(fn (TreasuryAccount $account) => $account->sale_point_id === null)
            ->values()
            ->map(fn (TreasuryAccount $account) => $this->formatAccount($account));

        $activeTypes = $accounts->pluck('type')->map(fn ($t) => $t->value)->unique()->values()->all();
        $requiredTypes = [
            TreasuryAccountType::Cash->value,
            TreasuryAccountType::Bank->value,
            TreasuryAccountType::ChequeClearing->value,
        ];
        $missingTypes = array_values(array_filter($requiredTypes, fn ($t) => ! in_array($t, $activeTypes)));

        return inertia('Treasury/Index', [
            'cash_drawers' => $cashDrawers,
            'shared_accounts' => $sharedAccounts,
            'missing_types' => $missingTypes,
        ]);
    }

    public function create()
    {
        $this->authorize('create', TreasuryAccount::class);

        return inertia('Treasury/Create', [
            'account_types' => TreasuryAccountType::casesWithLabels(),
            'sale_points' => Storage::select('id', 'name')->get(),
            'banks' => Bank::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(TreasuryAccountRequest $request)
    {
        $this->authorize('create', TreasuryAccount::class);

        TreasuryAccount::create($request->validated());

        return redirect()->route('treasury.index')->with('success', 'Treasury account created successfully.');
    }

    public function show(TreasuryAccount $treasury)
    {
        $this->authorize('view', TreasuryAccount::class);

        $movements = $treasury->movements()
            ->with('createdBy', 'movable')
            ->latest('occurred_at')
            ->paginate(20)
            ->withQueryString();

        return inertia('Treasury/Show', [
            'account' => $this->formatAccount($treasury),
            'movements' => $movements,
        ]);
    }

    public function edit(TreasuryAccount $treasury)
    {
        $this->authorize('update', TreasuryAccount::class);

        return inertia('Treasury/Edit', [
            'account' => $treasury->load('bank'),
            'account_types' => TreasuryAccountType::casesWithLabels(),
            'sale_points' => Storage::select('id', 'name')->get(),
            'banks' => Bank::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(TreasuryAccountRequest $request, TreasuryAccount $treasury)
    {
        $this->authorize('update', TreasuryAccount::class);

        $fields = ['name', 'notes'];

        if ($treasury->type === TreasuryAccountType::Bank) {
            $fields[] = 'bank_id';
        }

        $treasury->update($request->only($fields));

        return redirect()->route('treasury.show', $treasury)->with('success', 'Account updated successfully.');
    }

    private function formatAccount(TreasuryAccount $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'type' => $account->type->value,
            'type_label' => $account->type->label(),
            'currency' => $account->currency,
            'current_balance' => $account->opening_balance + (int) ($account->movements_sum_amount ?? 0),
            'opening_balance' => $account->opening_balance,
            'is_active' => $account->is_active,
            'notes' => $account->notes,
            'sale_point_id' => $account->sale_point_id,
            'sale_point_name' => $account->storage?->name,
            'bank_id' => $account->bank_id,
            'bank_name' => $account->bank?->name,
            'last_movement_at' => $account->last_movement_at,
        ];
    }
}
