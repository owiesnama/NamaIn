<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    defineProps(["total_sales", "total_purchase", "transactions"]);

    const quantityForHumans = (transaction) => {
        if (!transaction.unit) {
            return `${transaction.quantity} <strong>(Base unit)</strong>`;
        }
        return `${transaction.quantity} <storng>(${transaction.unit?.name})</storng>`;
    };
</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ __("Dashboard") }}
            </h2>
        </template>

        <div>
            <h3 class="text-base font-semibold leading-6 text-gray-900">
                {{ __("Last 30 days") }}
            </h3>
            <dl
                class="grid grid-cols-1 mt-5 overflow-hidden bg-white divide-y divide-gray-200 rounded-lg shadow md:grid-cols-2 md:divide-y-0 md:divide-x"
            >
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-base font-normal text-gray-900">
                        {{ __("Total Sales") }}
                    </dt>
                    <dd
                        class="flex items-baseline justify-between mt-1 md:block lg:flex"
                    >
                        <div
                            class="flex items-baseline text-2xl font-semibold text-indigo-600"
                        >
                            {{ total_sales }}
                        </div>
                    </dd>
                </div>

                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-base font-normal text-gray-900">
                        {{ __("Total Purchase") }}
                    </dt>
                    <dd
                        class="flex items-baseline justify-between mt-1 md:block lg:flex"
                    >
                        <div
                            class="flex items-baseline text-2xl font-semibold text-indigo-600"
                        >
                            {{ total_purchase }}
                        </div>
                    </dd>
                </div>
            </dl>
        </div>

        <div class="flex flex-col mt-8">
            <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                <div
                    class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8"
                >
                    <div
                        class="overflow-hidden border border-gray-200 rounded-lg dark:border-gray-700"
                    >
                        <table
                            class="min-w-full divide-y divide-gray-200 dark:divide-gray-700"
                        >
                            <thead class="bg-gray-100">
                                <tr>
                                    <th
                                        scope="col"
                                        class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400"
                                    >
                                        {{ __("The Product") }}
                                    </th>

                                    <th
                                        scope="col"
                                        class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400"
                                    >
                                        {{ __("Storage") }}
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400"
                                    >
                                        {{ __("Quantity") }}
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400"
                                    >
                                        {{ __("Type") }}
                                    </th>

                                    <th
                                        scope="col"
                                        class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400"
                                    >
                                        {{ __("Created At") }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-900"
                            >
                                <template v-if="transactions">
                                    <tr
                                        v-for="transaction in transactions"
                                        :key="transaction.id"
                                    >
                                        <th
                                            class="px-8 py-3 text-sm text-left rtl:text-right text-gray-800 whitespace-nowrap"
                                            v-text="transaction.product.name"
                                        ></th>

                                        <td
                                            class="px-8 py-3 text-sm text-left rtl:text-right whitespace-nowrap"
                                            v-text="transaction.storage.name"
                                        ></td>

                                        <td
                                            class="px-8 py-3 text-sm text-left rtl:text-right whitespace-nowrap"
                                            v-html="quantityForHumans(transaction)"
                                        ></td>
                                        <th
                                            class="px-8 py-3 text-sm text-left rtl:text-right text-gray-700 whitespace-nowrap"
                                            v-text="__(transaction.type)"
                                        ></th>

                                        <td
                                            class="px-8 py-3 text-sm text-left rtl:text-right text-gray-700 whitespace-nowrap"
                                            v-text="transaction.created_at"
                                        ></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <EmptySearch :data="transaction"></EmptySearch>
        </div>
    </AppLayout>
</template>
