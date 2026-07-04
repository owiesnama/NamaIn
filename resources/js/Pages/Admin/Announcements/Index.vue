<script setup>
import { ref, watch } from "vue";
import { router, useForm } from "@inertiajs/vue3";
import axios from "axios";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import TextInput from "@/Components/TextInput.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import CustomSelect from "@/Components/CustomSelect.vue";
import { useDate } from "@/Composables/useDate";

const props = defineProps({
    announcements: Object,
    tenants: Array,
    users: Object,
    filters: Object,
});

const { formatDate } = useDate();

const audiences = [
    { value: "all", label: "All users" },
    { value: "tenant", label: "Users of a tenant" },
    { value: "owners", label: "Owners only" },
    { value: "tenant_role", label: "Role within a tenant" },
    { value: "users", label: "Specific users" },
];

const audienceLabel = (value) => __(audiences.find((a) => a.value === value)?.label ?? value);

const form = useForm({
    subject: "",
    body: "",
    audience_type: "all",
    tenant_id: null,
    role_id: null,
    user_ids: [],
    send_email: false,
});

const roles = ref([]);
const rolesLoading = ref(false);

watch(
    () => [form.tenant_id, form.audience_type],
    async ([tenantId, audienceType]) => {
        form.role_id = null;
        roles.value = [];

        if (audienceType !== "tenant_role" || !tenantId) return;

        rolesLoading.value = true;

        try {
            const { data } = await axios.get(route("admin.tenants.roles", tenantId));
            roles.value = data;
        } finally {
            rolesLoading.value = false;
        }
    }
);

// Recipient filters (specific-users mode)
const search = ref(props.filters?.search || "");
const verified = ref(props.filters?.verified || "");
const ownersOnly = ref(!!props.filters?.owners_only);
let debounceTimer = null;

watch([search, verified, ownersOnly], () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get(route("admin.announcements.index"), {
            search: search.value || undefined,
            verified: verified.value || undefined,
            owners_only: ownersOnly.value || undefined,
        }, { preserveState: true, replace: true });
    }, 300);
});

const isSelected = (id) => form.user_ids.includes(id);

const toggle = (id) => {
    if (isSelected(id)) {
        form.user_ids = form.user_ids.filter((uid) => uid !== id);
    } else {
        form.user_ids = [...form.user_ids, id];
    }
};

const selectAllOnPage = () => {
    const ids = props.users.data.map((u) => u.id);
    const allSelected = ids.every((id) => isSelected(id));
    if (allSelected) {
        form.user_ids = form.user_ids.filter((id) => !ids.includes(id));
    } else {
        form.user_ids = [...new Set([...form.user_ids, ...ids])];
    }
};

const clearSelection = () => {
    form.user_ids = [];
};

