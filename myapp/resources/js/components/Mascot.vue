<script setup lang="ts">
// the brand-sheet crops live in public/mascot; the art has its own pale backdrop, so it sits in a rounded tile
defineProps<{
    pose: 'hero' | 'happy' | 'excited' | 'thinking' | 'worried' | 'checking';
    round?: boolean;
    // transparent cut-out: no tile, for sitting straight on a coloured card
    bare?: boolean;
}>();
</script>

<template>
    <img
        :src="`/mascot/${pose}.png`"
        alt=""
        :class="
            bare
                ? 'object-contain'
                : [
                      'bg-[#eaf2ff] object-cover',
                      round ? 'rounded-full' : 'rounded-2xl',
                  ]
        "
    />
</template>
