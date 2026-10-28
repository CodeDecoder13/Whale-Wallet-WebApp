<script setup lang="ts">
import { CircleHelp } from '@lucide/vue';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';

// the "?" sign: hover or focus (tap on a phone) explains a number in plain words
defineProps<{ text: string }>();
</script>

<template>
    <Tooltip>
        <TooltipTrigger as-child>
            <span
                role="button"
                tabindex="0"
                :aria-label="`What is this? ${text}`"
                class="inline-flex shrink-0 cursor-help align-middle opacity-60 hover:opacity-100 focus-visible:opacity-100"
                @click.stop
            >
                <CircleHelp class="size-3.5" />
            </span>
        </TooltipTrigger>
        <TooltipContent class="max-w-64 text-left">{{ text }}</TooltipContent>
    </Tooltip>
</template>
