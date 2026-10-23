<script setup lang="ts">
import AuthLayout from '@/layouts/auth/AuthSplitLayout.vue';

const {
    title = '',
    description = '',
    side = 'right',
} = defineProps<{
    title?: string;
    description?: string;
    side?: 'left' | 'right';
}>();
</script>

<template>
    <AuthLayout :title="title" :description="description" :side="side">
        <slot />
    </AuthLayout>
</template>
