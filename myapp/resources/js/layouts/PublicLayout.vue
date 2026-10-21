<script setup lang="ts">
import PublicFooter from '@/components/public/PublicFooter.vue';
import PublicHeader from '@/components/public/PublicHeader.vue';
</script>

<template>
    <div class="min-h-screen bg-background text-foreground">
        <PublicHeader />
        <main><slot /></main>
        <PublicFooter />
    </div>
</template>
