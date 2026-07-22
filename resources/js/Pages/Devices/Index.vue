<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { Link, router, usePage, useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import { usePermissions } from "@/Composables/usePermissions";

defineProps({
    devices: Array,
    storages: Array,
});

const { can } = usePermissions();
const page = usePage();

const pairingCode = computed(() => page.props.flash?.pairing_code ?? null);
const showEnroll = ref(false);

const enrollForm = useForm({ storage_id: null, name: "" });

const submitEnroll = () => {
    enrollForm.post(route("devices.store"), {
        preserveScroll: true,
        onSuccess: () => {
            showEnroll.value = false;
            enrollForm.reset();
        },
    });
};

const revoke = (device) => {
    if (confirm(__("Revoke this device? It will wipe on next launch and any unsynced work may be lost."))) {
        router.post(route("devices.revoke", device.id), {}, { preserveScroll: true });
    }
};

const replace = (device) => {
    if (confirm(__("Replace this device on the same register? Its outbox must be fully synced first."))) {
        router.post(route("devices.replace", device.id), {}, { preserveScroll: true });
    }
};

const healthClasses = {
    healthy: "text-emerald-700 bg-emerald-50 border-emerald-200 dark:bg-emerald-900/20 dark:border-emerald-800 dark:text-emerald-400",
    stale: "text-amber-700 bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-400",
    offline: "text-gray-600 bg-gray-100 border-gray-200 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400",
    skewed: "text-orange-700 bg-orange-50 border-orange-200 dark:bg-orange-900/20 dark:border-orange-800 dark:text-orange-400",
    revoked: "text-red-700 bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-800 dark:text-red-400",
};

const formatDate = (iso) =>
    iso ? new Date(iso).toLocaleString(undefined, { dateStyle: "medium", timeStyle: "short" }) : "—";
</script>

<template>
    <AppLayout :title="__('Devices')">
        <div class="w-full lg:flex lg:items-center lg:justify-between">
            <div>
                <div class="flex items-center gap-x-3">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-white">{{ __("Devices") }}</h2>
                    <span class="px-3 py-1 text-xs font-semibold rounded-full text-emerald-700 bg-emerald-100/60 dark:bg-gray-800 dark:text-emerald-400">
                        {{ devices.length }} {{ __("Devices") }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __("Offline POS terminals and their sync health.") }}</p>
            </div>
            <div class="mt-4 flex items-center justify-end gap-x-4 lg:mt-0">
                <button
                    v-if="can('devices.manage')"
                    type="button"
                    class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-colors duration-200"
                    @click="showEnroll = true"
                >
                    {{ __("Enroll device") }}
                </button>
            </div>
        </div>

        <!-- Pairing code banner -->
        <div v-if="pairingCode" class="mt-6 p-5 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-xl">
            <p class="text-sm text-emerald-700 dark:text-emerald-400">{{ page.props.flash?.message }}</p>
            <p class="mt-2 text-2xl font-bold tracking-widest text-emerald-800 dark:text-emerald-300">{{ pairingCode }}</p>
        </div>

        <!-- Fleet table -->
        <div class="mt-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50/50 dark:bg-gray-800/40">
                        <tr>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Device") }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Register") }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Health") }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Last seen") }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Pending") }}</th>
                            <th class="px-6 py-4 text-end text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Action") }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200/60 dark:divide-gray-700/60 bg-white dark:bg-gray-900">
                        <tr v-for="device in devices" :key="device.public_id" class="group hover:bg-gray-50 dark:hover:bg-gray-800 transition-all duration-200">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <Link :href="route('devices.show', device.id)" class="text-sm font-medium text-gray-900 dark:text-white hover:text-emerald-600 dark:hover:text-emerald-400">{{ device.name }}</Link>
                                <p class="text-xs text-gray-400 dark:text-gray-500">{{ device.app_version ?? "—" }}</p>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ device.register ?? "—" }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center gap-x-1.5 px-2.5 py-1 text-[11px] font-bold rounded-lg border" :class="healthClasses[device.health]">{{ device.health_label }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ formatDate(device.last_seen_at) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                {{ device.pending_count ?? 0 }}
                                <span v-if="device.revoked_unsynced_count" class="text-xs text-red-600 dark:text-red-400">(≈{{ device.revoked_unsynced_count }} {{ __("lost") }})</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-end">
                                <div v-if="can('devices.manage') && device.status !== 'revoked'" class="inline-flex items-center gap-x-3">
                                    <button type="button" class="text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400" @click="replace(device)">{{ __("Replace") }}</button>
                                    <button type="button" class="text-sm font-medium text-red-600 dark:text-red-400 hover:text-red-700" @click="revoke(device)">{{ __("Revoke") }}</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="devices.length === 0" class="py-12 text-center text-sm text-gray-400 dark:text-gray-500">{{ __("No devices enrolled yet.") }}</div>
        </div>

        <!-- Enroll modal -->
        <div v-if="showEnroll" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-500/20 dark:bg-gray-900/60 backdrop-blur-sm" @click="showEnroll = false" />
            <div class="relative bg-white dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-lg mx-auto">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __("Enroll device") }}</h3>
                <form class="mt-4 space-y-4" @submit.prevent="submitEnroll">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">{{ __("Sale point") }}</label>
                        <select v-model.number="enrollForm.storage_id" required class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50">
                            <option :value="null">{{ __("Select sale point") }}</option>
                            <option v-for="s in storages" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">{{ __("Device name") }}</label>
                        <input v-model="enrollForm.name" type="text" required class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50" />
                    </div>
                    <div class="flex justify-end gap-x-3 pt-2">
                        <button type="button" class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200" @click="showEnroll = false">{{ __("Cancel") }}</button>
                        <button type="submit" :disabled="enrollForm.processing" class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200">{{ __("Enroll") }}</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
