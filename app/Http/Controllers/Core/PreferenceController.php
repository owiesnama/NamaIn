<?php

namespace App\Http\Controllers\Core;

use App\Actions\UpdatePreferences;
use App\Enums\TreasuryAccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\PreferenceRequest;
use App\Models\Storage;
use App\Models\TreasuryAccount;
use Illuminate\Support\Collection;
use Inertia\Response;

class PreferenceController extends Controller
{
    public function index(): Response
    {
        abort_unless(auth()->user()->hasRole('owner', 'admin'), 403);

        // Deliberately passes no 'preferences' prop: HandleInertiaRequests already
        // shares one, with the logo path resolved to a URL. A page prop of the same
        // name overrides the shared one, which would hand the raw disk path back to
        // ApplicationLogo and 404 the sidebar logo on this page only.
        return inertia('Preferences/Show', [
            'cash_accounts' => $this->accountOptions(TreasuryAccountType::Cash),
            'bank_accounts' => $this->accountOptions(TreasuryAccountType::Bank),
            'sale_points' => Storage::salePoints()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Storage $salePoint) => [
                    'id' => $salePoint->id,
                    'name' => $salePoint->name,
                ]),
        ]);
    }

    /**
     * Active treasury accounts of the given type, shaped for a settings select.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    private function accountOptions(TreasuryAccountType $type): Collection
    {
        return TreasuryAccount::active()
            ->ofType($type)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (TreasuryAccount $account) => [
                'id' => $account->id,
                'name' => $account->name,
            ]);
    }

    public function update(PreferenceRequest $request, UpdatePreferences $action)
    {
        abort_unless(auth()->user()->hasRole('owner', 'admin'), 403);
        $action->handle($request);

        return back()->with('success', __('Settings updated successfully'));
    }
}
