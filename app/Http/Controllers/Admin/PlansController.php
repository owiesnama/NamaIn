<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\LogAdminAction;
use App\Features\Feature;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PlanRequest;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Response;

class PlansController extends Controller
{
    public function __construct(private readonly LogAdminAction $logger) {}

    public function index(): Response
    {
        $plans = Plan::withCount('subscriptions')
            ->with('planFeatures')
            ->orderBy('sort')
            ->get()
            ->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'key' => $plan->key,
                'name' => $plan->name,
                'display_name' => $plan->displayName(),
                'is_active' => $plan->is_active,
                'is_default' => $plan->is_default,
                'sort' => $plan->sort,
                'subscriptions_count' => $plan->subscriptions_count,
                'features_count' => $this->grantedFeatureCount($plan),
            ]);

        return inertia('Admin/Plans/Index', [
            'plans' => $plans,
            'catalog' => $this->catalog(),
        ]);
    }

    public function create(): Response
    {
        return inertia('Admin/Plans/Edit', [
            'plan' => null,
            'catalog' => $this->catalog(),
        ]);
    }

    public function store(PlanRequest $request): RedirectResponse
    {
        $plan = DB::transaction(function () use ($request) {
            $this->clearOtherDefaults($request->boolean('is_default'));
            $plan = Plan::create($this->attributes($request));
            $this->syncFeatures($plan, (array) $request->input('features', []));

            return $plan;
        });

        $this->logger->handle($request->user()->id, 'plan.created', $plan, ['key' => $plan->key]);

        return redirect()->route('admin.plans.index')->with('success', __('Plan created successfully.'));
    }

    public function edit(Plan $plan): Response
    {
        return inertia('Admin/Plans/Edit', [
            'plan' => [
                'id' => $plan->id,
                'key' => $plan->key,
                'name' => $plan->name,
                'description' => $plan->description,
                'is_active' => $plan->is_active,
                'is_default' => $plan->is_default,
                'sort' => $plan->sort,
                'features' => $plan->planFeatures->pluck('value', 'feature_key'),
            ],
            'catalog' => $this->catalog(),
        ]);
    }

    public function update(PlanRequest $request, Plan $plan): RedirectResponse
    {
        DB::transaction(function () use ($request, $plan) {
            $this->clearOtherDefaults($request->boolean('is_default'), $plan->id);
            $plan->update($this->attributes($request));
            $this->syncFeatures($plan, (array) $request->input('features', []));
        });

        $this->logger->handle($request->user()->id, 'plan.updated', $plan, ['key' => $plan->key]);

        return redirect()->route('admin.plans.index')->with('success', __('Plan updated successfully.'));
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        if ($plan->subscriptions()->exists()) {
            return back()->with('error', __('This plan has subscriptions and cannot be deleted.'));
        }

        $key = $plan->key;
        $plan->delete();

        $this->logger->handle(request()->user()->id, 'plan.deleted', null, ['key' => $key]);

        return redirect()->route('admin.plans.index')->with('success', __('Plan deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(PlanRequest $request): array
    {
        return [
            'key' => $request->input('key'),
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'is_active' => $request->boolean('is_active'),
            'is_default' => $request->boolean('is_default'),
            'sort' => (int) $request->input('sort', 0),
        ];
    }

    /**
     * Replace a plan's feature values, coercing each to the feature's type.
     *
     * @param  array<string, mixed>  $features
     */
    private function syncFeatures(Plan $plan, array $features): void
    {
        $keptKeys = [];

        foreach ($features as $key => $value) {
            $feature = Feature::tryFrom((string) $key);

            if (! $feature) {
                continue;
            }

            $coerced = $feature->isBoolean()
                ? (bool) $value
                : ($value === null || $value === '' ? null : (int) $value);

            $plan->planFeatures()->updateOrCreate(['feature_key' => $feature->value], ['value' => $coerced]);
            $keptKeys[] = $feature->value;
        }

        $plan->planFeatures()->whereNotIn('feature_key', $keptKeys)->delete();
    }

    /**
     * Clear the default flag on other plans so the single-default DB constraint
     * is never violated when this plan is marked default.
     */
    private function clearOtherDefaults(bool $makingDefault, ?int $exceptId = null): void
    {
        if (! $makingDefault) {
            return;
        }

        Plan::when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    /**
     * How many features a plan actually grants: boolean capabilities that are
     * on, plus limit quotas that are unlimited (null) or a positive number.
     * A boolean that is off or a limit of 0 (deny) does not count.
     */
    private function grantedFeatureCount(Plan $plan): int
    {
        return $plan->planFeatures->filter(function ($planFeature): bool {
            $feature = Feature::tryFrom($planFeature->feature_key);

            if (! $feature) {
                return false;
            }

            return $feature->isBoolean()
                ? $planFeature->value === true
                : ($planFeature->value === null || (int) $planFeature->value > 0);
        })->count();
    }

    /**
     * The feature catalog for the plan editor UI.
     *
     * @return array<int, array<string, string>>
     */
    private function catalog(): array
    {
        return array_map(fn (Feature $feature) => [
            'key' => $feature->value,
            'type' => $feature->type()->value,
            'group' => $feature->group(),
            'label' => __($feature->labelKey()),
        ], Feature::cases());
    }
}
