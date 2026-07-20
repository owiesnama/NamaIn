<script setup>
    import { computed } from "vue";
    import { useDate } from "@/Composables/useDate.js";
    import Ltr from "@/Components/Ltr.vue";

    const props = defineProps({
        tenant: { type: Object, required: true },
        members: { type: Array, default: () => [] },
        subscription: { type: Object, default: null },
        plans: { type: Array, default: () => [] },
        overrides: { type: Array, default: () => [] },
        entitlements: { type: Array, default: () => [] },
    });

    const { formatDate } = useDate();

    const roleLabel = (role) => __(role);

    const membersMeta = computed(() => {
        const order = ["owner", "manager", "cashier"];
        const counts = props.members.reduce((acc, m) => {
            acc[m.role] = (acc[m.role] || 0) + 1;
            return acc;
        }, {});

        return Object.keys(counts)
            .sort((a, b) => (order.indexOf(a) + 1 || 99) - (order.indexOf(b) + 1 || 99))
            .map((role) => `${counts[role]} ${roleLabel(role)}`)
            .join(" · ") || __("No members found.");
    });

    const defaultPlan = computed(() => props.plans.find((p) => p.is_default) ?? null);

    const currentPlanValue = computed(
        () => props.subscription?.plan_name ?? defaultPlan.value?.name ?? __("Default")
    );

    const STATUS_LABELS = { active: "Active", trialing: "Trialing", canceled: "Canceled" };

    const currentPlanMeta = computed(() =>
        props.subscription
            ? __(STATUS_LABELS[props.subscription.status] ?? props.subscription.status)
            : __("No active subscription")
    );

    const liveOverrides = computed(() => props.overrides.filter((o) => o.is_live));

    const overridesMeta = computed(() => {
        if (! liveOverrides.value.length) {
            return __("None");
        }

        return liveOverrides.value
            .map((o) => props.entitlements.find((e) => e.key === o.feature)?.label ?? o.feature)
            .join(" · ");
    });

    const cards = computed(() => [
        {
            key: "members",
            label: __("Members"),
            value: props.members.length,
            meta: membersMeta.value,
            ltr: true,
            tone: "emerald",
            icon: "M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z",
        },
        {
            key: "plan",
            label: __("Current Plan"),
            value: currentPlanValue.value,
            meta: currentPlanMeta.value,
            ltr: false,
            tone: "gray",
            icon: "M6 6.878V6a2.25 2.25 0 0 1 2.25-2.25h7.5A2.25 2.25 0 0 1 18 6v.878m-12 0c.235-.083.487-.128.75-.128h10.5c.263 0 .515.045.75.128m-12 0A2.25 2.25 0 0 0 4.5 9v.878m13.5-3A2.25 2.25 0 0 1 19.5 9v.878m0 0a2.246 2.246 0 0 0-.75-.128H5.25c-.263 0-.515.045-.75.128m15 0A2.25 2.25 0 0 1 21 12v6a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18v-6c0-.98.626-1.813 1.5-2.122",
        },
        {
            key: "overrides",
            label: __("Overrides"),
            value: liveOverrides.value.length,
            meta: overridesMeta.value,
            ltr: true,
            tone: "amber",
            icon: "M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z",
        },
        {
            key: "created",
            label: __("Created"),
            value: formatDate(props.tenant.created_at),
            meta: null,
            ltr: true,
            tone: "gray",
            icon: "M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5",
        },
    ]);

    const toneChip = {
        emerald: "bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400",
        amber: "bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400",
        gray: "bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400",
    };
</script>

<template>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div
            v-for="card in cards"
            :key="card.key"
            class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-5"
        >
            <div class="flex items-start gap-x-3">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
                    :class="toneChip[card.tone]"
                >
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="card.icon" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ card.label }}</p>
                    <p class="mt-0.5 text-xl font-bold text-gray-900 dark:text-white truncate">
                        <Ltr v-if="card.ltr">{{ card.value }}</Ltr>
                        <template v-else>{{ card.value }}</template>
                    </p>
                    <p v-if="card.meta" class="mt-0.5 text-xs text-gray-400 dark:text-gray-500 truncate" :title="card.meta">
                        {{ card.meta }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
