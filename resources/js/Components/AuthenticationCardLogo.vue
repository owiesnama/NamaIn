<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <Link href="/" class="flex items-center gap-2.5">
        <img
            :src="preferences('logo', '/images/logo.svg')"
            alt="Logo"
            class="h-8 w-8 object-contain"
        />
        <span class="text-lg font-bold text-slate-900">Namain</span>
    </Link>
</template>
