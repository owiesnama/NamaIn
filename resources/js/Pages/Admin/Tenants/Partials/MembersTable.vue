<script setup>
    import { computed, ref } from "vue";
    import { useDate } from "@/Composables/useDate.js";
    import Ltr from "@/Components/Ltr.vue";

    const props = defineProps({
        members: { type: Array, default: () => [] },
        tenant: { type: Object, required: true },
    });

    const emit = defineEmits(["impersonate", "change-role", "toggle-status", "remove"]);

    const { formatDate } = useDate();

    const search = ref("");

    const filteredMembers = computed(() => {
        const term = search.value.trim().toLowerCase();

        if (! term) {
            return props.members;
        }

        return props.members.filter(
            (m) =>
                m.name?.toLowerCase().includes(term) ||
                m.email?.toLowerCase().includes(term)
        );
    });

    const avatarTone = (member) => {
        if (member.role === "owner") {
            return "bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400";
        }

        if (member.role === "cashier") {
            return "bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400";
        }

        return "bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300";
    };
</script>

<template>
    <div>
        <!-- Search bar -->
        <div class="flex justify-end px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="relative">
                <span class="absolute inset-y-0 start-0 flex items-center ps-3 text-gray-400 dark:text-gray-500 pointer-events-none">
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                </span>
                <input
                    v-model="search"
                    type="text"
                    :placeholder="__('Search for a member…')"
                    class="w-56 ps-9 pe-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 placeholder-gray-400 dark:placeholder-gray-600"
                />
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50/50 dark:bg-gray-800/40">
                    <tr>
                        <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('User') }}</th>
                        <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Role') }}</th>
                        <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Status') }}</th>
                        <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Joined') }}</th>
                        <th class="px-6 py-4 text-end text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200/60 dark:divide-gray-700/60 bg-white dark:bg-gray-900">
                    <tr
                        v-for="member in filteredMembers"
                        :key="member.id"
                        class="group hover:bg-gray-50 dark:hover:bg-gray-800 transition-all duration-200"
                    >
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-x-3">
                                <img
                                    v-if="member.profile_photo_url"
                                    :src="member.profile_photo_url"
                                    :alt="member.name"
                                    class="h-9 w-9 rounded-full object-cover"
                                />
                                <div
                                    v-else
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                                    :class="avatarTone(member)"
                                >
                                    {{ member.name?.charAt(0)?.toUpperCase() }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ member.name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate"><Ltr>{{ member.email }}</Ltr></p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-md border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
                                {{ __(member.role) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 text-[11px] font-bold rounded-full"
                                :class="member.is_active
                                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400'
                                    : 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400'"
                            >
                                {{ member.is_active ? __('Active') : __('Inactive') }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            <Ltr>{{ formatDate(member.joined_at) }}</Ltr>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-end">
                            <div class="flex items-center justify-end gap-x-1">
                                <!-- Impersonate (all users, active tenant) -->
                                <button
                                    v-if="tenant.is_active"
                                    class="inline-flex items-center justify-center p-2 text-emerald-500 dark:text-emerald-400 rounded-lg hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-colors duration-200 focus:outline-none"
                                    :title="__('Impersonate')"
                                    @click="emit('impersonate', member)"
                                >
                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                                    </svg>
                                </button>
                                <!-- Non-owner actions -->
                                <template v-if="member.role !== 'owner'">
                                    <button
                                        class="inline-flex items-center justify-center p-2 text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors duration-200 focus:outline-none"
                                        :title="__('Change Role')"
                                        @click="emit('change-role', member)"
                                    >
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        </svg>
                                    </button>
                                    <button
                                        class="inline-flex items-center justify-center p-2 text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors duration-200 focus:outline-none"
                                        :title="member.is_active ? __('Deactivate') : __('Activate')"
                                        @click="emit('toggle-status', member)"
                                    >
                                        <svg v-if="member.is_active" class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
                                        </svg>
                                        <svg v-else class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                    </button>
                                    <button
                                        class="inline-flex items-center justify-center p-2 text-red-400 hover:text-red-600 dark:hover:text-red-300 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors duration-200 focus:outline-none"
                                        :title="__('Remove')"
                                        @click="emit('remove', member)"
                                    >
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </template>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="filteredMembers.length === 0" class="py-12 text-center text-sm text-gray-400 dark:text-gray-500">
            {{ search ? __('No members match your filters.') : __('No members found.') }}
        </div>
    </div>
</template>
