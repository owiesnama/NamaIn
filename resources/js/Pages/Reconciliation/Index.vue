<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { Link, router } from "@inertiajs/vue3";
import { ref, watch } from "vue";
import Pagination from "@/Shared/Pagination.vue";

const props = defineProps({
    items: Object,
    filters: Object,
    openCount: Number,
    openCountsByType: Object,
    types: Array,
});

const filters = ref({
    status: props.filters.status ?? "open",
    type: props.filters.type ?? null,
});

watch(
    filters,
    (value) => {
        router.get(route("reconciliation.index"), value, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    },
    { deep: true },
);

const typeClasses = {
    oversell: "text-red-700 bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-800 dark:text-red-400",
    credit_breach: "text-amber-700 bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-400",
    session_variance: "text-orange-700 bg-orange-50 border-orange-200 dark:bg-orange-900/20 dark:border-orange-800 dark:text-orange-400",
    parked_mutation: "text-gray-600 bg-gray-100 border-gray-200 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400",
};

const formatDate = (iso) =>
    iso
        ? new Date(iso).toLocaleString(undefined, { dateStyle: "medium", timeStyle: "short" })
        : "—";
</script>

<template>
    <AppLayout :title="__('Reconciliation')">
        <div class="w-full lg:flex lg:items-center lg:justify-between">
            <div>
                <div class="flex items-center gap-x-3">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-white">{{ __("Reconciliation") }}</h2>
                    <span class="px-3 py-1 text-xs font-semibold rounded-full text-amber-700 bg-amber-100/60 dark:bg-gray-800 dark:text-amber-400">
                        {{ openCount }} {{ __("Open") }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ __("Divergences surfaced by offline devices that need a decision.") }}
                </p>
            </div>
        </div>

        <!-- Filters -->
        <div class="mt-6 flex flex-wrap items-center gap-3">
            <div class="inline-flex rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                <button
                    v-for="option in ['open', 'resolved', 'all']"
                    :key="option"
                    type="button"
                    class="px-4 py-2 text-sm font-medium transition-colors duration-200"
                    :class="filters.status === option
                        ? 'bg-emerald-600 text-white'
                        : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'"
                    @click="filters.status = option"
                >
                    {{ __(option.charAt(0).toUpperCase() + option.slice(1)) }}
                </button>
            </div>

            <select
                v-model="filters.type"
                class="px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
            >
                <option :value="null">{{ __("All types") }}</option>
                <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
        </div>

        <!-- Table -->
        <div class="mt-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50/50 dark:bg-gray-800/40">
                        <tr>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Type") }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Device") }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Register") }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Occurred") }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Status") }}</th>
                            <th class="px-6 py-4 text-end text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Action") }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200/60 dark:divide-gray-700/60 bg-white dark:bg-gray-900">
                        <tr v-for="item in items.data" :key="item.public_id" class="group hover:bg-gray-50 dark:hover:bg-gray-800 transition-all duration-200">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center gap-x-1.5 px-2.5 py-1 text-[11px] font-bold rounded-lg border" :class="typeClasses[item.type]">
                                    {{ item.type_label }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ item.device ?? "—" }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ item.register ?? "—" }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ formatDate(item.occurred_at) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    class="px-2.5 py-0.5 text-[11px] font-semibold rounded-full"
                                    :class="item.status === 'open'
                                        ? 'text-amber-700 bg-amber-100 dark:bg-amber-900/30 dark:text-amber-400'
                                        : 'text-emerald-700 bg-emerald-50 dark:bg-emerald-900/20 dark:text-emerald-400'"
                                >
                                    {{ __(item.status === "open" ? "Open" : "Resolved") }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-end">
                                <Link :href="route('reconciliation.show', item.id)" class="text-sm font-medium text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300">
                                    {{ __("Review") }}
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="items.data.length === 0" class="py-12 text-center text-sm text-gray-400 dark:text-gray-500">
                {{ __("No reconciliation items found.") }}
            </div>
        </div>

        <Pagination :links="items.links" />
    </AppLayout>
</template>
