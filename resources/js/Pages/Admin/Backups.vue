<script setup>
    import { ref, watch } from "vue";
    import { router, useForm } from "@inertiajs/vue3";
    import AdminLayout from "@/Layouts/AdminLayout.vue";
    import { useDate } from '@/Composables/useDate';

    const props = defineProps({
        backups: Object,
        tenants: Array,
        settings: Object,
        filters: Object,
    });

    const typeFilter = ref(props.filters?.type || "");
    const statusFilter = ref(props.filters?.status || "");
    let debounceTimer = null;

    watch([typeFilter, statusFilter], () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            router.get(route("admin.backups.index"), {
                type: typeFilter.value || undefined,
                status: statusFilter.value || undefined,
            }, { preserveState: true, replace: true });
        }, 300);
    });

    // Schedule settings form
    const settingsForm = useForm({
        is_enabled: props.settings?.is_enabled ?? false,
        frequency: props.settings?.frequency ?? 'daily',
        cron_expression: props.settings?.cron_expression ?? '',
        retention_count: props.settings?.retention_count ?? 5,
    });

    const saveSettings = () => {
        settingsForm.put(route('admin.backups.settings'), {
            preserveScroll: true,
        });
    };

    // Backup forms
    const selectedTenantId = ref('');
    const selectedFormat = ref('sql');
    const backupTenantLoading = ref(false);
    const backupFullLoading = ref(false);

    const backupTenant = () => {
        if (!selectedTenantId.value) return;
        backupTenantLoading.value = true;
        router.post(route('admin.backups.store'), {
            type: 'tenant',
            format: selectedFormat.value,
            tenant_id: selectedTenantId.value,
        }, {
            preserveScroll: true,
            onFinish: () => { backupTenantLoading.value = false; },
        });
    };

    const backupFull = () => {
        backupFullLoading.value = true;
        router.post(route('admin.backups.store'), {
            type: 'full',
            format: 'dump',
        }, {
            preserveScroll: true,
            onFinish: () => { backupFullLoading.value = false; },
        });
    };

    const deleteBackup = (backup) => {
        if (!confirm(__('Are you sure you want to delete this backup?'))) return;
        router.delete(route('admin.backups.destroy', backup.id), {
            preserveScroll: true,
        });
    };

    const statusStyle = (status) => {
        const styles = {
            completed: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800',
            running: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/20 dark:text-amber-400 dark:border-amber-800',
            pending: 'bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700',
            failed: 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800',
        };
        return styles[status] || styles.pending;
    };

    const frequencyLabel = (freq) => {
        const labels = { daily: __('Daily'), weekly: __('Weekly'), monthly: __('Monthly'), custom: __('Custom') };
        return labels[freq] || freq;
    };

    const { formatDate } = useDate();
</script>

