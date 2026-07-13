<script setup>
import { ref, computed, watch } from "vue";
import { useForm, usePage } from "@inertiajs/vue3";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import QuickAddPartyModal from "@/Components/QuickAddPartyModal.vue";
import { useAsyncOptions } from "@/Composables/useAsyncOptions";

const props = defineProps({
    show: { type: Boolean, default: false },
    booking: { type: Object, default: null },
    prefillStart: { type: String, default: null },
});

const emit = defineEmits(["close", "saved"]);

const isEditing = computed(() => !!props.booking);
const modalDir = preferences("language", "en") === "ar" ? "rtl" : "ltr";

const {
    options: customerOptions,
    loading: customersLoading,
    loadMore: loadMoreCustomers,
    onSearch: searchCustomers,
} = useAsyncOptions(route("api.customers.index"));

const {
    options: serviceOptions,
    loading: servicesLoading,
    loadMore: loadMoreServices,
    onSearch: searchServices,
} = useAsyncOptions(route("api.products.index", { type: "service" }));

const selectedCustomer = ref(props.booking?.customer ?? null);
const selectedService = ref(props.booking?.service ?? null);
const showQuickAddModal = ref(false);
const bufferWarnings = ref([]);

const form = useForm({
    service_product_id: props.booking?.service_product_id ?? null,
    customer_id: props.booking?.customer_id ?? null,
    starts_at: props.booking?.starts_at
        ? props.booking.starts_at.slice(0, 16)
        : props.prefillStart ?? "",
    address: props.booking?.address ?? "",
    notes: props.booking?.notes ?? "",
    status: props.booking?.status ?? "confirmed",
    addons: [],
    acknowledge_buffer: false,
});

// Reset the form whenever the modal is (re)opened for a new context.
watch(
    () => props.show,
    (open) => {
        if (!open) return;
        bufferWarnings.value = [];
        selectedCustomer.value = props.booking?.customer ?? null;
        selectedService.value = props.booking?.service ?? null;
        form.defaults({
            service_product_id: props.booking?.service_product_id ?? null,
            customer_id: props.booking?.customer_id ?? null,
            starts_at: props.booking?.starts_at
                ? props.booking.starts_at.slice(0, 16)
                : props.prefillStart ?? "",
            address: props.booking?.address ?? "",
            notes: props.booking?.notes ?? "",
            status: props.booking?.status ?? "confirmed",
            addons: [],
            acknowledge_buffer: false,
        });
        form.reset();
        form.clearErrors();
    }
);

const serviceAddons = computed(() => selectedService.value?.service_addons ?? []);
const requiresAddress = computed(() => !!selectedService.value?.on_site);

const endsAt = computed(() => {
    if (!form.starts_at || !selectedService.value?.duration_minutes) return null;
    const start = new Date(form.starts_at);
    if (isNaN(start.getTime())) return null;
    const end = new Date(start.getTime() + selectedService.value.duration_minutes * 60000);
    return end.toISOString().slice(0, 16).replace("T", " ");
});

const bookingTotal = computed(() => {
    const base = parseFloat(selectedService.value?.price) || 0;
    const extras = serviceAddons.value
        .filter((a) => form.addons.includes(a.id))
        .reduce((sum, a) => sum + (parseFloat(a.price_delta) || 0), 0);
    return base + extras;
});

const onCustomerSelect = (customerId) => {
    selectedCustomer.value = customerOptions.value.find((c) => c.id == customerId) ?? selectedCustomer.value;
    form.customer_id = customerId ?? null;
};

const onCustomerCreated = (customer) => {
    selectedCustomer.value = customer;
    form.customer_id = customer.id;
    customerOptions.value.unshift(customer);
    showQuickAddModal.value = false;
};

const onServiceSelect = (serviceId) => {
    const service = serviceOptions.value.find((s) => s.id == serviceId) ?? null;
    selectedService.value = service;
    form.service_product_id = serviceId ?? null;
    form.addons = []; // clear stale add-ons when the service changes
    if (!requiresAddress.value) form.address = "";
};

const currency = computed(() =>
    /^[A-Z]{3}$/.test(preferences("currency")) ? preferences("currency") : "SDG"
);

const submit = () => {
    const onSuccess = () => {
        const warnings = usePage().props.flash?.travel_buffer_warnings;
        if (warnings && warnings.length) {
            bufferWarnings.value = warnings;
            return;
        }
        bufferWarnings.value = [];
        form.reset();
        emit("saved");
    };

    const options = { preserveScroll: true, preserveState: true, onSuccess };

    if (isEditing.value) {
        form.put(route("bookings.update", props.booking.id), options);
    } else {
        form.post(route("bookings.store"), options);
    }
};

