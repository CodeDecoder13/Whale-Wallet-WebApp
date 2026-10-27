<script setup lang="ts">
import { Form, Head, useForm, usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AccountResetController from '@/actions/App/Http/Controllers/Settings/AccountResetController';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toDataUrl } from '@/lib/image';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profile settings',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

const props = defineProps<{
    pay_dates: number[];
    digest: { digest_enabled: boolean; digest_time: string };
    consented: boolean;
    hasPin: boolean;
}>();

// square-crop would be nicer, but a 128px JPEG keeps auth.user small on every page
const { getInitials } = useInitials();
const avatar = useForm({ avatar: null as string | null });

async function pickAvatar(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];

    if (file) {
        avatar.avatar = await toDataUrl(file, 160, 'image/jpeg', 0.85, true).catch(() => null);
        avatar.patch(ProfileController.avatar.url(), { preserveScroll: true });
    }
}

function removeAvatar() {
    avatar.avatar = null;
    avatar.patch(ProfileController.avatar.url(), { preserveScroll: true });
}
const payday = useForm({
    first: String(props.pay_dates[0] ?? ''),
    second: String(props.pay_dates[1] ?? ''),
});

// Whale tips refresh every day at this Philippine-time hour; the email only goes out when the box is ticked
const digest = useForm({
    digest_enabled: props.digest.digest_enabled,
    digest_time: props.digest.digest_time,
});

function saveDigest() {
    digest.patch(ProfileController.digest.url(), { preserveScroll: true });
}

// sends today's digest to this user now; a mail failure comes back as an error under the button
const digestTest = useForm({ digest_test: '' }); // only here so its error has a typed home

function sendTest() {
    digestTest.post(ProfileController.digestTest.url(), { preserveScroll: true });
}

// reset to a new account: the PIN and a code emailed to the user must both be right
const codeSent = ref(false);
const reset = useForm({ pin: '', code: '' });

function sendResetCode() {
    reset.post(AccountResetController.code.url(), {
        preserveScroll: true,
        onSuccess: () => (codeSent.value = true),
    });
}

function resetAccount() {
    if (confirm('Reset this account to new? Your cards, bills, loans, pods, transactions, income, receipts and chats are deleted for good.')) {
        reset.post(AccountResetController.reset.url());
    }
}

function savePayday() {
    payday
        .transform((d) => ({ pay_dates: [d.first, d.second].filter(Boolean) }))
        .patch(ProfileController.payday.url(), { preserveScroll: true });
}
</script>

