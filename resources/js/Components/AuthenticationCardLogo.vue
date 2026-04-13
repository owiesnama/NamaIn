<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <Link :href="'/'">
        <img
            :src="preferences('logo', '/images/logo.svg')"
            alt="Logo"
            class="object-contain w-16 h-16"
        />
    </Link>
</template>
