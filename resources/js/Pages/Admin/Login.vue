<script setup>
    import { computed } from "vue";
    import { Head, useForm, usePage } from "@inertiajs/vue3";
    import ApplicationLogo from "@/Components/ApplicationLogo.vue";

    const direction = computed(() => {
        return usePage().props.locale === "ar" ? "rtl" : "ltr";
    });

    const form = useForm({
        email: "",
        password: "",
        remember: false,
    });

    const submit = () => {
        form.post(route("admin.login"), {
            onFinish: () => form.reset("password"),
        });
    };
</script>

<template>
    <div :dir="direction" class="flex min-h-screen items-center justify-center bg-gray-50 dark:bg-gray-950 px-4">
        <Head title="Admin Login" />

        <div class="w-full max-w-sm">
            <!-- Logo + title -->
            <div class="mb-8 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-900 dark:bg-white">
                    <ApplicationLogo class="h-8 w-auto brightness-0 invert dark:brightness-100 dark:invert-0" />
                </div>
                <h1 class="mt-4 text-xl font-bold text-gray-900 dark:text-white">Admin Panel</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Sign in to manage the platform</p>
            </div>

            <!-- Login form -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-6">
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">
                            Email
                        </label>
                        <input
                            v-model="form.email"
                            type="email"
                            required
                            autofocus
                            class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-gray-400 focus:ring focus:ring-gray-200 focus:ring-opacity-50 dark:focus:ring-gray-700 placeholder-gray-400 dark:placeholder-gray-600"
                            placeholder="admin@example.com"
                        />
                        <p v-if="form.errors.email" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">
                            Password
                        </label>
                        <input
                            v-model="form.password"
                            type="password"
                            required
                            class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-gray-400 focus:ring focus:ring-gray-200 focus:ring-opacity-50 dark:focus:ring-gray-700 placeholder-gray-400 dark:placeholder-gray-600"
                            placeholder="••••••••"
                        />
                        <p v-if="form.errors.password" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.password }}</p>
                    </div>

                    <div class="flex items-center">
                        <input
                            v-model="form.remember"
                            type="checkbox"
                            class="border-gray-300 dark:border-gray-600 rounded text-gray-900 dark:text-white focus:border-gray-400 focus:ring focus:ring-gray-200 focus:ring-opacity-50"
                        />
                        <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Remember me</span>
                    </div>

                    <button
                        type="submit"
                        class="w-full inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-white bg-gray-900 dark:bg-white dark:text-gray-900 border border-transparent rounded-lg hover:bg-gray-800 dark:hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Signing in...' : 'Sign in' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