<template>
    <AdminLayout :title="__('Backups')">
        <!-- Page header -->
        <div class="mb-8">
            <div class="flex items-center gap-x-3">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-white">{{ __('Backups') }}</h2>
                <span class="px-3 py-1 text-xs font-semibold rounded-full text-emerald-700 bg-emerald-100/60 dark:bg-gray-800 dark:text-emerald-400">
                    {{ backups.total }} {{ __('Total') }}
                </span>
            </div>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Manage database backups for tenants and the full system') }}</p>
        </div>

        <!-- Schedule Settings -->
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl mb-6">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('Scheduled Backup') }}</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Configure automatic full database backups') }}</p>
            </div>
            <div class="p-6">
                <div class="flex flex-wrap items-end gap-4">
                    <!-- Enable toggle -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 rtl:text-right">{{ __('Status') }}</label>
                        <button
                            type="button"
                            class="inline-flex items-center gap-x-2 px-4 py-2 text-sm border rounded-lg transition-colors duration-200"
                            :class="settingsForm.is_enabled
                                ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800'
                                : 'bg-gray-100 text-gray-600 border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700'"
                            @click="settingsForm.is_enabled = !settingsForm.is_enabled"
                        >
                            <span class="h-2 w-2 rounded-full" :class="settingsForm.is_enabled ? 'bg-emerald-500' : 'bg-gray-400'"></span>
                            {{ settingsForm.is_enabled ? __('Enabled') : __('Disabled') }}
                        </button>
                    </div>

                    <!-- Frequency -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 rtl:text-right">{{ __('Frequency') }}</label>
                        <select
                            v-model="settingsForm.frequency"
                            class="px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                        >
                            <option value="daily">{{ __('Daily') }}</option>
                            <option value="weekly">{{ __('Weekly') }}</option>
                            <option value="monthly">{{ __('Monthly') }}</option>
                            <option value="custom">{{ __('Custom Cron') }}</option>
                        </select>
                    </div>

                    <!-- Cron expression (shown for custom) -->
                    <div v-if="settingsForm.frequency === 'custom'">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 rtl:text-right">{{ __('Cron Expression') }}</label>
                        <input
                            v-model="settingsForm.cron_expression"
                            type="text"
                            placeholder="0 0 * * *"
                            class="w-40 px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 placeholder-gray-400 dark:placeholder-gray-600"
                        />
                    </div>

                    <!-- Retention count -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 rtl:text-right">{{ __('Keep Last') }}</label>
                        <input
                            v-model.number="settingsForm.retention_count"
                            type="number"
                            min="1"
                            max="100"
                            class="w-20 px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                        />
                    </div>

                    <!-- Save button -->
                    <button
                        type="button"
                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                        :disabled="settingsForm.processing"
                        @click="saveSettings"
                    >
                        {{ __('Save Settings') }}
                    </button>
                </div>
                <p v-if="settingsForm.errors && Object.keys(settingsForm.errors).length" class="mt-2 text-sm text-red-600 dark:text-red-400">
                    {{ Object.values(settingsForm.errors)[0] }}
                </p>
            </div>
        </div>

        <!-- On-demand Backup Actions -->
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl mb-6">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('On-Demand Backup') }}</h3>
            </div>
            <div class="p-6">
                <div class="flex flex-wrap items-end gap-4">
                    <!-- Tenant backup -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 rtl:text-right">{{ __('Tenant') }}</label>
                        <select
                            v-model="selectedTenantId"
                            class="px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                        >
                            <option value="">{{ __('Select tenant...') }}</option>
                            <option v-for="tenant in tenants" :key="tenant.id" :value="tenant.id">
                                {{ tenant.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 rtl:text-right">{{ __('Format') }}</label>
                        <select
                            v-model="selectedFormat"
                            class="px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                        >
                            <option value="sql">SQL</option>
                            <option value="json">JSON</option>
                        </select>
                    </div>

                    <button
                        type="button"
                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                        :disabled="!selectedTenantId || backupTenantLoading"
                        @click="backupTenant"
                    >
                        <svg v-if="backupTenantLoading" class="animate-spin ltr:-ml-1 rtl:-mr-1 ltr:mr-2 rtl:ml-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ __('Backup Tenant') }}
                    </button>

                    <!-- Divider -->
                    <div class="hidden sm:block h-8 w-px bg-gray-200 dark:bg-gray-700"></div>

                    <!-- Full backup -->
                    <button
                        type="button"
                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                        :disabled="backupFullLoading"
                        @click="backupFull"
                    >
                        <svg v-if="backupFullLoading" class="animate-spin ltr:-ml-1 rtl:-mr-1 ltr:mr-2 rtl:ml-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ __('Full Database Backup') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-4 mb-6">
            <select
                v-model="typeFilter"
                class="px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-gray-400 focus:ring focus:ring-gray-200 focus:ring-opacity-50"
            >
                <option value="">{{ __('All types') }}</option>
                <option value="tenant">{{ __('Tenant') }}</option>
                <option value="full">{{ __('Full') }}</option>
            </select>
            <select
                v-model="statusFilter"
                class="px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-gray-400 focus:ring focus:ring-gray-200 focus:ring-opacity-50"
            >
                <option value="">{{ __('All statuses') }}</option>
                <option value="completed">{{ __('Completed') }}</option>
                <option value="running">{{ __('Running') }}</option>
                <option value="pending">{{ __('Pending') }}</option>
                <option value="failed">{{ __('Failed') }}</option>
            </select>
        </div>

        <!-- Table -->
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50/50 dark:bg-gray-800/40">
                        <tr>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Type') }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Tenant') }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Format') }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Size') }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Status') }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Created By') }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Date') }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200/60 dark:divide-gray-700/60 bg-white dark:bg-gray-900">
                        <tr
                            v-for="backup in backups.data"
                            :key="backup.id"
                            class="group hover:bg-gray-50 dark:hover:bg-gray-800 transition-all duration-200"
                        >
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-x-2">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white capitalize">{{ backup.type }}</span>
                                    <span v-if="backup.is_scheduled" class="px-1.5 py-0.5 text-[9px] font-medium bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 rounded-md">
                                        {{ __('Scheduled') }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                {{ backup.tenant?.name || '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-1.5 py-0.5 text-[9px] font-medium bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 rounded-md uppercase">
                                    {{ backup.format }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ backup.size_human || '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 text-[11px] font-bold rounded-lg border"
                                    :class="statusStyle(backup.status)"
                                >
                                    {{ backup.status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ backup.creator?.name || __('System') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ formatDate(backup.created_at) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-x-2">
                                    <!-- Download -->
                                    <a
                                        v-if="backup.status === 'completed'"
                                        :href="route('admin.backups.show', backup.id)"
                                        class="inline-flex items-center justify-center p-1.5 text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors duration-200"
                                        :title="__('Download')"
                                    >
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                        </svg>
                                    </a>
                                    <!-- Delete -->
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center p-1.5 text-gray-400 hover:text-red-600 dark:hover:text-red-400 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors duration-200"
                                        :title="__('Delete')"
                                        @click="deleteBackup(backup)"
                                    >
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty state -->
            <div v-if="backups.data.length === 0" class="py-12 text-center text-sm text-gray-400 dark:text-gray-500">
                {{ __('No backups yet.') }}
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="backups.links && backups.links.length > 3" class="flex flex-wrap items-center gap-1 mt-8">
            <template v-for="link in backups.links" :key="link.label">
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
                    v-html="link.label"
                    @click.prevent="link.url && router.get(link.url, {}, { preserveState: true })"
                />
            </template>
        </div>
    </AdminLayout>
</template>
