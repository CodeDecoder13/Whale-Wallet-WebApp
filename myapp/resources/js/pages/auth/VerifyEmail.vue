<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { otp, send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Enter your code',
        description: 'We emailed a 6-digit code to confirm your email address. It works for 10 minutes.',
    },
});

defineProps<{
    status?: string;
}>();

const code = ref('');
</script>

<template>
    <Head title="Verify your email" />

    <div
        v-if="status === 'verification-link-sent'"
        class="text-sm font-medium text-green-600"
    >
        A new code is on its way to your email.
    </div>

    <Form
        v-bind="otp.form()"
        reset-on-error
        class="grid gap-5"
        @error="code = ''"
        #default="{ errors, processing }"
    >
        <input type="hidden" name="code" :value="code" />
        <InputOTP v-model="code" :maxlength="6" :disabled="processing" autofocus>
            <InputOTPGroup>
                <InputOTPSlot v-for="i in 6" :key="i" :index="i - 1" />
            </InputOTPGroup>
        </InputOTP>
        <InputError :message="errors.code" />
        <Button type="submit" class="w-full" :disabled="processing || code.length < 6">
            <Spinner v-if="processing" />
            Verify and continue
        </Button>
    </Form>

    <Form v-bind="send.form()" class="flex items-center justify-between text-sm" v-slot="{ processing }">
        <Button :disabled="processing" variant="ghost" size="sm">Send a new code</Button>
        <TextLink :href="logout()" as="button">Log out</TextLink>
    </Form>
</template>