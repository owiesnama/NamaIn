<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { Link, useForm } from "@inertiajs/vue3";
import { computed } from "vue";
import { usePermissions } from "@/Composables/usePermissions";
import { useCurrency } from "@/Composables/useCurrency";

const props = defineProps({
    item: Object,
    options: Object,
});

const { can } = usePermissions();
const { formatCurrency } = useCurrency();

const money = (minor) => formatCurrency((minor ?? 0) / 100);

const form = useForm({
    resolution: props.item.resolutions[0]?.value ?? "acknowledge",
    note: "",
    counted_qty: null,
    from_storage_id: null,
    quantity: props.item.detail?.oversold_qty ?? null,
    amount: null,
    payment_method: "cash",
    treasury_account_id: null,
    credit_limit: null,
    new_balance: null,
});

const isOpen = computed(() => props.item.status === "open");

const submit = () => {
    form.post(route("reconciliation.resolve", props.item.id), {
        preserveScroll: true,
    });
};

const formatDate = (iso) =>
    iso ? new Date(iso).toLocaleString(undefined, { dateStyle: "medium", timeStyle: "short" }) : "—";

const detailRows = computed(() => {
    const d = props.item.detail ?? {};
    switch (props.item.type) {
        case "oversell":
            return [
                { label: __("Product"), value: d.product },
                { label: __("Storage"), value: d.storage },
                { label: __("Oversold quantity"), value: d.oversold_qty },
                { label: __("On hand before"), value: d.on_hand_before },
                { label: __("Current on hand"), value: d.current_on_hand },
                { label: __("Invoice"), value: d.invoice },
            ];
        case "credit_breach":
            return [
                { label: __("Customer"), value: d.customer },
                { label: __("Credit limit"), value: money(d.credit_limit) },
                { label: __("Balance after"), value: money(d.balance_after) },
                { label: __("Invoice"), value: d.invoice },
            ];
        case "session_variance":
            return [
                { label: __("Expected"), value: money(d.expected_amount) },
                { label: __("Declared"), value: money(d.declared_amount) },
                { label: __("Variance"), value: money(d.variance_amount) },
                { label: __("Drawer"), value: d.drawer },
            ];
        case "parked_mutation":
            return [
                { label: __("Mutation type"), value: d.mutation_type },
                { label: __("Rejection reason"), value: d.rejection_reason },
                { label: __("Message"), value: d.rejection_message },
            ];
        default:
            return [];
    }
});
</script>

<template>
    <AppLayout :title="__('Reconciliation')">
        <div class="mb-6">
            <Link :href="route('reconciliation.index')" class="inline-flex items-center gap-x-1 text-sm text-gray-500 dark:text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400">
                <svg class="h-4 w-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                {{ __("Back to inbox") }}
            </Link>
        </div>

        <div class="flex items-center gap-x-3 mb-6">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white">{{ item.type_label }}</h2>
            <span
                class="px-2.5 py-0.5 text-[11px] font-semibold rounded-full"
                :class="isOpen
                    ? 'text-amber-700 bg-amber-100 dark:bg-amber-900/30 dark:text-amber-400'
                    : 'text-emerald-700 bg-emerald-50 dark:bg-emerald-900/20 dark:text-emerald-400'"
            >
                {{ __(isOpen ? "Open" : "Resolved") }}
            </span>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Detail -->
            <div class="lg:col-span-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ __("Details") }}</h3>
                </div>
                <dl class="p-6 grid gap-4 sm:grid-cols-2">
                    <div v-for="row in detailRows" :key="row.label">
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ row.label }}</dt>
                        <dd class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ row.value ?? "—" }}</dd>
                    </div>
                    <div class="sm:col-span-2 pt-4 border-t border-gray-100 dark:border-gray-800 grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ __("Device") }}</dt>
                            <dd class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ item.device ?? "—" }} · {{ item.register ?? "—" }}</dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ __("Cashier") }}</dt>
                            <dd class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ item.actor ?? "—" }}</dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ __("Occurred") }}</dt>
                            <dd class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ formatDate(item.occurred_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ __("Detected") }}</dt>
                            <dd class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ formatDate(item.detected_at) }}</dd>
                        </div>
                    </div>
                </dl>
            </div>

            <!-- Resolution -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ __("Resolution") }}</h3>
                </div>

                <div v-if="!isOpen" class="p-6 space-y-3">
                    <div class="flex items-center gap-x-2 text-emerald-600 dark:text-emerald-400">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <span class="text-sm font-medium">{{ __("Resolved by :name", { name: item.resolved_by ?? "—" }) }}</span>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ formatDate(item.resolved_at) }}</p>
                    <p v-if="item.resolution_note" class="text-sm text-gray-700 dark:text-gray-300">{{ item.resolution_note }}</p>
                </div>

                <form v-else-if="can('reconciliation.resolve')" class="p-6 space-y-4" @submit.prevent="submit">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">{{ __("How do you want to resolve this?") }}</label>
                        <select v-model="form.resolution" class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50">
                            <option v-for="r in item.resolutions" :key="r.value" :value="r.value">{{ r.label }}</option>
                        </select>
                    </div>

                    <!-- Oversell: Adjust / Shrinkage -->
                    <div v-if="['adjust', 'shrinkage'].includes(form.resolution)">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">{{ __("Counted quantity") }}</label>
                        <input v-model.number="form.counted_qty" type="number" class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50" />
                    </div>

                    <!-- Oversell: Transfer -->
                    <template v-if="form.resolution === 'transfer'">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">{{ __("Transfer from") }}</label>
                            <select v-model.number="form.from_storage_id" class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50">
                                <option :value="null">{{ __("Select storage") }}</option>
                                <option v-for="s in options.storages" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">{{ __("Quantity") }}</label>
                            <input v-model.number="form.quantity" type="number" class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50" />
                        </div>
                    </template>

                    <!-- Credit breach: Collect -->
                    <template v-if="form.resolution === 'collect'">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">{{ __("Amount") }}</label>
                            <input v-model.number="form.amount" type="number" step="0.01" class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">{{ __("Into account") }}</label>
                            <select v-model.number="form.treasury_account_id" class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50">
                                <option :value="null">{{ __("Select account") }}</option>
                                <option v-for="a in options.treasury_accounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                            </select>
                        </div>
                    </template>

                    <!-- Credit breach: Raise limit -->
                    <div v-if="form.resolution === 'raise_limit'">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">{{ __("New credit limit") }}</label>
                        <input v-model.number="form.credit_limit" type="number" step="0.01" class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50" />
                    </div>

                    <!-- Session variance: Adjust drawer -->
                    <div v-if="form.resolution === 'adjust_drawer'">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">{{ __("Corrected drawer balance (minor units)") }}</label>
                        <input v-model.number="form.new_balance" type="number" class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">{{ __("Note") }}</label>
                        <textarea v-model="form.note" rows="2" class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"></textarea>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                    >
                        {{ __("Resolve") }}
                    </button>
                </form>

                <div v-else class="p-6 text-sm text-gray-500 dark:text-gray-400">
                    {{ __("You do not have permission to resolve items.") }}
                </div>
            </div>
        </div>
    </AppLayout>
</template>
