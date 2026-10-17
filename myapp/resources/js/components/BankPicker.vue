<script setup lang="ts">
import { banks, cardGradient } from '@/lib/banks';
import type { BankPreset } from '@/lib/banks';

const props = defineProps<{ name: string; color: string | null }>();
const emit = defineEmits<{ pick: [bank: BankPreset] }>();

const active = (b: BankPreset) =>
    b.name === props.name && b.color === props.color;
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <button
            v-for="b in banks"
            :key="b.name"
            type="button"
            class="rounded-full px-3 py-1.5 text-xs font-medium text-white ring-offset-2 ring-offset-background transition"
            :class="
                active(b) ? 'ring-2 ring-white' : 'opacity-70 hover:opacity-100'
            "
            :style="{ background: cardGradient(b.color) }"
            @click="emit('pick', b)"
        >
            {{ b.name }}
        </button>
    </div>
</template>
