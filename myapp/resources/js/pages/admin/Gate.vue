<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import { adminUrl } from '@/lib/adminUrl'; // plain paths, not generated ones: the same build serves /admin/gate locally and /gate on the admin host
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { InputOTP, InputOTPGroup, InputOTPSlot } from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';

defineOptions({
    layout: {
        title: 'Admin check',
        description: 'Enter your 6-digit PIN and the code we email you. It opens the admin for 2 hours.',
    },
});

defineProps<{ hasPin: boolean; codeSent: boolean; email: string }>();

const pin = ref('');
const code = ref('');
</script>

<template>
    <Head title="Admin check" />

    <p v-if="!hasPin" class="text-sm text-muted-foreground">
        Set a login PIN first in Settings &gt; Security, then come back.
    </p>

    <template v-else>
        <Form :action="adminUrl('/gate/code')" method="post" class="flex items-center justify-between gap-3 text-sm" #default="{ processing, errors }">
            <span class="text-muted-foreground">
                {{ codeSent ? `Code sent to ${email}.` : `We will email a code to ${email}.` }}
            </span>
            <Button type="submit" variant="outline" size="sm" :disabled="processing">
                {{ codeSent ? 'Send a new code' : 'Email me a code' }}
            </Button>
            <InputError :message="errors.pin" />
        </Form>

        <Form
            v-if="codeSent"
            :action="adminUrl('/gate')"
            method="post"
            reset-on-error
            class="grid gap-5"
            @error="((pin = ''), (code = ''))"
            #default="{ errors, processing }"
        >
            <input type="hidden" name="pin" :value="pin" />
            <input type="hidden" name="code" :value="code" />
            <div class="grid gap-2">
                <span class="text-sm font-medium">PIN</span>
                <InputOTP v-model="pin" :maxlength="6" :disabled="processing" autofocus class="w-full">
                    <InputOTPGroup class="w-full">
                        <InputOTPSlot v-for="i in 6" :key="i" :index="i - 1" class="h-12 w-auto flex-1 text-lg" />
                    </InputOTPGroup>
                </InputOTP>
                <InputError :message="errors.pin" />
            </div>
            <div class="grid gap-2">
                <span class="text-sm font-medium">Code from your email</span>
                <InputOTP v-model="code" :maxlength="6" :disabled="processing" class="w-full">
                    <InputOTPGroup class="w-full">
                        <InputOTPSlot v-for="i in 6" :key="i" :index="i - 1" class="h-12 w-auto flex-1 text-lg" />
                    </InputOTPGroup>
                </InputOTP>
                <InputError :message="errors.code" />
            </div>
            <Button type="submit" class="justify-self-center px-8" :disabled="processing || pin.length < 6 || code.length < 6">
                <Spinner v-if="processing" />
                Open admin
            </Button>
        </Form>
    </template>

    <div class="text-right text-sm">
        <TextLink :href="logout()" as="button">Log out</TextLink>
    </div>
</template>
