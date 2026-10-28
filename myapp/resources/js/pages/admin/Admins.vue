<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { UserPlus } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SelectNative from '@/components/SelectNative.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { adminUrl } from '@/lib/adminUrl';
import { ago } from '@/lib/ago';

type Admin = { id: number; name: string; email: string; role: string; me: boolean; last_gate_at: string | null; login_alerts: string | null; suspended: boolean };

defineProps<{ admins: Admin[] }>();

const form = useForm({ email: '', role: 'support' });
const add = () => form.post(adminUrl('/admins'), { preserveScroll: true, onSuccess: () => form.reset('email') });

const setRole = (a: Admin, role: string) => router.put(adminUrl(`/admins/${a.id}`), { role }, { preserveScroll: true });
const remove = (a: Admin) =>
    confirm(`Remove ${a.name} as an admin? They keep their normal Whale Wallet account, and lose admin access right away.`) &&
    router.delete(adminUrl(`/admins/${a.id}`), { preserveScroll: true });

const alerts: Record<string, string> = { all: 'every sign-in', new: 'new devices', off: 'off' };
</script>

<template>
    <Head title="Admins" />

    <Heading title="Admins" description="Who can open the admin. Every change is logged and emailed to all owners." />

    <div class="mt-6 grid items-start gap-6 xl:grid-cols-[1fr_22rem]">
        <div class="overflow-x-auto rounded-2xl border bg-card shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="border-b text-xs text-muted-foreground">
                    <tr><th class="p-3.5">Admin</th><th class="p-3.5">Role</th><th class="p-3.5">Last opened the admin</th><th class="p-3.5">Login alerts</th><th class="p-3.5"></th></tr>
                </thead>
                <tbody>
                    <tr v-for="a in admins" :key="a.id" class="border-b last:border-0 hover:bg-accent/40">
                        <td class="p-3.5">
                            <span class="font-semibold">{{ a.name }}</span><span v-if="a.me" class="ml-1 text-muted-foreground">(you)</span>
                            <span v-if="a.suspended" class="ml-1 rounded-full bg-destructive/10 px-2 py-0.5 text-xs font-semibold text-destructive">suspended</span>
                            <div class="text-xs text-muted-foreground">{{ a.email }}</div>
                        </td>
                        <td class="p-3.5">
                            <span v-if="a.me" class="capitalize">{{ a.role }}</span>
                            <div v-else class="w-32"><SelectNative :model-value="a.role" :aria-label="`Role of ${a.name}`" @update:model-value="setRole(a, $event as string)"><option value="owner">Owner</option><option value="support">Support</option></SelectNative></div>
                        </td>
                        <td class="p-3.5 whitespace-nowrap">{{ ago(a.last_gate_at) }}</td>
                        <td class="p-3.5">{{ a.login_alerts ? alerts[a.login_alerts] : '-' }}</td>
                        <td class="p-3.5 text-right"><Button v-if="!a.me" variant="ghost" size="sm" class="text-destructive" @click="remove(a)">Remove</Button></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <section class="rounded-2xl border bg-card p-5 shadow-sm">
            <h2 class="flex items-center gap-2 text-sm font-semibold"><UserPlus class="size-4" /> Add an admin</h2>
            <p class="mt-2 text-sm text-muted-foreground">They need a verified Whale Wallet account and a login PIN (Settings &gt; Security). They get an email, and confirm with their PIN and an emailed code when they open the admin.</p>
            <form class="mt-4 grid gap-3" @submit.prevent="add">
                <Input v-model="form.email" type="email" required placeholder="their@email.com" aria-label="Email of the person" />
                <InputError :message="form.errors.email" />
                <SelectNative v-model="form.role" aria-label="Role">
                    <option value="support">Support: the support chat only</option>
                    <option value="owner">Owner: everything, including this page</option>
                </SelectNative>
                <InputError :message="form.errors.role" />
                <Button :disabled="form.processing">Add admin</Button>
            </form>
        </section>
    </div>
</template>
