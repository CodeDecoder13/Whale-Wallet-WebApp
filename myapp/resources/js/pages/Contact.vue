<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Doc from '@/components/public/Doc.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SelectNative from '@/components/SelectNative.vue';

const props = defineProps<{ topics: string[] }>();

const form = useForm({
    name: '',
    email: '',
    topic: props.topics[0],
    message: '',
    website: '', // honeypot: hidden from people, filled by bots
});

const send = () =>
    form.post('/contact', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
</script>

<template>
    <Doc
        title="Contact"
        description="Questions, feedback or a privacy request? Check the documentation first, then write to us."
    >
        <p>
            Start with the <a href="/docs">documentation</a>. You can also email
            <a href="mailto:support@whalewallet.cloud"
                >support@whalewallet.cloud</a
            >
            or use the form below. For access, correction or deletion of your
            data, choose the topic “Privacy request”.
        </p>

        <form class="mt-8 grid gap-4" @submit.prevent="send">
            <div class="grid gap-2">
                <Label for="c-name">Name</Label>
                <Input
                    id="c-name"
                    v-model="form.name"
                    required
                    maxlength="80"
                    autocomplete="name"
                />
                <InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-2">
                <Label for="c-email">Email</Label>
                <Input
                    id="c-email"
                    v-model="form.email"
                    type="email"
                    required
                    maxlength="120"
                    autocomplete="email"
                />
                <InputError :message="form.errors.email" />
            </div>
            <div class="grid gap-2">
                <Label for="c-topic">Topic</Label>
                <SelectNative id="c-topic" v-model="form.topic">
                    <option v-for="t in topics" :key="t" :value="t">
                        {{ t }}
                    </option>
                </SelectNative>
                <InputError :message="form.errors.topic" />
            </div>
            <div class="grid gap-2">
                <Label for="c-message">Message</Label>
                <textarea
                    id="c-message"
                    v-model="form.message"
                    required
                    maxlength="4000"
                    rows="6"
                    class="w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <InputError :message="form.errors.message" />
            </div>
            <input
                v-model="form.website"
                name="website"
                class="hidden"
                tabindex="-1"
                autocomplete="off"
                aria-hidden="true"
            />
            <p class="text-sm text-muted-foreground">
                We use your details only to reply. See our
                <a href="/privacy-policy">Privacy Policy</a>.
            </p>
            <div><Button :disabled="form.processing">Send message</Button></div>
        </form>
    </Doc>
</template>
