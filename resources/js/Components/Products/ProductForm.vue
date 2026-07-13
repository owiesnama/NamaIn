<script setup>
    import { useForm } from "@inertiajs/vue3";
    import { ref, computed, watch } from "vue";
    import InputError from "@/Components/InputError.vue";
    import InputLabel from "@/Components/InputLabel.vue";
    import PrimaryButton from "@/Components/PrimaryButton.vue";
    import TextInput from "@/Components/TextInput.vue";
    import CustomSelect from "../CustomSelect.vue";

    const props = defineProps({
        product: {
            type: Object,
            default: () => {},
            required: false,
        },
        categories: {
            type: Array,
            default: () => [],
            required: false,
        },
        storages: {
            type: Array,
            default: () => [],
            required: false,
        },
    });

    const isEditing = !!props.product;

    const currentQuantityFor = (storageId) => {
        const entry = (props.product?.stock || []).find((s) => s.id === storageId);
        return entry?.pivot?.quantity ?? 0;
    };

    const storageOf = (storageId) =>
        (props.storages || []).find((s) => s.id === storageId) || {};
    const storageName = (storageId) => storageOf(storageId).name ?? "";
    const storageType = (storageId) => storageOf(storageId).type ?? "";

    // Under purchase-driven, stock only enters via purchase invoices, so the
    // per-location quantities are shown read-only. Free-form allows editing them.
    const manualStockAllowed =
        preferences("inventory_strategy", "purchase_driven") === "free_form";

    const show = ref(false);
    const product = useForm({
        name: props.product?.name,
        cost: props.product?.cost ? String(props.product?.cost) : "",
        price: props.product?.price ? String(props.product?.price) : "",
        expire_date: props.product?.expire_date || "",
        currency: props.product?.currency || preferences("currency") || "SDG",
        alert_quantity: props.product?.alert_quantity || "",
        categories: props.product?.categories || [],
        units: props.product?.units?.length
            ? props.product?.units
            : [],
        quantities: (props.storages || []).map((storage) => ({
            storage_id: storage.id,
            quantity: currentQuantityFor(storage.id),
        })),
    });

    const setCurrency = (value) => {
        product.currency = (value || "").toUpperCase();
    };

    const addUnit = () => {
        product.units.push({ name: "", conversion_factor: null });
    };

    const addCategory = (newTag) => {
        const tag = {
            name: newTag,
            id: newTag, // Use name as ID for new tags, backend will handle it
        };
        product.categories.push(tag);
    };

    const formAttributes = () => {
        let action = props.product ? "put" : "post";
        let url = props.product
            ? route("products.update", props.product)
            : route("products.index");
        return [action, url];
    };

    const save = () => {
        let [action, route] = formAttributes();
        product[action](route, {
            preserveState: true,
            onSuccess: () => {
                show.value = false;
                product.reset();
            },
        });
    };

    const cancel = () => {
        if (product.isDirty && !window.confirm(__("Discard unsaved changes?"))) {
            return;
        }
        product.reset();
        show.value = false;
    };

    const modalDir = preferences("language", "en") === "ar" ? "rtl" : "ltr";

    // Common currencies, always including the product's current one.
    const currencies = [...new Set(["SDG", "USD", "EUR", "SAR", "AED", "EGP", product.currency].filter(Boolean))];

    // Live, debounced-ish margin readout between cost and price.
    const margin = computed(() => {
        const cost = parseFloat(product.cost);
        const price = parseFloat(product.price);
        if (!product.cost || !product.price || isNaN(cost) || isNaN(price)) return null;
        const profit = price - cost;
        return { profit, pct: cost > 0 ? (profit / cost) * 100 : 0, belowCost: price < cost };
    });

    // Units are demoted to a collapsible section; expand it when units exist.
    const showUnits = ref((props.product?.units?.length || 0) > 0);

    // Esc closes the dialog (with the dirty guard).
    const onKeydown = (e) => { if (e.key === "Escape") cancel(); };
    watch(show, (open) => {
        if (open) window.addEventListener("keydown", onKeydown);
        else window.removeEventListener("keydown", onKeydown);
    });
</script>

