<?php

namespace App\Http\Controllers\Reconciliation;

use App\Actions\Reconciliation\ResolveReconciliationItemAction;
use App\Enums\ReconciliationType;
use App\Enums\ResolutionKind;
use App\Enums\TreasuryAccountType;
use App\Http\Controllers\Controller;
use App\Models\ReconciliationItem;
use App\Models\Storage;
use App\Models\TreasuryAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * The reconciliation inbox (Design 04 §2). Lists divergences with type/status/
 * device filters, shows one item with its linked records, and resolves it by
 * dispatching to the type-specific action. Permission-gated via the policy.
 */
class ReconciliationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ReconciliationItem::class);

        $status = $request->string('status', 'open')->value();

        $items = ReconciliationItem::query()
            ->with(['device', 'register', 'actor'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')->value()))
            ->when($request->filled('device'), fn ($query) => $query->where('device_id', $request->integer('device')))
            ->latest('detected_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (ReconciliationItem $item): array => $this->summarize($item));

        return inertia('Reconciliation/Index', [
            'items' => $items,
            'filters' => [
                'status' => $status,
                'type' => $request->string('type')->value() ?: null,
                'device' => $request->integer('device') ?: null,
            ],
            'openCount' => ReconciliationItem::open()->count(),
            'openCountsByType' => ReconciliationItem::open()
                ->selectRaw('type, count(*) as total')
                ->groupBy('type')
                ->pluck('total', 'type'),
            'types' => collect(ReconciliationType::cases())->map(fn (ReconciliationType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ]),
        ]);
    }

    public function show(ReconciliationItem $reconciliation): Response
    {
        $this->authorize('view', $reconciliation);

        $reconciliation->load(['device', 'register', 'actor', 'resolvedBy', 'subject']);

        return inertia('Reconciliation/Show', [
            'item' => array_merge($this->summarize($reconciliation), [
                'detail' => $this->detail($reconciliation),
                'resolutions' => collect($reconciliation->type->resolutions())->map(fn (ResolutionKind $kind): array => [
                    'value' => $kind->value,
                    'label' => $kind->label(),
                ]),
                'resolution' => $reconciliation->resolution?->value,
                'resolution_note' => $reconciliation->resolution_note,
                'resolved_by' => $reconciliation->resolvedBy?->name,
                'resolved_at' => $reconciliation->resolved_at?->toIso8601String(),
            ]),
            'options' => $this->options($reconciliation),
        ]);
    }

    public function resolve(Request $request, ReconciliationItem $reconciliation, ResolveReconciliationItemAction $action): RedirectResponse
    {
        $this->authorize('resolve', $reconciliation);

        abort_if(! $reconciliation->isOpen(), 422, __('This item is already resolved.'));

        $validated = $request->validate([
            'resolution' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:1000'],
            'counted_qty' => ['nullable', 'integer'],
            'from_storage_id' => ['nullable', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string'],
            'treasury_account_id' => ['nullable', 'integer'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'new_balance' => ['nullable', 'integer'],
        ]);

        $resolution = ResolutionKind::tryFrom($validated['resolution']);

        abort_if(
            $resolution === null || ! in_array($resolution, $reconciliation->type->resolutions(), true),
            422,
            __('That resolution is not available for this item.'),
        );

        $action->handle($reconciliation, $resolution, $request->user(), $validated);

        return redirect()
            ->route('reconciliation.index')
            ->with('success', __('Reconciliation item resolved.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function summarize(ReconciliationItem $item): array
    {
        return [
            'id' => $item->id,
            'public_id' => $item->public_id,
            'type' => $item->type->value,
            'type_label' => $item->type->label(),
            'status' => $item->status,
            'device' => $item->device?->name,
            'register' => $item->register?->code,
            'actor' => $item->actor?->name,
            'occurred_at' => $item->occurred_at?->toIso8601String(),
            'detected_at' => $item->detected_at?->toIso8601String(),
        ];
    }

    /**
     * The selectable resolution inputs (storages to transfer from, cash accounts
     * to collect into) the type needs, kept minimal per item type.
     *
     * @return array<string, mixed>
     */
    private function options(ReconciliationItem $item): array
    {
        return match ($item->type) {
            ReconciliationType::Oversell => [
                'storages' => Storage::query()
                    ->where('id', '!=', $item->subject->storage_id)
                    ->get(['id', 'name'])
                    ->map(fn ($storage): array => ['id' => $storage->id, 'name' => $storage->name]),
            ],
            ReconciliationType::CreditBreach => [
                'treasury_accounts' => TreasuryAccount::query()
                    ->ofType(TreasuryAccountType::Cash)
                    ->get(['id', 'name'])
                    ->map(fn ($account): array => ['id' => $account->id, 'name' => $account->name]),
            ],
            default => [],
        };
    }

    /**
     * The type-specific linked records shown on the detail page.
     *
     * @return array<string, mixed>
     */
    private function detail(ReconciliationItem $item): array
    {
        $subject = $item->subject;

        return match ($item->type) {
            ReconciliationType::Oversell => [
                'product' => $subject->product?->name,
                'storage' => $subject->storage?->name,
                'storage_id' => $subject->storage_id,
                'oversold_qty' => $subject->oversold_qty,
                'on_hand_before' => $subject->on_hand_before,
                'current_on_hand' => $subject->storage?->quantityOf($subject->product_id),
                'invoice' => $subject->invoice?->serial_number,
            ],
            ReconciliationType::CreditBreach => [
                'customer' => $subject->customer?->name,
                'credit_limit' => $subject->credit_limit,
                'balance_after' => $subject->balance_after,
                'invoice' => $subject->invoice?->serial_number,
            ],
            ReconciliationType::SessionVariance => [
                'expected_amount' => $subject->expected_amount,
                'declared_amount' => $subject->declared_amount,
                'variance_amount' => $subject->variance_amount,
                'drawer' => $subject->drawer?->name,
            ],
            ReconciliationType::ParkedMutation => [
                'mutation_type' => $subject->mutation_type,
                'rejection_reason' => $subject->rejection_reason?->value,
                'rejection_message' => $subject->rejection_message,
                'envelope' => $subject->envelope,
            ],
        };
    }
}
