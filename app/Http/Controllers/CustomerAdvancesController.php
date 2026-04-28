<?php

namespace App\Http\Controllers;

use App\Actions\RecordCustomerAdvanceAction;
use App\Actions\SettleCustomerAdvanceAction;
use App\Exceptions\OverSettlementException;
use App\Http\Requests\RecordCustomerAdvanceRequest;
use App\Http\Requests\SettleCustomerAdvanceRequest;
use App\Models\Customer;
use App\Models\CustomerAdvance;
use App\Models\Invoice;
use App\Models\TreasuryAccount;
use Illuminate\Http\RedirectResponse;

class CustomerAdvancesController extends Controller
{
    /**
     * Record a new advance for a customer.
     */
    public function store(
        Customer $customer,
        RecordCustomerAdvanceRequest $request,
        RecordCustomerAdvanceAction $action,
    ): RedirectResponse {
        $treasury = TreasuryAccount::findOrFail($request->validated('treasury_account_id'));

        $action->handle(
            customer: $customer,
            amount: (float) $request->validated('amount'),
            treasury: $treasury,
            actor: $request->user(),
            notes: $request->validated('notes'),
            givenAt: $request->validated('given_at') ? new \DateTime($request->validated('given_at')) : null,
        );

        return back()->with('success', __('Customer advance recorded successfully.'));
    }

    /**
     * Settle an existing advance — direct repayment or invoice offset.
     */
    public function destroy(
        CustomerAdvance $customerAdvance,
        SettleCustomerAdvanceRequest $request,
        SettleCustomerAdvanceAction $action,
    ): RedirectResponse {
        $treasury = TreasuryAccount::findOrFail($request->validated('treasury_account_id'));
        $invoice = $request->validated('invoice_id')
            ? Invoice::findOrFail($request->validated('invoice_id'))
            : null;

        try {
            $action->handle(
                advance: $customerAdvance,
                amount: (float) $request->validated('amount'),
                treasury: $treasury,
                actor: $request->user(),
                invoice: $invoice,
                notes: $request->validated('notes'),
            );
        } catch (OverSettlementException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('success', __('Advance settlement recorded successfully.'));
    }
}
