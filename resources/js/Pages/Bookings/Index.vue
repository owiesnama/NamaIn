<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import Pagination from "@/Shared/Pagination.vue";
import BookingCalendar from "@/Components/Bookings/BookingCalendar.vue";
import BookingForm from "@/Components/Bookings/BookingForm.vue";
import { ref } from "vue";
import { router } from "@inertiajs/vue3";

const props = defineProps({
    bookings: { type: Object, required: true },
});

const showForm = ref(false);
const editingBooking = ref(null);
const prefillStart = ref(null);

const openCreate = (dateKey = null) => {
    editingBooking.value = null;
    prefillStart.value = dateKey ? `${dateKey}T09:00` : null;
    showForm.value = true;
};

const openEdit = (booking) => {
    editingBooking.value = booking;
    prefillStart.value = null;
    showForm.value = true;
};

const onSaved = () => {
    showForm.value = false;
    router.reload({ only: ["bookings"] });
};

const cancelBooking = (booking) => {
    if (!window.confirm(__("Cancel this booking?"))) return;
    router.patch(route("bookings.cancel", booking.id), {}, { preserveScroll: true });
};

const locale = preferences("language", "en") === "ar" ? "ar" : "en";
const formatWhen = (value) =>
    value
        ? new Date(value).toLocaleString(locale, {
              dateStyle: "medium",
              timeStyle: "short",
          })
        : "";

const statusBadge = (status) =>
    ({
        confirmed: "text-emerald-700 bg-emerald-50 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800",
        completed: "text-gray-600 bg-gray-100 border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700",
        cancelled: "text-red-700 bg-red-50 border-red-200 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800",
    }[status] ?? "text-gray-600 bg-gray-100 border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700");

const statusLabel = (status) =>
    ({ confirmed: __("Confirmed"), completed: __("Completed"), cancelled: __("Cancelled") }[status] ?? status);
</script>

<template>
    <AppLayout :title="__('Bookings')">
        <div class="w-full">
            <!-- Header -->
            <div class="w-full lg:flex lg:items-center lg:justify-between mb-6">
                <div>
                    <div class="flex items-center gap-x-3">
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-white">{{ __("Bookings") }}</h2>
                        <span class="px-3 py-1 text-xs font-semibold rounded-full text-emerald-700 bg-emerald-100/60 dark:bg-gray-800 dark:text-emerald-400">
                            {{ bookings.total }} {{ __("Bookings") }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __("Manage service appointments on one calendar.") }}</p>
                </div>
                <div class="mt-4 flex items-center justify-end gap-x-4 lg:mt-0">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-x-2 px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-colors duration-200"
                        @click="openCreate()"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" /></svg>
                        {{ __("New booking") }}
                    </button>
                </div>
            </div>

            <!-- Calendar -->
            <BookingCalendar :bookings="bookings.data" @select="openEdit" @create="openCreate" />

            <!-- List -->
            <div class="mt-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50/50 dark:bg-gray-800/40">
                            <tr>
                                <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("When") }}</th>
                                <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Service") }}</th>
                                <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Customer") }}</th>
                                <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Status") }}</th>
                                <th class="px-6 py-4 text-end text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500"><span class="sr-only">{{ __("Actions") }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200/60 dark:divide-gray-700/60 bg-white dark:bg-gray-900">
                            <tr
                                v-for="booking in bookings.data"
                                :key="booking.id"
                                class="group hover:bg-gray-50 dark:hover:bg-gray-800 transition-all duration-200 cursor-pointer"
                                @click="openEdit(booking)"
                            >
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300"><Ltr>{{ formatWhen(booking.starts_at) }}</Ltr></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">{{ booking.service?.name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ booking.customer?.name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-1 text-[11px] font-bold rounded-lg border" :class="statusBadge(booking.status)">
                                        {{ statusLabel(booking.status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-end" @click.stop>
                                    <button
                                        v-if="booking.status === 'confirmed'"
                                        type="button"
                                        class="px-3 py-1.5 text-xs font-medium text-red-600 dark:text-red-400 bg-white dark:bg-gray-800 border border-red-200 dark:border-red-800 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                                        @click="cancelBooking(booking)"
                                    >
                                        {{ __("Cancel") }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="!bookings.data.length" class="py-12 text-center text-sm text-gray-400 dark:text-gray-500">
                    {{ __("No bookings yet.") }}
                </div>
            </div>

            <Pagination v-if="bookings.data.length" :links="bookings.links" class="mt-6" />
        </div>

        <BookingForm
            :show="showForm"
            :booking="editingBooking"
            :prefill-start="prefillStart"
            @close="showForm = false"
            @saved="onSaved"
        />
    </AppLayout>
</template>
