<script setup>
    import { useForm } from "@inertiajs/vue3";
    import { ref } from "vue";
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
    });

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
    });

    const isEditing = !!props.product;

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
        product.reset();
        show.value = false;
    };
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
                            class="relative w-full px-6 pt-6 pb-6 overflow-hidden text-left transition-all transform bg-white border border-gray-200 shadow-xl dark:bg-gray-900 dark:border-gray-700 rounded-xl sm:my-8 sm:max-w-2xl"
                        >
                            <!-- Header -->
                            <div class="flex items-start justify-between gap-x-4">
                                <div class="rtl:text-right">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
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
                                                        type="number"
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
                                                        type="number"
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
                                            <div>
                                                <InputLabel
                                                    for="currency"
                                                    :value="__('Currency')"
                                                />
                                                <TextInput
                                                    id="currency"
                                                    :model-value="product.currency"
                                                    @update:model-value="setCurrency"
                                                    type="text"
                                                    maxlength="3"
                                                    class="block w-full mt-1 uppercase"
                                                    :placeholder="__('SDG')"
                                                />
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
                                                    type="number"
                                                    min="0"
                                                    class="block w-full mt-1"
                                                    :placeholder="__('3')"
                                                />
                                                <InputError
                                                    class="mt-1"
                                                    :message="
                                                        product.errors.alert_quantity
                                                    "
                                                />
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

                                    <!-- Right column: units -->
                                    <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 rtl:text-right">
                                            {{ __("Units") }}
                                        </p>

                                        <div class="mt-4 space-y-4 overflow-y-auto max-h-72">
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
                                                        type="number"
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
