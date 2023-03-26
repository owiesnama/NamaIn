<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import { Link } from "@inertiajs/vue3";
    import { router } from "@inertiajs/vue3";
    import TextInput from "@/Components/TextInput.vue";
    import Cheque from "@/Shared/Cheque.vue";
    import SelectBox from "@/Shared/SelectBox.vue";
    import { useQueryString } from "@/Composables/useQueryString";
    import { watch, reactive } from "vue";
    import { debounce } from "lodash";

    defineProps({
        cheques: Object,
        status: Object,
    });
    let filters = reactive({
        search: useQueryString("search"),
        type: useQueryString("type"),
        status: useQueryString("status"),
        due: useQueryString("due"),
    });

    watch(
        filters,
        debounce(function (watchedFitlers) {
            router.get(
                route("cheques.index"),
                { ...watchedFitlers },
                { preserveState: true }
            );
        }, 300)
    );
</script>

<template>
    <AppLayout title="Products">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Products
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="mb-4">
                    <form class="flex justify-between items-center">
                        <div class="space-x-2">
                            <TextInput
                                v-model="filters.search"
                                type="text"
                                placeholder="Search here ..."
                            ></TextInput>
                            <SelectBox
                                v-model="filters.type"
                                class="w-52"
                            >
                                <option value="">All</option>
                                <option value="1">Credit</option>
                                <option value="0">Debit</option>
                            </SelectBox>

                            <TextInput
                                v-model="filters.due"
                                type="date"
                                placeholder="Due before.."
                            />
                        </div>
                        <div>
                            <Link
                                href="/cheques/create"
                                as="Button"
                                class="inline-flex items-center px-4 py-3 bg-gray-100 border border-gray-200 rounded font-semibold text-sm text-gray-900 uppercase tracking-widest hover:bg-gray-50 active:bg-gray-200 focus:outline-none focus:border-gray-200 focus:ring focus:ring-gray-300 disabled:opacity-25 transition mr-2"
                                >New Cheque</Link
                            >
                        </div>
                    </form>
                </div>

                <Cheque
                    v-for="cheque in cheques"
                    :key="cheque.id"
                    :cheque="cheque"
                    :cheque-status="status"
                ></Cheque>
            </div>
        </div>
    </AppLayout>
</template>