const send = () => {
    form.post(route("admin.announcements.store"), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <AdminLayout :title="__('Announcements')">
        <!-- Page header -->
        <div class="w-full lg:flex lg:items-center lg:justify-between mb-8">
            <div>
                <div class="flex items-center gap-x-3">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-white">{{ __('Announcements') }}</h2>
                    <span class="px-3 py-1 text-xs font-semibold rounded-full text-gray-700 bg-gray-100 dark:bg-gray-800 dark:text-gray-300">
                        {{ announcements.total }} {{ __('sent') }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Send in-app news to all users, a group, or specific users') }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-4">
                <!-- Recipient picker (specific-users mode) -->
                <template v-if="form.audience_type === 'users'">
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="w-full sm:w-64">
                            <TextInput
                                v-model="search"
                                type="text"
                                :placeholder="__('Search by name or email...')"
                                class="w-full"
                            />
                        </div>
                        <select
                            v-model="verified"
                            class="px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-gray-400 focus:ring focus:ring-gray-200 focus:ring-opacity-50"
                        >
                            <option value="">{{ __('All users') }}</option>
                            <option value="unverified">{{ __('Unverified only') }}</option>
                            <option value="verified">{{ __('Verified only') }}</option>
                        </select>
                        <label class="inline-flex items-center gap-x-2 text-sm text-gray-700 dark:text-gray-300">
                            <input
                                v-model="ownersOnly"
                                type="checkbox"
                                class="border-gray-300 dark:border-gray-600 rounded text-emerald-600 focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                            />
                            {{ __('Owners only') }}
                        </label>
                    </div>

                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50/50 dark:bg-gray-800/40">
                                    <tr>
                                        <th class="px-6 py-4 text-start">
                                            <input
                                                type="checkbox"
                                                :checked="users.data.length > 0 && users.data.every((u) => isSelected(u.id))"
                                                class="border-gray-300 dark:border-gray-600 rounded text-emerald-600 focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                                                @change="selectAllOnPage"
                                            />
                                        </th>
                                        <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Name') }}</th>
                                        <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Email') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200/60 dark:divide-gray-700/60 bg-white dark:bg-gray-900">
                                    <tr
                                        v-for="user in users.data"
                                        :key="user.id"
                                        class="group hover:bg-gray-50 dark:hover:bg-gray-800 transition-all duration-200 cursor-pointer"
                                        @click="toggle(user.id)"
                                    >
                                        <td class="px-6 py-4 whitespace-nowrap" @click.stop>
                                            <input
                                                type="checkbox"
                                                :checked="isSelected(user.id)"
                                                class="border-gray-300 dark:border-gray-600 rounded text-emerald-600 focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                                                @change="toggle(user.id)"
                                            />
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                            {{ user.name }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" dir="ltr">
                                            {{ user.email }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-if="users.data.length === 0" class="py-12 text-center text-sm text-gray-400 dark:text-gray-500">
                            {{ __('No users found.') }}
                        </div>
                    </div>

                    <div v-if="users.links && users.links.length > 3" class="flex flex-wrap items-center gap-1">
                        <template v-for="link in users.links" :key="link.label">
                            <component
                                :is="link.url ? 'a' : 'span'"
                                :href="link.url"
                                class="px-4 py-2.5 text-sm leading-4 border rounded-md transition-colors duration-200"
                                :class="[
                                    link.active
                                        ? 'bg-gray-900 dark:bg-white text-white dark:text-gray-900 border-gray-900 dark:border-white'
                                        : link.url
                                            ? 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800'
                                            : 'text-gray-400 dark:text-gray-600 border-gray-200 dark:border-gray-700 cursor-not-allowed'
                                ]"
                                @click.prevent="link.url && router.get(link.url, {}, { preserveState: true })"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </template>

                <!-- Sent history -->
                <template v-else>
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50/50 dark:bg-gray-800/40">
                                    <tr>
                                        <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Subject') }}</th>
                                        <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Audience') }}</th>
                                        <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Recipients') }}</th>
                                        <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Sent at') }}</th>
                                        <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Sent by') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200/60 dark:divide-gray-700/60 bg-white dark:bg-gray-900">
                                    <tr
                                        v-for="announcement in announcements.data"
                                        :key="announcement.id"
                                        class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-all duration-200"
                                    >
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">
                                            <div class="flex items-center gap-x-2">
                                                <span>{{ announcement.subject }}</span>
                                                <span
                                                    v-if="announcement.send_email"
                                                    class="px-1.5 py-0.5 text-[9px] font-medium bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400 rounded-md"
                                                >
                                                    {{ __('Email') }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ audienceLabel(announcement.audience_type) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                            {{ announcement.recipients_count }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ announcement.sent_at ? formatDate(announcement.sent_at) : __('Queued') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ announcement.admin?.name }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-if="announcements.data.length === 0" class="py-12 text-center text-sm text-gray-400 dark:text-gray-500">
                            {{ __('No announcements sent yet.') }}
                        </div>
                    </div>

                    <div v-if="announcements.links && announcements.links.length > 3" class="flex flex-wrap items-center gap-1">
                        <template v-for="link in announcements.links" :key="link.label">
                            <component
                                :is="link.url ? 'a' : 'span'"
                                :href="link.url"
                                class="px-4 py-2.5 text-sm leading-4 border rounded-md transition-colors duration-200"
                                :class="[
                                    link.active
                                        ? 'bg-gray-900 dark:bg-white text-white dark:text-gray-900 border-gray-900 dark:border-white'
                                        : link.url
                                            ? 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800'
                                            : 'text-gray-400 dark:text-gray-600 border-gray-200 dark:border-gray-700 cursor-not-allowed'
                                ]"
                                @click.prevent="link.url && router.get(link.url, {}, { preserveState: true })"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </template>
            </div>

            <!-- Compose -->
            <div class="lg:col-span-1">
                <div class="sticky top-4 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('Compose Announcement') }}</h3>
                    </div>
                    <form class="p-6 space-y-4" @submit.prevent="send">
                        <div>
                            <InputLabel :value="__('Subject')" />
                            <TextInput v-model="form.subject" type="text" class="w-full mt-1" required />
                            <InputError :message="form.errors.subject" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel :value="__('Message')" />
                            <textarea
                                v-model="form.body"
                                rows="6"
                                class="w-full mt-1 px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                                required
                            ></textarea>
                            <InputError :message="form.errors.body" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel :value="__('Audience')" />
                            <div class="mt-1 space-y-1">
                                <label
                                    v-for="audience in audiences"
                                    :key="audience.value"
                                    class="flex min-h-[44px] cursor-pointer items-center gap-x-3 rounded-lg border px-3 py-2 transition-colors"
                                    :class="form.audience_type === audience.value
                                        ? 'border-emerald-300 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-900/10'
                                        : 'border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800'"
                                >
                                    <input
                                        v-model="form.audience_type"
                                        type="radio"
                                        :value="audience.value"
                                        class="border-gray-300 dark:border-gray-600 text-emerald-600 focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                                    />
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ __(audience.label) }}</span>
                                </label>
                            </div>
                            <InputError :message="form.errors.audience_type" class="mt-1" />
                        </div>

                        <div v-if="form.audience_type === 'tenant' || form.audience_type === 'tenant_role'">
                            <InputLabel :value="__('Tenant')" />
                            <CustomSelect
                                v-model="form.tenant_id"
                                :options="tenants"
                                label="name"
                                track-by="id"
                                class="w-full mt-1"
                                :placeholder="__('Select a tenant')"
                            />
                            <InputError :message="form.errors.tenant_id" class="mt-1" />
                        </div>

                        <div v-if="form.audience_type === 'tenant_role'">
                            <InputLabel :value="__('Role')" />
                            <CustomSelect
                                v-model="form.role_id"
                                :options="roles"
                                label="name"
                                track-by="id"
                                :loading="rolesLoading"
                                class="w-full mt-1"
                                :placeholder="form.tenant_id ? __('Select a role') : __('Select a tenant first')"
                            />
                            <InputError :message="form.errors.role_id" class="mt-1" />
                        </div>

                        <div v-if="form.audience_type === 'users'" class="flex items-center justify-between">
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ form.user_ids.length }} {{ __('recipient(s) selected') }}
                            </p>
                            <button
                                v-if="form.user_ids.length"
                                type="button"
                                class="text-xs font-medium text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300"
                                @click="clearSelection"
                            >
                                {{ __('Clear') }}
                            </button>
                        </div>
                        <InputError :message="form.errors.user_ids" />

                        <label class="flex min-h-[44px] cursor-pointer items-center gap-x-3 rounded-lg border border-gray-200 px-3 py-2 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                            <input
                                v-model="form.send_email"
                                type="checkbox"
                                class="border-gray-300 dark:border-gray-600 rounded text-emerald-600 focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                            />
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Also send as email') }}</span>
                        </label>
                        <InputError :message="form.errors.send_email" />

                        <button
                            type="submit"
                            class="w-full inline-flex items-center justify-center px-4 py-2 min-h-[44px] text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                            :disabled="form.processing || (form.audience_type === 'users' && form.user_ids.length === 0)"
                        >
                            {{ __('Send announcement') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
