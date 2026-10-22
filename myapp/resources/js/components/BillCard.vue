<script setup lang="ts">
import { ArrowUpRight } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import BrandLogo from '@/components/BrandLogo.vue';
import { findBrand } from '@/lib/brandLogos';
import { formatPeso } from '@/lib/currency';
import { dueTone } from '@/lib/dueTone';

// kind = "subscription", repeat = ["Repeats", "Monthly"], date = "21 Oct"
const props = defineProps<{
    name: string;
    logo: string | null;
    kind: string;
    repeat: [string, string];
    date: string;
    amount: number;
    due: string | null;
    done: boolean;
    cancelled?: boolean;
    hidden?: boolean;
}>();
defineEmits<{ select: [] }>();

const tone = computed(() =>
    props.cancelled || props.hidden ? { c: '#94a3b8', text: props.cancelled ? 'Cancelled' : 'Hidden' } : dueTone(props.due, props.done),
);

// the decoration in the corner follows the brand: a gear for motorbikes, fibre strands for internet and so on
const motif = computed(() => {
    const n = props.name.toLowerCase();
    const is = (re: RegExp) => re.test(n);

    return props.kind === 'loan'
        ? 'money'
        : is(/yamaha|nmax|aerox|honda|suzuki|motor|bike|car\b|auto/)
          ? 'gear'
          : is(/converge|wifi|wi-fi|internet|pldt|globe|sky|fiber|fibre|router|dito/)
            ? 'fiber'
            : is(/youtube/)
              ? 'play'
              : is(/netflix|disney|hbo|prime|viu|video|stream/)
                ? 'stream'
                : is(/spotify|music/)
                  ? 'bars'
                  : is(/rent|house|home|condo|apartment|dorm|lease/)
                    ? 'house'
                    : is(/claude|gpt|openai|gemini|copilot/)
                      ? 'rays'
                      : is(/google/)
                        ? 'google'
                        : 'wave';
});

type Rgb = [number, number, number];
const hexToRgb = (h: string): Rgb => [1, 3, 5].map((i) => parseInt(h.slice(i, i + 2), 16)) as Rgb;

// The logo's own colour paints the brush and the hover fill: a brand icon gives it directly, an image is averaged on a
// 1px canvas. Until then (or when the logo is grey or near white) the due colour stands in.
const rgb = ref<Rgb | null>(null);
const brand = findBrand(props.name)?.icon?.hex;

if (brand) {
    rgb.value = hexToRgb(`#${brand}`);
}

const vibe = computed(() => `rgb(${(rgb.value ?? hexToRgb(tone.value.c)).join(' ')})`);
onMounted(() => {
    const src = props.logo || findBrand(props.name)?.image;

    if (brand || !src) {
        return;
    }

    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = () => {
        try {
            const ctx = document.createElement('canvas').getContext('2d')!;
            ctx.canvas.width = ctx.canvas.height = 1;
            ctx.drawImage(img, 0, 0, 1, 1);
            const [r, g, b, a] = ctx.getImageData(0, 0, 1, 1).data;
            const grey = Math.max(r, g, b) - Math.min(r, g, b) < 35 || Math.min(r, g, b) > 215;

            if (a > 40 && !grey) {
                rgb.value = [r, g, b];
            }
        } catch {
            // tainted canvas: keep the due colour
        }
    };
    img.src = src;
});
</script>

