<script setup lang="ts">
import PublicFooter from '@/components/public/PublicFooter.vue';
import PublicHeader from '@/components/public/PublicHeader.vue';
</script>

<template>
    <div class="min-h-screen overflow-x-clip bg-background text-foreground">
        <PublicHeader />
        <main><slot /></main>
        <PublicFooter />
    </div>
</template>