const confirmAndProceed = () => {
    form.acknowledge_buffer = true;
    bufferWarnings.value = [];
    submit();
};

const close = () => {
    bufferWarnings.value = [];
    emit("close");
};
</script>

<template>
    <div class="relative z-50" role="dialog" aria-modal="true">
        <transition
            enter-from-class="opacity-0"
            enter-active-class="duration-300 ease-out"
            enter-to-class="opacity-100"
            leave-from-class="opacity-100"
            leave-active-class="duration-200 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-show="show"
                class="fixed inset-0 transition-opacity bg-gray-500/20 dark:bg-gray-900/60 backdrop-blur-sm"
                @click="close"
            ></div>
        </transition>

        <transition
            enter-from-class="scale-95 opacity-0"
            enter-active-class="duration-200 ease-out"
            enter-to-class="scale-100 opacity-100"
            leave-from-class="scale-100 opacity-100"
            leave-active-class="duration-200 ease-in"
            leave-to-class="scale-95 opacity-0"
        >
            <div v-show="show" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex items-end justify-center min-h-full p-4 text-center sm:items-center sm:p-0">
                    <div
                        :dir="modalDir"
                        class="relative w-full px-6 pt-6 pb-6 text-start transition-all transform bg-white border border-gray-200 shadow-xl dark:bg-gray-900 dark:border-gray-700 rounded-xl sm:my-8 sm:max-w-2xl"
                        @click.stop
                    >
                        <!-- Header -->
                        <div class="flex items-start justify-between gap-x-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    {{ isEditing ? __("Edit Booking") : __("New Booking") }}
                                </h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ __("Schedule an appointment for a service.") }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="p-1 text-gray-400 transition-colors duration-200 rounded-lg shrink-0 hover:text-gray-600 hover:bg-gray-50 dark:text-gray-500 dark:hover:text-gray-300 dark:hover:bg-gray-800 focus:outline-none"
                                @click="close"
                                :title="__('Cancel')"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <form class="mt-6 space-y-5" @submit.prevent="submit">
                            <!-- Service -->
                            <div>
                                <InputLabel :value="__('Service')" />
                                <CustomSelect
                                    :model-value="selectedService"
                                    :options="serviceOptions"
                                    label="name"
                                    track-by="id"
                                    :remote="true"
                                    :loading="servicesLoading"
                                    class="mt-1"
                                    :placeholder="__('Search service...')"
                                    @update:model-value="onServiceSelect"
                                    @search-change="searchServices"
                                    @scroll-end="loadMoreServices"
                                />
                                <InputError class="mt-1" :message="form.errors.service_product_id" />
                            </div>

                            <!-- Customer -->
                            <div>
                                <InputLabel :value="__('Customer')" />
                                <CustomSelect
                                    :model-value="selectedCustomer"
                                    :options="customerOptions"
                                    label="name"
                                    track-by="id"
                                    :remote="true"
                                    :loading="customersLoading"
                                    class="mt-1"
                                    :placeholder="__('Search customer...')"
                                    @update:model-value="onCustomerSelect"
                                    @search-change="searchCustomers"
                                    @scroll-end="loadMoreCustomers"
                                >
                                    <template #noResult>
                                        <div class="p-2 space-y-2 text-center">
                                            <p class="text-sm text-gray-500">{{ __("No elements found. Consider changing the search query.") }}</p>
                                            <button type="button" @click="showQuickAddModal = true" class="text-emerald-600 hover:text-emerald-700 font-semibold text-sm">
                                                + {{ __("Add New Customer") }}
                                            </button>
                                        </div>
                                    </template>
                                </CustomSelect>
                                <InputError class="mt-1" :message="form.errors.customer_id" />
                            </div>

                            <!-- Date/time + derived end -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel for="starts_at" :value="__('Starts at')" />
                                    <input
                                        id="starts_at"
                                        v-model="form.starts_at"
                                        type="datetime-local"
                                        dir="ltr"
                                        class="w-full px-3 py-2 mt-1 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                                    />
                                    <InputError class="mt-1" :message="form.errors.starts_at" />
                                </div>
                                <div>
                                    <InputLabel :value="__('Ends at')" />
                                    <div class="w-full px-3 py-2 mt-1 text-sm text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-800/40 border border-gray-200 dark:border-gray-700 rounded-lg">
                                        <Ltr>{{ endsAt || "—" }}</Ltr>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ __("Derived from the service duration.") }}</p>
                                </div>
                            </div>

                            <!-- Address (on-site only) -->
                            <div v-if="requiresAddress">
                                <InputLabel for="address" :value="__('Address')" />
                                <TextInput id="address" v-model="form.address" type="text" class="block w-full mt-1" :placeholder="__('Client address')" />
                                <InputError class="mt-1" :message="form.errors.address" />
                            </div>

                            <!-- Add-ons -->
                            <div v-if="serviceAddons.length">
                                <InputLabel :value="__('Add-ons')" />
                                <div class="mt-1 space-y-2">
                                    <label
                                        v-for="addon in serviceAddons"
                                        :key="addon.id"
                                        class="flex items-center justify-between px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50"
                                    >
                                        <span class="flex items-center gap-x-2 text-sm text-gray-700 dark:text-gray-300">
                                            <input
                                                type="checkbox"
                                                :value="addon.id"
                                                v-model="form.addons"
                                                class="border-gray-300 dark:border-gray-600 rounded text-emerald-600 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                                            />
                                            {{ addon.name }}
                                        </span>
                                        <Ltr class="text-sm font-medium text-gray-500 dark:text-gray-400">+{{ Number(addon.price_delta).toFixed(2) }}</Ltr>
                                    </label>
                                </div>
                            </div>

                            <!-- Running total -->
                            <div
                                v-if="selectedService"
                                class="flex items-center justify-between px-3 py-2 text-sm rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400"
                            >
                                <span class="font-medium">{{ __("Total") }}</span>
                                <Ltr class="font-semibold">{{ bookingTotal.toFixed(2) }} {{ currency }}</Ltr>
                            </div>

                            <!-- Status + Notes -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel for="status" :value="__('Status')" />
                                    <select
                                        id="status"
                                        v-model="form.status"
                                        class="block w-full px-3 py-2 mt-1 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                                    >
                                        <option value="confirmed">{{ __("Confirmed") }}</option>
                                        <option value="completed">{{ __("Completed") }}</option>
                                        <option value="cancelled">{{ __("Cancelled") }}</option>
                                    </select>
                                </div>
                                <div>
                                    <InputLabel for="notes" :value="__('Notes')" />
                                    <TextInput id="notes" v-model="form.notes" type="text" class="block w-full mt-1" :placeholder="__('Optional')" />
                                </div>
                            </div>

                            <!-- Travel-buffer soft warning -->
                            <div
                                v-if="bufferWarnings.length"
                                class="p-4 border rounded-lg border-amber-200 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-800"
                            >
                                <div class="flex items-start gap-x-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 shrink-0 text-amber-600 dark:text-amber-400">
                                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625l6.28-10.875zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                                    </svg>
                                    <div class="text-sm text-amber-800 dark:text-amber-300">
                                        <p class="font-semibold">{{ __("Tight travel buffer") }}</p>
                                        <p v-for="(w, i) in bufferWarnings" :key="i" class="mt-0.5">
                                            {{ w.direction === 'before'
                                                ? __("Only :gap min after the previous booking (buffer :buf min).", { gap: w.gap_minutes, buf: w.required_buffer_minutes })
                                                : __("Only :gap min before the next booking (buffer :buf min).", { gap: w.gap_minutes, buf: w.required_buffer_minutes }) }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex justify-end mt-3">
                                    <button
                                        type="button"
                                        @click="confirmAndProceed"
                                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white rounded-lg bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 transition-colors duration-200"
                                    >
                                        {{ __("Confirm and proceed") }}
                                    </button>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="flex items-center justify-end pt-5 mt-5 border-t border-gray-200 dark:border-gray-700 gap-x-3">
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 bg-white border border-gray-300 rounded-lg dark:text-gray-300 dark:bg-gray-800 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors duration-200"
                                    @click="close"
                                >
                                    {{ __("Cancel") }}
                                </button>
                                <PrimaryButton :class="{ 'opacity-50': form.processing }" :disabled="form.processing">
                                    {{ isEditing ? __("Save") : __("Create booking") }}
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </transition>

        <QuickAddPartyModal
            :show="showQuickAddModal"
            type="customer"
            @close="showQuickAddModal = false"
            @created="onCustomerCreated"
        />
    </div>
</template>