<template>
    <li
        class="bill-card relative overflow-hidden rounded-[1.75rem] text-sm shadow-sm ring-1 ring-border"
        :class="(cancelled || hidden) && 'opacity-70'"
        :style="{ '--vibe': vibe, '--tone': tone.c }"
    >
        <!-- corner decoration in the logo colour; the shape follows the brand (see motif) -->
        <svg class="bill-brush" viewBox="0 0 160 110" aria-hidden="true">
            <template v-if="motif === 'gear'">
                <g class="spin" style="transform-origin: 122px 96px">
                    <circle cx="122" cy="96" r="50" class="teeth" />
                    <circle cx="122" cy="96" r="42" class="f" />
                    <circle cx="122" cy="96" r="15" class="hole" />
                </g>
                <g class="spin back" style="transform-origin: 58px 106px">
                    <circle cx="58" cy="106" r="27" class="teeth small" />
                    <circle cx="58" cy="106" r="22" class="f soft2" />
                    <circle cx="58" cy="106" r="8" class="hole" />
                </g>
            </template>
            <template v-else-if="motif === 'fiber'">
                <path class="s base" d="M-30 104 C 30 96 80 48 190 34" />
                <path class="s base" d="M-30 112 C 40 104 90 62 190 52" />
                <path class="s flow" d="M-30 104 C 30 96 80 48 190 34" />
                <path class="s flow slow" d="M-30 112 C 40 104 90 62 190 52" />
                <path class="s flow late" d="M-30 96 C 20 86 90 36 190 18" />
                <circle class="f pulse" cx="150" cy="40" r="4" />
                <circle class="f pulse late" cx="124" cy="60" r="3" />
            </template>
            <template v-else-if="motif === 'bars'">
                <rect v-for="(h, i) in [46, 78, 58, 92, 64, 40]" :key="i" class="f bar" :style="{ animationDelay: `${i * -0.35}s` }" :x="40 + i * 20" :y="112 - h" width="13" :height="h" rx="6.5" />
            </template>
            <template v-else-if="motif === 'house'">
                <g class="float b">
                    <path class="f soft2" d="M10 112 V86 L36 64 L62 86 V112 Z" />
                    <rect class="hole" x="30" y="92" width="12" height="20" rx="2" />
                </g>
                <g class="float">
                    <rect class="f" x="138" y="22" width="12" height="26" rx="2" />
                    <path class="f" d="M70 112 V66 L116 28 L162 66 V112 Z" />
                    <path class="f soft2" d="M60 70 L116 20 L172 70 L162 72 L116 34 L70 72 Z" />
                    <rect class="hole" x="105" y="76" width="22" height="36" rx="4" />
                    <rect class="hole glow" x="80" y="78" width="16" height="16" rx="3" />
                    <rect class="hole glow late" x="136" y="78" width="16" height="16" rx="3" />
                </g>
            </template>
            <template v-else-if="motif === 'google'">
                <g class="float" style="transform-origin: 112px 66px">
                    <circle v-for="a in [{ c: '#ea4335', r: 200 }, { c: '#fbbc05', r: 110 }, { c: '#34a853', r: 20 }, { c: '#4285f4', r: -10 }]" :key="a.c" cx="112" cy="66" r="40" fill="none" stroke-width="15" :stroke="a.c" :stroke-dasharray="a.r === -10 ? '21 230' : '62 189'" :transform="`rotate(${a.r} 112 66)`" class="gseg" />
                    <rect x="112" y="60" width="42" height="13" fill="#4285f4" class="gseg" />
                </g>
                <circle class="f float b" cx="40" cy="96" r="14" />
                <circle class="f soft2 float c" cx="146" cy="14" r="7" />
            </template>
            <template v-else-if="motif === 'play'">
                <g class="tilt" style="transform-origin: 112px 72px">
                    <rect class="f" x="58" y="36" width="104" height="72" rx="22" />
                    <path class="hole" d="M99 56 L132 72 L99 88 Z" />
                </g>
                <rect class="f soft2 progress" x="20" y="104" width="130" height="7" rx="3.5" />
            </template>
            <template v-else-if="motif === 'stream'">
                <g class="float">
                    <rect class="f" x="62" y="42" width="98" height="66" rx="12" />
                    <rect class="hole" x="71" y="51" width="80" height="48" rx="7" />
                    <path class="f" d="M102 60 L130 75 L102 90 Z" />
                </g>
                <path class="s sig" d="M88 28 Q 111 8 134 28" style="stroke-width: 5" />
                <path class="s sig late" d="M74 20 Q 111 -12 148 20" style="stroke-width: 5" />
            </template>
            <template v-else-if="motif === 'money'">
                <g class="float b">
                    <rect class="f soft2" x="26" y="34" width="72" height="42" rx="7" transform="rotate(-14 62 55)" />
                    <circle class="hole" cx="62" cy="55" r="11" transform="rotate(-14 62 55)" />
                </g>
                <g class="float">
                    <circle class="f" cx="128" cy="82" r="32" />
                    <circle class="ring" cx="128" cy="82" r="25" />
                    <text class="peso" x="128" y="94" font-size="34">₱</text>
                </g>
                <g class="float c">
                    <circle class="f soft2" cx="76" cy="100" r="20" />
                    <circle class="ring" cx="76" cy="100" r="15" />
                    <text class="peso" x="76" y="108" font-size="22">₱</text>
                </g>
            </template>
            <template v-else-if="motif === 'rays'">
                <g class="spin slowspin" style="transform-origin: 124px 92px">
                    <line v-for="r in 12" :key="r" class="s ray" x1="124" y1="92" x2="124" y2="22" :style="{ transform: `rotate(${r * 30}deg)`, transformOrigin: '124px 92px' }" />
                </g>
                <circle class="f" cx="124" cy="92" r="18" />
            </template>
            <template v-else>
                <path class="f wave-a" d="M-60 110 C -10 105 40 45 100 50 C 130 53 150 40 190 22 L 190 110 Z" />
                <path class="f soft2 wave-b" d="M10 110 C 50 100 100 70 135 85 C 150 91 170 100 190 110 Z" />
            </template>
        </svg>
        <button
            type="button"
            class="relative flex min-h-56 w-full cursor-pointer flex-col p-5 text-left outline-none focus-visible:ring-2 focus-visible:ring-ring"
            @click="$emit('select')"
        >
            <div class="flex w-full items-start justify-between gap-3">
                <BrandLogo :name="name" :logo="logo" />
                <span
                    class="bill-pill shrink-0 rounded-full px-3 py-1 text-[11px] font-bold tracking-wide"
                >
                    {{ tone.text }}
                </span>
            </div>
            <div class="muted mt-5 text-xs capitalize">{{ kind }}</div>
            <div class="bill-title line-clamp-2 text-[1.65rem] leading-[1.05] font-black tracking-tight">
                {{ name }}
            </div>
            <dl class="mt-auto grid grid-cols-[auto_auto_1fr] items-end gap-x-6 pt-5">
                <div class="min-w-0">
                    <dt class="muted text-[11px]">Amount</dt>
                    <dd class="text-base font-bold">{{ formatPeso(amount) }}</dd>
                </div>
                <div>
                    <dt class="muted text-[11px]">{{ repeat[0] }}</dt>
                    <dd class="text-base font-bold capitalize">{{ repeat[1] }}</dd>
                </div>
                <div>
                    <dt class="muted text-[11px]">Next due</dt>
                    <dd class="text-base font-bold">{{ date }}</dd>
                </div>
            </dl>
            <span
                class="bill-pill mt-4 grid size-9 place-items-center rounded-full"
                aria-hidden="true"
            >
                <ArrowUpRight class="size-4" />
            </span>
        </button>
    </li>
