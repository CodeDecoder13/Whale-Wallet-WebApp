<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <img
        src="/whale-wallet-logo.svg"
        alt=""
        :class="['rounded-lg', className]"
        v-bind="$attrs"
    />
</template>
