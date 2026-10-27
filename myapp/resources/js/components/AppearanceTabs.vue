<script setup lang="ts">
import { Check, Monitor, Moon, Sun } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

// each tile is a tiny drawing of the app in that theme; System splits light and dark
const tabs = [
    { value: 'light', Icon: Sun, label: 'Light' },
    { value: 'dark', Icon: Moon, label: 'Dark' },
    { value: 'system', Icon: Monitor, label: 'System' },
] as const;
const shade = { light: 'bg-white', dark: 'bg-neutral-900' };
</script>

<template>
    <div class="grid grid-cols-3 gap-3" role="radiogroup" aria-label="Theme">
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            type="button"
            role="radio"
            :aria-checked="appearance === value"
            class="rounded-xl border-2 p-2 text-left transition-[border-color,transform] duration-150 ease-out active:scale-[0.97]"
            :class="appearance === value ? 'border-primary' : 'border-transparent bg-muted/50 hover:border-border'"
            @click="updateAppearance(value)"
        >
            <span class="flex h-20 overflow-hidden rounded-lg border">
                <span
                    v-for="half in value === 'system' ? ['light', 'dark'] : [value]"
                    :key="half"
                    class="flex flex-1 gap-1.5 p-2"
                    :class="shade[half as 'light' | 'dark']"
                >
                    <span class="w-2 rounded-sm bg-primary" />
                    <span class="flex flex-1 flex-col gap-1.5">
                        <span class="h-2 rounded-sm" :class="half === 'dark' ? 'bg-neutral-700' : 'bg-neutral-200'" />
                        <span class="flex-1 rounded-sm" :class="half === 'dark' ? 'bg-neutral-800' : 'bg-neutral-100'" />
                    </span>
                </span>
            </span>
            <span class="mt-2 flex items-center gap-1.5 px-1 text-sm font-medium">
                <component :is="Icon" class="size-4 text-muted-foreground" />
                {{ label }}
                <Check v-if="appearance === value" class="ml-auto size-4 rounded-full bg-primary p-0.5 text-primary-foreground" />
            </span>
        </button>
    </div>
</template>
