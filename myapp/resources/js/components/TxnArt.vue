<script setup lang="ts">
// A big, soft drawing in the corner of a transaction card. Shapes use currentColor (the category colour) at a low opacity;
// "hole" shapes are the card's own colour so they cut into the drawing.
defineProps<{ art: string }>();
</script>

<template>
    <svg viewBox="0 0 160 110" class="pointer-events-none absolute right-0 bottom-0 h-full max-h-44 text-[var(--vibe)]" aria-hidden="true">
        <g fill="currentColor" opacity="0.32">
            <template v-if="art === 'coffee'">
                <path d="M70 52h62v26a30 30 0 0 1-30 30h-2a30 30 0 0 1-30-30z" />
                <path d="M132 60h8a14 14 0 0 1 0 28h-10v-8h8a6 6 0 0 0 0-12h-6z" />
                <rect x="60" y="106" width="82" height="6" rx="3" opacity="0.7" />
                <path d="M88 14c-8 8 8 12 0 22M108 10c-8 8 8 12 0 22" fill="none" stroke="currentColor" stroke-width="6" stroke-linecap="round" />
            </template>
            <template v-else-if="art === 'food'">
                <circle cx="116" cy="70" r="40" />
                <circle cx="116" cy="70" r="28" fill="var(--hole)" />
                <rect x="44" y="30" width="8" height="80" rx="4" />
                <rect x="30" y="30" width="6" height="30" rx="3" />
                <rect x="60" y="30" width="6" height="30" rx="3" />
            </template>
            <template v-else-if="art === 'laundry'">
                <path d="M30 56h100l-10 52H40z" />
                <path d="M30 56c0-14 14-24 50-24s50 10 50 24" fill="none" stroke="currentColor" stroke-width="7" />
                <path d="M96 8l16 10 12-6 12 18-12 8v22H90V38l-12-8z" opacity="0.8" />
            </template>
            <template v-else-if="art === 'carwash' || art === 'car'">
                <path d="M20 84l14-30c3-6 8-9 15-9h60c7 0 12 3 16 9l18 30v22H20z" />
                <circle cx="52" cy="106" r="13" fill="var(--hole)" />
                <circle cx="128" cy="106" r="13" fill="var(--hole)" />
                <path d="M52 62h28v18H44zM88 62h26l14 18H88z" fill="var(--hole)" opacity="0.7" />
                <template v-if="art === 'carwash'">
                    <circle cx="126" cy="26" r="14" opacity="0.8" /><circle cx="98" cy="14" r="8" opacity="0.8" /><circle cx="148" cy="52" r="7" opacity="0.8" />
                </template>
            </template>
            <template v-else-if="art === 'motor'">
                <circle cx="44" cy="84" r="26" /><circle cx="44" cy="84" r="14" fill="var(--hole)" />
                <circle cx="124" cy="84" r="26" /><circle cx="124" cy="84" r="14" fill="var(--hole)" />
                <path d="M44 84l30-36h30l20 36h-14l-14-22H82L62 84zM92 44l-6-14h20v8z" />
            </template>
            <template v-else-if="art === 'card'">
                <rect x="24" y="30" width="112" height="70" rx="12" transform="rotate(-10 80 65)" />
                <rect x="24" y="48" width="112" height="14" transform="rotate(-10 80 65)" fill="var(--hole)" opacity="0.7" />
                <circle cx="124" cy="82" r="26" opacity="0.8" /><circle cx="124" cy="82" r="18" fill="var(--hole)" />
            </template>
            <template v-else-if="art === 'grocery'">
                <path d="M16 20h22l10 56h74l14-40H52" fill="none" stroke="currentColor" stroke-width="8" stroke-linejoin="round" />
                <path d="M52 36h86l-14 40H60z" opacity="0.7" /><circle cx="66" cy="96" r="9" /><circle cx="118" cy="96" r="9" />
            </template>
            <template v-else-if="art === 'bill'">
                <path d="M96 12a36 36 0 0 0-20 66v12h40V78a36 36 0 0 0-20-66z" />
                <rect x="78" y="94" width="36" height="8" rx="4" opacity="0.7" /><rect x="84" y="106" width="24" height="6" rx="3" opacity="0.7" />
            </template>
            <template v-else-if="art === 'shop'">
                <path d="M34 42h92l8 68H26z" />
                <path d="M58 42v-8a22 22 0 0 1 44 0v8" fill="none" stroke="currentColor" stroke-width="8" />
                <circle cx="62" cy="62" r="5" fill="var(--hole)" /><circle cx="98" cy="62" r="5" fill="var(--hole)" />
            </template>
            <template v-else-if="art === 'health'">
                <rect x="58" y="14" width="44" height="92" rx="14" /><rect x="34" y="38" width="92" height="44" rx="14" />
                <rect x="70" y="26" width="20" height="68" rx="6" fill="var(--hole)" opacity="0.7" />
            </template>
            <template v-else-if="art === 'fun'">
                <rect x="14" y="38" width="132" height="66" rx="30" />
                <rect x="40" y="62" width="30" height="8" rx="4" fill="var(--hole)" /><rect x="51" y="51" width="8" height="30" rx="4" fill="var(--hole)" />
                <circle cx="106" cy="58" r="7" fill="var(--hole)" /><circle cx="124" cy="74" r="7" fill="var(--hole)" />
            </template>
            <template v-else-if="art === 'school'">
                <path d="M14 30c30-8 54-4 66 8v68c-12-12-36-16-66-8z" /><path d="M146 30c-30-8-54-4-66 8v68c12-12 36-16 66-8z" opacity="0.7" />
            </template>
            <template v-else-if="art === 'travel'">
                <path d="M12 76l50-6L100 8h16l-20 62 42-6 10-18h10l-6 28 6 28h-10l-10-18-42-6 20 62H100L62 82l-50-6z" />
            </template>
            <template v-else-if="art === 'bus'">
                <rect x="18" y="26" width="124" height="70" rx="16" /><rect x="30" y="40" width="42" height="24" rx="5" fill="var(--hole)" opacity="0.7" /><rect x="80" y="40" width="50" height="24" rx="5" fill="var(--hole)" opacity="0.7" />
                <circle cx="52" cy="100" r="11" /><circle cx="108" cy="100" r="11" />
            </template>
            <template v-else-if="art === 'pet'">
                <circle cx="80" cy="82" r="26" /><circle cx="42" cy="52" r="12" /><circle cx="68" cy="32" r="12" /><circle cx="94" cy="32" r="12" /><circle cx="120" cy="52" r="12" />
            </template>
            <template v-else>
                <circle cx="116" cy="74" r="34" /><circle cx="116" cy="74" r="25" fill="var(--hole)" opacity="0.7" /><text x="116" y="88" text-anchor="middle" font-size="36" font-weight="700" fill="currentColor">₱</text>
                <circle cx="62" cy="92" r="20" opacity="0.7" /><circle cx="62" cy="92" r="14" fill="var(--hole)" opacity="0.7" />
            </template>
        </g>
    </svg>
</template>
