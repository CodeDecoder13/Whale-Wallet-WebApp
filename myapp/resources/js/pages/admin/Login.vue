<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ShieldCheck } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

// The admin host's own sign-in page: no sign-up, no marketing, no "remember me". The PIN and emailed code come next, at the gate.
defineProps<{ status?: string; canResetPassword: boolean }>();
</script>

<template>
    <Head title="Admin sign in" />

    <main class="grid min-h-svh place-items-center bg-linear-to-br from-navy to-navy-2 px-4 py-10">
        <div class="w-full max-w-sm rounded-2xl bg-card p-7 shadow-[0_30px_70px_-25px_rgba(0,0,0,0.6)] ring-1 ring-white/10">
            <div class="flex items-center justify-between">
                <AppLogo />
                <span class="flex items-center gap-1 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary"><ShieldCheck class="size-3.5" /> Admin</span>
            </div>

            <h1 class="mt-6 text-2xl font-semibold tracking-tight">Admin sign in</h1>
            <p class="mt-1 text-sm text-muted-foreground">Authorized staff only. After this you will confirm with your PIN and an emailed code.</p>

            <div v-if="status" class="mt-4 text-sm font-medium text-green-600">{{ status }}</div>

            <Form v-bind="store.form()" :reset-on-success="['password']" v-slot="{ errors, processing }" class="mt-6 grid gap-5">
                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input id="email" type="email" name="email" required v-focus autocomplete="username" placeholder="you@example.com" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password">Password</Label>
                        <TextLink v-if="canResetPassword" :href="request()" class="text-sm">Forgot your password?</TextLink>
                    </div>
                    <PasswordInput id="password" name="password" required autocomplete="current-password" placeholder="Password" />
                    <InputError :message="errors.password || errors.email" />
                </div>

                <Button type="submit" class="w-full" :disabled="processing" data-test="admin-login-button">
                    <Spinner v-if="processing" />
                    Sign in
                </Button>
            </Form>
        </div>
    </main>
</template>
