<script setup lang="ts">
import { computed, ref } from 'vue';
import BrandLogo from '@/components/BrandLogo.vue';
import { banks } from '@/lib/banks';
import type { BankPreset } from '@/lib/banks';

const props = defineProps<{ name: string; color: string | null }>();
const emit = defineEmits<{ pick: [bank: BankPreset] }>();

const active = (b: BankPreset) => b.name === props.name && b.color === props.color;

// three short lists instead of one wall of chips; opens on the group of the current pick
const groups = [
    { type: 'ewallet', label: 'E-wallets' },
    { type: 'bank', label: 'Banks' },
    { type: 'cash', label: 'Cash' },
];
const group = ref(banks.find(active)?.type ?? 'ewallet');
const shown = computed(() => banks.filter((b) => b.type === group.value));
</script>

<template>
    <div class="grid gap-3">
        <div role="tablist" class="inline-flex w-fit gap-1 rounded-xl bg-muted p-1">
            <button
                v-for="g in groups"
                :key="g.type"
                type="button"
                role="tab"
                :aria-selected="group === g.type"
                class="rounded-lg px-3 py-1.5 text-xs font-medium transition active:scale-[0.98]"
                :class="group === g.type ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                @click="group = g.type"
            >
                {{ g.label }}
            </button>
        </div>
        <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
            <button
                v-for="b in shown"
                :key="b.name"
                type="button"
                class="flex items-center gap-2 rounded-xl border bg-card px-2.5 py-2 text-left text-sm transition hover:bg-accent active:scale-[0.98]"
                :class="active(b) && 'border-transparent ring-2 ring-[var(--bank)]'"
                :style="{ '--bank': b.color }"
                :aria-pressed="active(b)"
                @click="emit('pick', b)"
            >
                <BrandLogo :name="b.name" class="!size-7" />
                <span class="min-w-0 truncate font-medium">{{ b.name }}</span>
            </button>
        </div>
    </div>
</template>