</template>

<style scoped>
/* pastel throughout: the logo colour is only ever mixed into the theme's own card colour, never used at full strength */
.bill-card {
    --paper: color-mix(in srgb, var(--card) 55%, var(--background));
    --soft: color-mix(in srgb, var(--vibe) 42%, var(--paper));
    background: color-mix(in srgb, var(--vibe) 6%, var(--paper));
    transition:
        transform 0.25s ease,
        box-shadow 0.25s ease,
        background-color 0.35s ease;
}

.muted {
    color: var(--muted-foreground);
}

.bill-title {
    font-family: ui-serif, Georgia, 'Times New Roman', serif;
}

.bill-pill {
    background: color-mix(in srgb, var(--tone) 24%, var(--paper));
    color: color-mix(in srgb, var(--tone) 65%, var(--foreground));
    transition: background-color 0.35s ease;
}

.bill-brush {
    position: absolute;
    right: -0.5rem;
    bottom: -0.75rem;
    width: 19rem;
    pointer-events: none;
    overflow: visible;
    transform-origin: 100% 100%;
    transition: transform 0.4s ease;
}

.bill-brush .f {
    fill: var(--soft);
    transform-box: fill-box;
    transform-origin: 50% 100%;
    transition: fill 0.35s ease;
}

.bill-brush .s {
    fill: none;
    stroke: var(--soft);
    stroke-linecap: round;
    transition: stroke 0.35s ease;
}

.bill-brush .soft2 {
    opacity: 0.6;
}

/* gear */
.teeth {
    fill: none;
    stroke: var(--soft);
    stroke-width: 12;
    stroke-dasharray: 6.5 6.5;
}