<template>
    <div>
        <a
            v-if="props.product"
            href="#"
            class="p-2 text-gray-400 transition-colors duration-200 rounded-lg hover:text-yellow-500 hover:bg-yellow-50 dark:hover:bg-yellow-900/20 focus:outline-none"
            @click="show = true"
            :title="__('Edit')"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="w-4 h-4"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"
                />
            </svg>
        </a>
        <button
            v-else
            class="w-full px-5 py-2.5 mt-3 text-sm tracking-wide text-white transition-colors font-bold duration-200 rounded-lg sm:mt-0 bg-emerald-500 shrink-0 sm:w-auto hover:bg-emerald-600 dark:hover:bg-emerald-500 dark:bg-emerald-600"
            @click="show = true"
        >
            + {{ __("Add New Product") }}
        </button>

        <div
            class="relative z-50"
            aria-labelledby="modal-title"
            role="dialog"
            aria-modal="true"
        >
            <transition
                enter-from-class="opacity-0"
                enter-active-class="duration-300 ease-out transform"
                enter-to-class="opacity-100"
                leave-from-class="opacity-100"
                leave-active-class="duration-300 ease-in transform"
                leave-to-class="opacity-0"
            >
                <div
                    v-show="show"
                    class="fixed inset-0 transition-opacity bg-gray-500/20 dark:bg-gray-900/60 backdrop-blur-sm"
                    @click="cancel"
                ></div>
            </transition>

            <transition
                enter-from-class="scale-95 translate-y-0 opacity-0"
                enter-active-class="duration-200 ease-out transform"
                enter-to-class="scale-100 translate-y-0 opacity-100"
                leave-from-class="scale-100 translate-y-0 opacity-100"
                leave-active-class="duration-200 ease-in transform"
                leave-to-class="scale-95 translate-y-0 opacity-0"
            >
                <div
                    v-show="show"
                    class="fixed inset-0 z-50 overflow-y-auto"
                >
                    <div
                        class="flex items-end justify-center min-h-full p-4 text-center sm:items-center sm:p-0"
                    >
                        <div
                            :dir="modalDir"
                            class="relative w-full px-6 pt-6 pb-6 overflow-hidden text-start transition-all transform bg-white border border-gray-200 shadow-xl dark:bg-gray-900 dark:border-gray-700 rounded-xl sm:my-8 sm:max-w-4xl"
                            @click.stop
                        >
                            <!-- Header -->
                            <div class="flex items-start justify-between gap-x-4">
                                <div>
                                    <h3 id="modal-title" class="text-lg font-semibold text-gray-900 dark:text-white">
                                        {{ isEditing ? __("Edit Product") : __("Add New Product") }}
                                    </h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        {{ __("Fill in the product details below.") }}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="p-1 text-gray-400 transition-colors duration-200 rounded-lg shrink-0 hover:text-gray-600 hover:bg-gray-50 dark:text-gray-500 dark:hover:text-gray-300 dark:hover:bg-gray-800 focus:outline-none"
                                    @click="cancel"
                                    :title="__('Cancel')"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <form
                                class="mt-6"
                                @submit.prevent="save"
                            >
                                <div class="grid gap-6 md:grid-cols-2">
                                    <!-- Left column: product details -->
                                    <div class="space-y-5">
                                        <div>
                                            <InputLabel
                                                for="name"
                                                :value="__('Name')"
                                            />
                                            <TextInput
                                                id="name"
                                                v-model="product.name"
                                                type="text"
                                                class="block w-full mt-1"
                                                required
                                                autofocus
                                            />
                                            <InputError
                                                class="mt-1"
                                                :message="product.errors.name"
                                            />
                                        </div>

                                        <!-- Pricing group -->
                                        <div class="p-4 space-y-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 rtl:text-right">
                                                {{ __("Pricing") }}
                                            </p>
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <InputLabel
                                                        for="cost"
                                                        :value="__('Cost')"
                                                    />
                                                    <TextInput
                                                        id="cost"
                                                        v-model="product.cost"
                                                        type="number" inputmode="decimal"
                                                        min="0"
                                                        step="0.01"
                                                        class="block w-full mt-1"
                                                        :placeholder="__('0.00')"
                                                    />
                                                    <InputError
                                                        class="mt-1"
                                                        :message="product.errors.cost"
                                                    />
                                                </div>
                                                <div>
                                                    <InputLabel
                                                        for="price"
                                                        :value="__('Price')"
                                                    />
                                                    <TextInput
                                                        id="price"
                                                        v-model="product.price"
                                                        type="number" inputmode="decimal"
                                                        min="0"
                                                        step="0.01"
                                                        class="block w-full mt-1"
                                                        :placeholder="__('0.00')"
                                                    />
                                                    <InputError
                                                        class="mt-1"
                                                        :message="product.errors.price"
                                                    />
                                                </div>
                                            </div>
                                            <!-- Live margin readout -->
                                            <div
                                                v-if="margin"
                                                class="flex items-center justify-between px-3 py-2 text-sm rounded-lg"
                                                :class="margin.belowCost
                                                    ? 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400'
                                                    : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400'"
                                            >
                                                <span class="font-medium">
                                                    {{ margin.belowCost ? __("Priced below cost") : __("Profit") }}
                                                </span>
                                                <Ltr class="font-semibold">{{ margin.profit.toFixed(2) }} {{ product.currency }} ({{ margin.pct.toFixed(0) }}%)</Ltr>
                                            </div>

                                            <div>
                                                <InputLabel
                                                    for="currency"
                                                    :value="__('Currency')"
                                                />
                                                <select
                                                    id="currency"
                                                    v-model="product.currency"
                                                    class="block w-full px-3 py-2 mt-1 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                                                >
                                                    <option v-for="code in currencies" :key="code" :value="code">{{ code }}</option>
                                                </select>
                                                <InputError
                                                    class="mt-1"
                                                    :message="product.errors.currency"
                                                />
                                            </div>
                                        </div>

                                        <!-- Stock & alerts group -->
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <InputLabel
                                                    for="expire_date"
                                                    :value="__('Expire Date')"
                                                />
                                                <DatePicker
                                                    id="expire_date"
                                                    v-model="product.expire_date"
                                                    class="block w-full mt-1 rtl:text-right"
                                                />
                                                <InputError
                                                    class="mt-1"
                                                    :message="product.errors.expire_date"
                                                />
                                            </div>
                                            <div>
                                                <InputLabel
                                                    for="alert_quantity"
                                                    :value="__('Alert Quantity')"
                                                />
                                                <TextInput
                                                    id="alert_quantity"
                                                    v-model="product.alert_quantity"
                                                    type="number" inputmode="numeric"
                                                    min="0"
                                                    dir="ltr"
                                                    class="block w-full mt-1"
                                                    :placeholder="__('3')"
                                                />
                                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                                    {{ __("Alerts you when stock drops to this level or below.") }}
                                                </p>
                                                <InputError
                                                    class="mt-1"
                                                    :message="
                                                        product.errors.alert_quantity
                                                    "
                                                />
                                            </div>
                                        </div>

                                        <!-- Stock per location (add + edit) -->
                                        <div v-if="props.storages.length">
                                            <div class="flex items-center justify-between gap-x-2">
                                                <InputLabel :value="__('Stock per location')" />
                                                <span
                                                    v-if="!manualStockAllowed"
                                                    class="text-xs text-gray-400 dark:text-gray-500 rtl:text-right"
                                                >
                                                    {{ __("Stock is managed through purchase invoices.") }}
                                                </span>
                                            </div>
                                            <p
                                                v-if="manualStockAllowed"
                                                class="mt-1 text-xs text-gray-400 dark:text-gray-500"
                                            >
                                                {{ __("Saving records the difference as a stock adjustment.") }}
                                            </p>
                                            <div class="mt-2 space-y-2 overflow-y-auto max-h-60 ltr:pr-1 rtl:pl-1">
                                                <div
                                                    v-for="(row, index) in product.quantities"
                                                    :key="row.storage_id"
                                                    class="flex items-center gap-3"
                                                >
                                                    <div class="flex items-center flex-1 min-w-0 gap-2">
                                                        <span class="text-sm text-gray-700 dark:text-gray-300 truncate">
                                                            {{ storageName(row.storage_id) }}
                                                        </span>
                                                        <span
                                                            class="shrink-0 px-1.5 py-0.5 text-[9px] font-medium rounded-md"
                                                            :class="storageType(row.storage_id) === 'warehouse'
                                                                ? 'bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400'
                                                                : 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/20 dark:text-emerald-400'"
                                                        >
                                                            {{ storageType(row.storage_id) === 'warehouse' ? __('Warehouse') : __('Sale Point') }}
                                                        </span>
                                                    </div>
                                                    <TextInput
                                                        v-model="row.quantity"
                                                        type="number" inputmode="numeric"
                                                        min="0"
                                                        :disabled="!manualStockAllowed"
                                                        class="w-28 shrink-0"
                                                        :class="{ 'opacity-60 cursor-not-allowed': !manualStockAllowed }"
                                                    />
                                                    <InputError
                                                        :message="product.errors['quantities.' + index + '.quantity']"
                                                    />
                                                </div>
                                            </div>
                                        </div>

                                        <div>
                                            <InputLabel
                                                for="categories"
                                                :value="__('Categories')"
                                            />
                                            <CustomSelect
                                                id="categories"
                                                v-model="product.categories"
                                                :options="props.categories"
                                                class="mt-1"
                                                multiple
                                                :taggable="true"
                                                :close-on-select="false"
                                                label="name"
                                                track-by="id"
                                                @tag="addCategory"
                                            />
                                            <InputError
                                                class="mt-1"
                                                :message="product.errors.categories"
                                            />
                                        </div>
                                    </div>

                                    <!-- Right column: units (collapsible) -->
                                    <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg self-start">
                                        <button
                                            type="button"
                                            class="flex items-center justify-between w-full"
                                            :aria-expanded="showUnits"
                                            @click="showUnits = !showUnits"
                                        >
                                            <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                                {{ __("Units") }}
                                                <span v-if="product.units.length" class="text-emerald-500">({{ product.units.length }})</span>
                                            </span>
                                            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="{ 'rotate-180': showUnits }" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                                        </button>

                                        <!-- Empty state -->
                                        <div v-if="!showUnits" class="mt-3">
                                            <p class="text-xs text-gray-400 dark:text-gray-500">
                                                {{ __("Define alternative units (e.g. a box of 12 pieces) so you can sell or purchase in bulk. Optional — a base unit is created automatically.") }}
                                            </p>
                                            <button
                                                type="button"
                                                class="mt-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:text-emerald-700"
                                                @click="showUnits = true; product.units.length === 0 && addUnit()"
                                            >
                                                + {{ __("Add a unit") }}
                                            </button>
                                        </div>

                                        <div v-show="showUnits" class="mt-4 space-y-4 overflow-y-auto max-h-72">
                                            <div
                                                v-for="(
                                                    unit, index
                                                ) in product.units"
                                                :key="`unit-` + index"
                                                class="pb-4 space-y-3 border-b border-gray-100 dark:border-gray-800 last:border-0 last:pb-0"
                                            >
                                                <div>
                                                    <InputLabel
                                                        for="unit name"
                                                        :value="__('Unit Name')"
                                                    />
                                                    <TextInput
                                                        v-model="unit.name"
                                                        class="block w-full mt-1"
                                                        :placeholder="
                                                            __('Unit eg: box')
                                                        "
                                                    />
                                                    <InputError
                                                        class="mt-1"
                                                        :message="
                                                            product.errors[
                                                                `units.${index}.name`
                                                            ]
                                                        "
                                                    />
                                                </div>

                                                <div>
                                                    <InputLabel
                                                        for="conversion_factor"
                                                        :value="
                                                            __(
                                                                'Unit Conversion Factor'
                                                            )
                                                        "
                                                    />
                                                    <TextInput
                                                        v-model="
                                                            unit.conversion_factor
                                                        "
                                                        class="block w-full mt-1"
                                                        type="number" inputmode="decimal"
                                                        min="1"
                                                        :placeholder="
                                                            __(
                                                                'Unit Conversion Factor'
                                                            )
                                                        "
                                                    />
                                                    <InputError
                                                        class="mt-1"
                                                        :message="
                                                            product.errors[
                                                                `units.${index}.conversion_factor`
                                                            ]
                                                        "
                                                    />
                                                </div>

                                                <button
                                                    v-if="
                                                        index ==
                                                        product.units.length - 1
                                                    "
                                                    type="button"
                                                    class="inline-flex items-center justify-center w-full px-4 py-2 text-sm font-normal text-gray-700 bg-white border border-gray-300 rounded-lg dark:text-gray-300 dark:bg-gray-800 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors duration-200"
                                                    @click="addUnit"
                                                >
                                                    {{ __("Add Unit") }}
                                                </button>
                                            </div>

                                            <button
                                                v-if="product.units.length === 0"
                                                type="button"
                                                class="inline-flex items-center justify-center w-full px-4 py-2 text-sm font-normal text-gray-700 bg-white border border-gray-300 rounded-lg dark:text-gray-300 dark:bg-gray-800 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors duration-200"
                                                @click="addUnit"
                                            >
                                                {{ __("Add Unit") }}
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Footer -->
                                <div
                                    class="flex items-center justify-end pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-x-3"
                                >
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 bg-white border border-gray-300 rounded-lg dark:text-gray-300 dark:bg-gray-800 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                                        @click="cancel"
                                    >
                                        {{ __("Cancel") }}
                                    </button>

                                    <PrimaryButton
                                        :class="{
                                            'opacity-50': product.processing,
                                        }"
                                        :disabled="product.processing"
                                    >
                                        {{ isEditing ? __("Save") : __("Add") }}
                                    </PrimaryButton>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </transition>
        </div>
    </div>
</template>
