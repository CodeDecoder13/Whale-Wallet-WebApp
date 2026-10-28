<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { adminUrl } from '@/lib/adminUrl';

type Blocks = ({ type: 'p'; text: string } | { type: 'ul'; items: string[] })[];
type Note = { id: number; title: string; version: string | null; body: string; send_email: boolean; show_popup: boolean; status: string; email_status: string; emailed_count: number; blocks: Blocks };

const props = defineProps<{ note: Note | null; recipients: number }>();

const form = useForm({
    title: props.note?.title ?? '',
    version: props.note?.version ?? '',
    body: props.note?.body ?? '',
    send_email: props.note?.send_email ?? true,
    show_popup: props.note?.show_popup ?? true,
});
const published = computed(() => props.note?.status === 'published');

// a live preview of the body, split the same way the server does it: blank line = new paragraph, "- " = bullet
const preview = computed<Blocks>(() =>
    form.body
        .trim()
        .split(/\n{2,}/)
        .filter(Boolean)
        .flatMap((chunk) => {
            const out: Blocks = [];
            let list: string[] = [];

            for (const line of chunk.split('\n').map((l) => l.trim()).filter(Boolean)) {
                if (line.startsWith('- ')) {
                    list.push(line.slice(2).trim());
                    continue;
                }

                if (list.length) {
                    out.push({ type: 'ul', items: list });
                    list = [];
                }
                out.push({ type: 'p', text: line });
            }

            list.length && out.push({ type: 'ul', items: list });

            return out;
        }),
);

const save = () => (props.note ? form.put(adminUrl(`/release-notes/${props.note.id}`), { preserveScroll: true }) : form.post(adminUrl('/release-notes')));
const post = (what: 'publish' | 'test', ask?: string) => {
    if (ask && !confirm(ask)) {
        return;
    }

    router.post(adminUrl(`/release-notes/${props.note!.id}/${what}`), {}, { preserveScroll: true });
};
const remove = () => confirm('Delete this note? Users who already got the email keep it.') && router.delete(adminUrl(`/release-notes/${props.note!.id}`));
</script>

<template>
    <Head :title="note ? 'Edit release note' : 'New release note'" />

    <Link :href="adminUrl('/release-notes')" class="text-sm text-muted-foreground underline-offset-4 hover:underline">Back to release notes</Link>
    <Heading :title="note ? 'Edit release note' : 'New release note'" :description="published ? 'Published. You can fix wording; the email and popup are not sent again.' : 'Save it, send yourself a test, then publish.'" class="mt-2" />

    <div class="mt-6 grid items-start gap-6 lg:grid-cols-2">
        <form class="grid gap-4 rounded-2xl border bg-card p-5 shadow-sm" @submit.prevent="save">
            <div class="grid gap-2">
                <Label for="title">Title</Label>
                <Input id="title" v-model="form.title" maxlength="120" required placeholder="Support chat is here" />
                <InputError :message="form.errors.title" />
            </div>
            <div class="grid gap-2">
                <Label for="version">Version (optional)</Label>
                <Input id="version" v-model="form.version" maxlength="20" placeholder="v1.4" class="max-w-40" />
                <InputError :message="form.errors.version" />
            </div>
            <div class="grid gap-2">
                <Label for="body">What changed</Label>
                <textarea id="body" v-model="form.body" rows="10" maxlength="5000" required class="rounded-lg border bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring" placeholder="You can now talk to us from inside the app.&#10;&#10;- Open Support in the sidebar&#10;- We reply in the chat and by email" />
                <p class="text-xs text-muted-foreground">Plain text. A blank line starts a new paragraph; a line starting with "- " is a bullet.</p>
                <InputError :message="form.errors.body" />
            </div>
            <label class="flex items-center gap-3 text-sm">
                <Checkbox :model-value="form.send_email" @update:model-value="form.send_email = $event === true" :disabled="published" />
                Email it to users ({{ recipients }} will get it: verified, not suspended, not unsubscribed)
            </label>
            <label class="flex items-center gap-3 text-sm">
                <Checkbox :model-value="form.show_popup" @update:model-value="form.show_popup = $event === true" :disabled="published" />
                Show it once as a popup in the app
            </label>

            <div class="flex flex-wrap gap-2">
                <Button :disabled="form.processing">{{ note ? 'Save changes' : 'Save draft' }}</Button>
                <template v-if="note">
                    <Button type="button" variant="outline" @click="post('test')">Send me a test</Button>
                    <Button v-if="!published" type="button" variant="default" class="bg-emerald-600 hover:bg-emerald-700" @click="post('publish', `Publish this note${form.send_email ? ` and email ${recipients} users` : ''}? This cannot be undone.`)">Publish</Button>
                    <Button type="button" variant="ghost" class="text-destructive" @click="remove">Delete</Button>
                </template>
            </div>
            <InputError :message="(form.errors as Record<string, string>).test" />
            <p v-if="note?.email_status === 'sending'" class="text-xs text-muted-foreground">Emails are going out ({{ note.emailed_count }} sent so far). It continues in the background.</p>
        </form>

        <section class="rounded-2xl border bg-card p-5 shadow-sm">
            <h2 class="text-sm font-semibold">Preview</h2>
            <div class="mt-4 rounded-xl border p-4">
                <div class="text-xs font-semibold tracking-wide text-primary uppercase">What's new<template v-if="form.version"> · {{ form.version }}</template></div>
                <div class="mt-1 text-lg font-semibold">{{ form.title || 'Your title' }}</div>
                <div class="mt-3 grid gap-3 text-sm">
                    <template v-for="(b, i) in preview" :key="i">
                        <p v-if="b.type === 'p'">{{ b.text }}</p>
                        <ul v-else class="list-disc space-y-1.5 pl-5"><li v-for="item in b.items" :key="item">{{ item }}</li></ul>
                    </template>
                    <p v-if="!preview.length" class="text-muted-foreground">Your text appears here.</p>
                </div>
            </div>
        </section>
    </div>
</template>