<template>
    <Head title="Profile settings" />

    <h1 class="sr-only">Profile settings</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Profile"
            description="Update your name and email address"
        />

        <div class="flex items-center gap-4">
            <Avatar class="size-16 overflow-hidden rounded-full">
                <AvatarImage v-if="user.avatar" :src="user.avatar" :alt="user.name" class="object-cover" />
                <AvatarFallback class="bg-sidebar-accent text-lg text-sidebar-accent-foreground">
                    {{ getInitials(user.name) }}
                </AvatarFallback>
            </Avatar>
            <div class="grid gap-1">
                <label class="cursor-pointer text-sm font-medium underline underline-offset-4">
                    {{ user.avatar ? 'Change picture' : 'Upload picture' }}
                    <input
                        type="file"
                        accept="image/png,image/jpeg,image/webp"
                        class="sr-only"
                        @change="pickAvatar"
                    />
                </label>
                <button
                    v-if="user.avatar"
                    type="button"
                    class="text-left text-xs text-muted-foreground underline underline-offset-4"
                    @click="removeAvatar"
                >
                    Remove
                </button>
                <InputError :message="avatar.errors.avatar" />
            </div>
        </div>

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    placeholder="Full name"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                    placeholder="Email address"
                />
                <InputError class="mt-2" :message="errors.email" />
            </div>

            <div v-if="page.props.mustVerifyEmail && !user.email_verified_at">
                <p class="-mt-4 text-sm text-muted-foreground">
                    Your email address is unverified.
                    <Link
                        :href="send()"
                        as="button"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                    >
                        Click here to re-send the verification email.
                    </Link>
                </p>

                <div
                    v-if="page.props.status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    A new verification link has been sent to your email address.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <Button :disabled="processing" data-test="update-profile-button"
                    >Save</Button
                >
            </div>
        </Form>
    </div>

    <form id="payday" class="mt-10 space-y-6" @submit.prevent="savePayday">
        <Heading
            variant="small"
            title="Payday"
            description="The day or days of the month your income arrives. The forecast counts down to the next one."
        />
        <div class="grid max-w-sm grid-cols-2 gap-4">
            <div class="grid gap-2">
                <Label for="payday-first">Payday (day of month)</Label>
                <Input
                    id="payday-first"
                    v-model="payday.first"
                    type="number"
                    min="1"
                    max="31"
                    required
                />
            </div>
            <div class="grid gap-2">
                <Label for="payday-second">Second payday (optional)</Label>
                <Input
                    id="payday-second"
                    v-model="payday.second"
                    type="number"
                    min="1"
                    max="31"
                />
            </div>
            <InputError class="col-span-2" :message="Object.values(payday.errors)[0]"
            />
        </div>
        <Button :disabled="payday.processing">Save payday</Button>
    </form>

    <form id="digest" class="mt-10 space-y-6" @submit.prevent="saveDigest">
        <Heading
            variant="small"
            title="Daily digest"
            description="Whale writes your tips every day at the time you pick, and can email them to you. The same tips show on your dashboard."
        />
        <div class="grid max-w-sm gap-4">
            <label class="flex items-center gap-3 text-sm">
                <Checkbox
                    :model-value="digest.digest_enabled"
                    @update:model-value="digest.digest_enabled = $event === true"
                />
                Email me the daily digest
            </label>
            <div class="grid gap-2">
                <Label for="digest-time">Send at (Philippine time)</Label>
                <Input id="digest-time" v-model="digest.digest_time" type="time" required />
                <p class="text-xs text-muted-foreground">
                    9:00 AM by default. Tips refresh within a few minutes of this time.
                </p>
                <InputError :message="digest.errors.digest_time" />
            </div>
            <p v-if="!consented" class="text-xs text-muted-foreground">
                Tips need the AI notice accepted. Open Whale Chat or Tips once and tap I agree.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <Button :disabled="digest.processing">Save digest</Button>
            <Button type="button" variant="outline" :disabled="digestTest.processing" @click="sendTest">
                {{ digestTest.processing ? 'Sending...' : 'Send me a test email' }}
            </Button>
        </div>
        <p class="text-xs text-muted-foreground">The test goes to {{ user.email }} with today's tips, so you can see what the digest looks like.</p>
        <InputError :message="digestTest.errors.digest_test" />
    </form>

    <div id="appearance" class="space-y-4">
        <Heading variant="small" title="Appearance" description="Light, dark, or follow your device. Saved on this device." />
        <AppearanceTabs />
    </div>

    <div id="reset" class="space-y-4">
        <Heading
            variant="small"
            title="Reset to a new account"
            description="Deletes your cards, bills, loans, pods, transactions, income, receipts and chats and starts you over. Your login stays. It needs your 6-digit PIN and a code we email you."
        />
        <p v-if="!hasPin" class="text-sm text-muted-foreground">
            Set a login PIN first in
            <Link href="/settings/security" class="font-medium text-foreground underline underline-offset-4">Security</Link>.
        </p>
        <template v-else>
            <Button v-if="!codeSent" type="button" variant="outline" :disabled="reset.processing" @click="sendResetCode">
                Email me a code
            </Button>
            <form v-else class="grid gap-4" @submit.prevent="resetAccount">
                <div class="grid gap-2">
                    <Label for="reset-pin">Your PIN</Label>
                    <Input id="reset-pin" v-model="reset.pin" type="password" inputmode="numeric" maxlength="6" autocomplete="off" required />
                    <InputError :message="reset.errors.pin" />
                </div>
                <div class="grid gap-2">
                    <Label for="reset-code">Code from your email</Label>
                    <Input id="reset-code" v-model="reset.code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" required />
                    <InputError :message="reset.errors.code" />
                </div>
                <div class="flex gap-2">
                    <Button variant="destructive" :disabled="reset.processing || reset.pin.length !== 6 || reset.code.length !== 6">Reset account</Button>
                    <Button type="button" variant="ghost" :disabled="reset.processing" @click="sendResetCode">Send a new code</Button>
                </div>
            </form>
        </template>
    </div>

    <DeleteUser />
</template>