.teeth.small {
    stroke-width: 9;
    stroke-dasharray: 5.2 5.2;
}

.hole {
    fill: var(--paper);
}

.spin {
    animation: spin 18s linear infinite;
}

.spin.back {
    animation-direction: reverse;
    animation-duration: 12s;
}

.spin.slowspin {
    animation-duration: 30s;
}

/* fibre */
.s.base {
    stroke-width: 5;
    opacity: 0.45;
}

.s.flow {
    stroke-width: 3;
    stroke-dasharray: 3 12;
    animation: flow 1.6s linear infinite;
}

.s.flow.slow {
    animation-duration: 2.4s;
}

.s.flow.late {
    animation-delay: -0.8s;
    opacity: 0.7;
}

.pulse {
    animation: pulse 2s ease-in-out infinite;
}

.pulse.late {
    animation-delay: -1s;
}

/* house, google, play, stream, money */
.glow {
    animation: fade 3s ease-in-out infinite;
}

.gseg {
    opacity: 0.42;
}

.tilt {
    animation: tilt 4s ease-in-out infinite alternate;
}

.progress {
    transform-box: fill-box;
    transform-origin: 0 50%;
    animation: grow 5s ease-in-out infinite;
}

.sig {
    animation: fade 2s ease-in-out infinite;
}

.bar {
    transform-box: fill-box;
    transform-origin: 50% 100%;
    animation: bounce 1.4s ease-in-out infinite;
}

.ring {
    fill: none;
    stroke: var(--paper);
    stroke-width: 2.5;
}

.peso {
    fill: var(--paper);
    font-weight: 800;
    text-anchor: middle;
    font-family: ui-sans-serif, system-ui, sans-serif;
}

@keyframes fade {
    50% {
        opacity: 0.3;
    }
}

@keyframes tilt {
    from {
        transform: rotate(-9deg);
    }
    to {
        transform: rotate(-3deg) translateY(-4px);
    }
}

@keyframes grow {
    from {
        transform: scaleX(0.08);
    }
    to {
        transform: scaleX(1);
    }
}

@keyframes bounce {
    50% {
        transform: scaleY(0.55);
    }
}

/* blades, leaf, rays, blobs */
.blade {
    stroke-width: 9;
    transform-box: fill-box;
    transform-origin: 50% 100%;
    animation: sway 3s ease-in-out infinite alternate;
}

.s.stem {
    stroke-width: 3;
}

.sway-leaf {
    transform-box: fill-box;
    transform-origin: 0% 100%;
    animation: sway 4s ease-in-out infinite alternate;
}

.s.ray {
    stroke-width: 5;
    opacity: 0.6;
}

.float {
    animation: float 4s ease-in-out infinite alternate;
}

.float.b {
    animation-duration: 5.5s;
    animation-direction: alternate-reverse;
}

.float.c {
    animation-duration: 3.2s;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

@keyframes flow {
    to {
        stroke-dashoffset: -15;
    }
}

@keyframes pulse {
    50% {
        opacity: 0.25;
        transform: scale(1.5);
    }
}

@keyframes sway {
    from {
        transform: rotate(-7deg);
    }
    to {
        transform: rotate(7deg);
    }
}

@keyframes float {
    from {
        transform: translate(-5px, 4px);
    }
    to {
        transform: translate(5px, -6px);
    }
}

.wave-b {
    opacity: 0.6;
}

.wave-a {
    animation: wave 5s ease-in-out infinite alternate;
}

.wave-b {
    animation: wave 3.6s ease-in-out infinite alternate-reverse;
}

@keyframes wave {
    from {
        transform: translateX(-14px) rotate(-2deg) scaleY(0.94);
    }
    to {
        transform: translateX(10px) rotate(3deg) scaleY(1.08);
    }
}

.bill-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 18px 36px -20px var(--vibe);
    background: color-mix(in srgb, var(--vibe) 24%, var(--paper));
}

.bill-card:hover .bill-brush {
    --soft: color-mix(in srgb, var(--vibe) 58%, var(--paper));
}

.bill-card:hover .bill-pill {
    background: var(--paper);
}

@media (prefers-reduced-motion: reduce) {
    .bill-card,
    .bill-brush,
    .bill-brush * {
        transition: none;
        animation: none !important;
    }
}
</style>
