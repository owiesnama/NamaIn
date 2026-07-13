<script setup>
import { ref, computed } from "vue";

const props = defineProps({
    bookings: { type: Array, default: () => [] },
});

const emit = defineEmits(["select", "create"]);

const locale = preferences("language", "en") === "ar" ? "ar" : "en";
// Arabic calendars conventionally start the week on Saturday; otherwise Sunday.
const weekStart = locale === "ar" ? 6 : 0;

const today = new Date();
const cursor = ref(new Date(today.getFullYear(), today.getMonth(), 1));

const monthLabel = computed(() =>
    cursor.value.toLocaleDateString(locale, { month: "long", year: "numeric" })
);

// Localised weekday short names, rotated to the locale's week start.
const weekdays = computed(() => {
    const base = new Date(2026, 1, 1); // a Sunday
    return Array.from({ length: 7 }, (_, i) => {
        const d = new Date(base);
        d.setDate(base.getDate() + ((weekStart + i) % 7));
        return d.toLocaleDateString(locale, { weekday: "short" });
    });
});

const pad = (n) => String(n).padStart(2, "0");
const dateKey = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

// Group bookings by calendar day (local date portion of starts_at).
const byDay = computed(() => {
    const map = {};
    for (const b of props.bookings) {
        if (!b.starts_at) continue;
        const key = b.starts_at.slice(0, 10);
        (map[key] ??= []).push(b);
    }
    return map;
});

// Build the 6-week grid covering the visible month.
const cells = computed(() => {
    const year = cursor.value.getFullYear();
    const month = cursor.value.getMonth();
    const first = new Date(year, month, 1);
    const lead = (first.getDay() - weekStart + 7) % 7;
    const start = new Date(year, month, 1 - lead);

    return Array.from({ length: 42 }, (_, i) => {
        const d = new Date(start.getFullYear(), start.getMonth(), start.getDate() + i);
        const key = dateKey(d);
        return {
            key,
            day: d.toLocaleDateString(locale, { day: "numeric" }),
            inMonth: d.getMonth() === month,
            isToday: key === dateKey(today),
            bookings: byDay.value[key] ?? [],
        };
    });
});

const timeOf = (b) =>
    b.starts_at
        ? new Date(b.starts_at).toLocaleTimeString(locale, { hour: "2-digit", minute: "2-digit" })
        : "";

const statusClass = (status) =>
    ({
        confirmed: "bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400",
        completed: "bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400",
        cancelled: "bg-red-50 text-red-600 line-through dark:bg-red-900/20 dark:text-red-400",
    }[status] ?? "bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400");

const shift = (delta) => {
    cursor.value = new Date(cursor.value.getFullYear(), cursor.value.getMonth() + delta, 1);
};
const goToday = () => {
    cursor.value = new Date(today.getFullYear(), today.getMonth(), 1);
};
</script>

<template>
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-x-2">
                <button
                    type="button"
                    class="p-2 text-gray-500 dark:text-gray-400 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                    :title="__('Previous')"
                    @click="shift(-1)"
                >
                    <svg class="h-4 w-4 rtl:rotate-180" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                </button>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white min-w-[9rem] text-center">{{ monthLabel }}</h3>
                <button
                    type="button"
                    class="p-2 text-gray-500 dark:text-gray-400 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                    :title="__('Next')"
                    @click="shift(1)"
                >
                    <svg class="h-4 w-4 rtl:rotate-180" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                </button>
            </div>
            <button
                type="button"
                class="px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                @click="goToday"
            >
                {{ __("Today") }}
            </button>
        </div>

        <!-- Weekday header -->
        <div class="grid grid-cols-7 border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/40">
            <div
                v-for="wd in weekdays"
                :key="wd"
                class="px-2 py-2 text-center text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500"
            >
                {{ wd }}
            </div>
        </div>

        <!-- Day grid -->
        <div class="grid grid-cols-7">
            <div
                v-for="cell in cells"
                :key="cell.key"
                class="min-h-[6rem] border-b border-e border-gray-100 dark:border-gray-800 p-1.5 cursor-pointer transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/50"
                :class="cell.inMonth ? 'bg-white dark:bg-gray-900' : 'bg-gray-50/40 dark:bg-gray-800/20'"
                @click="emit('create', cell.key)"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="inline-flex items-center justify-center w-6 h-6 text-xs rounded-full"
                        :class="[
                            cell.isToday ? 'bg-emerald-600 text-white font-semibold' : 'text-gray-500 dark:text-gray-400',
                            !cell.inMonth && !cell.isToday ? 'opacity-40' : '',
                        ]"
                    >
                        {{ cell.day }}
                    </span>
                </div>

                <div class="mt-1 space-y-1">
                    <button
                        v-for="b in cell.bookings.slice(0, 3)"
                        :key="b.id"
                        type="button"
                        class="block w-full px-1.5 py-1 text-start rounded-md text-[11px] leading-tight truncate transition-colors"
                        :class="statusClass(b.status)"
                        @click.stop="emit('select', b)"
                        :title="`${b.service?.name ?? ''} — ${b.customer?.name ?? ''}`"
                    >
                        <Ltr class="font-semibold">{{ timeOf(b) }}</Ltr>
                        <span class="ms-1">{{ b.service?.name }}</span>
                    </button>
                    <p v-if="cell.bookings.length > 3" class="px-1.5 text-[10px] text-gray-400 dark:text-gray-500">
                        +{{ cell.bookings.length - 3 }} {{ __("more") }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
