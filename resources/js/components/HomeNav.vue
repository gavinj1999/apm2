<template>
    <nav class="flex items-center justify-end gap-4">
        <Link v-if="$page.props.auth.user" :href="route('dashboard')"
            class="inline-block rounded-sm border border-[#19140035] px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#1915014a] dark:border-[#3E3E3A] dark:text-[#EDEDEC] dark:hover:border-[#62605b]">
        Dashboard
        </Link>
        <template v-else>
            <Link :href="route('login')"
                class="inline-block rounded-sm border border-transparent px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#19140035] dark:text-[#EDEDEC] dark:hover:border-[#3E3E3A]">
            Log in
            </Link>
            <Link :href="route('register')"
                class="inline-block rounded-sm border border-[#19140035] px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#1915014a] dark:border-[#3E3E3A] dark:text-[#EDEDEC] dark:hover:border-[#62605b]">
            Register
            </Link>
        </template>
    </nav>
</template>
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>
