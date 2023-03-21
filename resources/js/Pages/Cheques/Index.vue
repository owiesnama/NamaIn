<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import { Link, useForm } from "@inertiajs/vue3";
    import { router } from "@inertiajs/vue3";
    import FileUploadButton from "@/Shared/FileUploadButton.vue";
    import TextInput from "@/Components/TextInput.vue";
    import Cheque from "@/Shared/Cheque.vue";
    import SelectBox from "@/Shared/SelectBox.vue";
    import { useQueryString } from "@/Composables/useQueryString";
    import { watch } from "vue";
    import { debounce } from "lodash";

    defineProps({
        cheques: Object,
    });
    let form = useForm({
        file: null,
    });
    let submit = (files) => {
        form.file = files[0];
        form.post(route("cheques.import"));
    };

    let search = useQueryString("search");
    let chequeType = useQueryString("chequeType");
    watch(
        search,
        debounce(function (value) {
            router.get(
                "/cheques",
                { search: value, chequeType: chequeType.value },
                { preserveState: true }
            );
        }, 300)
    );
    watch(
        chequeType,
        debounce(function (value) {
            router.get(
                "/cheques",
                { chequeType: value, search: search.value },
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
                        <div>
                            <TextInput
                                v-model="search"
                                type="text"
                                placeholder="Search here ..."
                            ></TextInput>
                            <SelectBox
                                class="w-52"
                                v-model="chequeType"
                            >
                                <option value="">All</option>
                                <option value="1">Credit</option>
                                <option value="0">Debit</option>
                            </SelectBox>
                        </div>
                        <div>
                            <Link
                                href="/cheques/create"
                                as="Button"
                                class="inline-flex items-center px-4 py-3 bg-gray-100 border border-gray-200 rounded font-semibold text-sm text-gray-900 uppercase tracking-widest hover:bg-gray-50 active:bg-gray-200 focus:outline-none focus:border-gray-200 focus:ring focus:ring-gray-300 disabled:opacity-25 transition mr-2"
                                >New Cheque</Link
                            >
                            <FileUploadButton @input="submit"
                                >Import From CSV</FileUploadButton
                            >
                        </div>
                    </form>
                </div>

                <Cheque
                    v-for="cheque in cheques"
                    :cheque="cheque"
                ></Cheque>
            </div>
        </div>
    </AppLayout>
</template>
