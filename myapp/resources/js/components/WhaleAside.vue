<script setup lang="ts">
import Mascot from '@/components/Mascot.vue';

// Brand panel for the left of an add/edit dialog: a floating whale, rising bubbles and slow waves.
// Fixed navy with white text, so it reads the same in light and dark mode.
defineProps<{ title: string; body: string }>();
</script>

<template>
    <aside class="aside relative hidden min-h-[30rem] w-64 shrink-0 flex-col justify-between overflow-hidden bg-brand-deep p-6 text-white md:flex">
        <span
            v-for="b in [8, 22, 40, 58, 74, 88]"
            :key="b"
            class="bubble-rise absolute bottom-0 rounded-full bg-white/15"
            :style="{ left: `${b}%`, width: `${6 + (b % 5) * 3}px`, height: `${6 + (b % 5) * 3}px`, animationDelay: `${-b / 10}s`, animationDuration: `${7 + (b % 4)}s` }"
            aria-hidden="true"
        />
        <div class="relative">
            <div class="whale-bob grid size-16 place-items-center rounded-2xl bg-white/10 ring-1 ring-white/20">
                <Mascot pose="happy" bare class="size-12" />
            </div>
            <div class="mt-6 text-[11px] font-semibold tracking-[0.18em] text-sky-200 uppercase">Whale Wallet</div>
            <h3 class="mt-2 text-2xl leading-tight font-semibold tracking-tight">{{ title }}</h3>
            <p class="mt-3 text-sm leading-relaxed text-white/75">{{ body }}</p>
        </div>
        <svg class="wave-pan pointer-events-none absolute inset-x-0 bottom-0 h-16 w-[200%]" viewBox="0 0 800 64" preserveAspectRatio="none" aria-hidden="true">
            <path d="M0 32 C 100 8 200 56 300 32 S 500 8 600 32 S 700 56 800 32 V64 H0 Z" fill="rgb(255 255 255 / 0.08)" />
        </svg>
        <svg class="wave-pan slow pointer-events-none absolute inset-x-0 bottom-0 h-10 w-[200%]" viewBox="0 0 800 40" preserveAspectRatio="none" aria-hidden="true">
            <path d="M0 22 C 100 4 200 36 300 22 S 500 4 600 22 S 700 36 800 22 V40 H0 Z" fill="rgb(125 211 252 / 0.14)" />
        </svg>
        <p class="relative mt-8 text-xs text-white/60">Private to your account.</p>
    </aside>
</template>

<style scoped>
.aside {
    background-image: radial-gradient(120% 60% at 0% 0%, rgb(125 211 252 / 0.16), transparent 60%);
}

.whale-bob {
    animation: whale-bob 4.5s ease-in-out infinite;
}

.bubble-rise {
    opacity: 0;
    animation: bubble-rise linear infinite;
}

.wave-pan {
    animation: wave-pan 14s linear infinite;
}

.wave-pan.slow {
    animation-duration: 22s;
    animation-direction: reverse;
}

@keyframes whale-bob {
    50% {
        transform: translateY(-6px) rotate(-2deg);
    }
}

@keyframes bubble-rise {
    0% {
        transform: translateY(0);
        opacity: 0;
    }
    15% {
        opacity: 1;
    }
    100% {
        transform: translateY(-26rem);
        opacity: 0;
    }
}

@keyframes wave-pan {
    to {
        transform: translateX(-50%);
    }
}

@media (prefers-reduced-motion: reduce) {
    .whale-bob,
    .bubble-rise,
    .wave-pan {
        animation: none;
    }

    .bubble-rise {
        opacity: 0.5;
    }
}
</style>
