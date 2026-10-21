<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

// A plain reading page: title, optional "last updated", and prose styled once here so each page is just content.
defineProps<{ title: string; description: string; updated?: string }>();
</script>

<template>
    <Head :title="title">
        <meta name="description" :content="description" />
    </Head>
    <article
        class="mx-auto max-w-3xl px-4 py-12 md:px-8 md:py-16 [&_:is(p,li,td)>a]:font-medium [&_:is(p,li,td)>a]:text-brand-deep [&_:is(p,li,td)>a]:underline [&_:is(p,li,td)>a]:underline-offset-4 dark:[&_:is(p,li,td)>a]:text-brand-light [&_h2]:mt-10 [&_h2]:mb-3 [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:tracking-tight [&_h3]:mt-6 [&_h3]:mb-2 [&_h3]:text-lg [&_h3]:font-semibold [&_li]:mt-1.5 [&_ol]:ml-5 [&_ol]:list-decimal [&_p]:mt-3 [&_p]:leading-relaxed [&_table]:mt-4 [&_table]:w-full [&_table]:text-left [&_table]:text-sm [&_td]:border-t [&_td]:py-2 [&_td]:pr-4 [&_td]:align-top [&_th]:pr-4 [&_th]:pb-2 [&_ul]:ml-5 [&_ul]:list-disc"
    >
        <h1
            class="text-4xl font-semibold tracking-tight text-balance md:text-5xl"
        >
            {{ title }}
        </h1>
        <p v-if="updated" class="mt-3 text-sm text-muted-foreground">
            Last updated: {{ updated }}
        </p>
        <p class="mt-5 text-lg text-muted-foreground">{{ description }}</p>
        <slot />
    </article>
</template>
