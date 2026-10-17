const fs = require('fs');
let s = fs.readFileSync('apply-sidebar.cjs', 'utf8');
const r = (a, b) => { if (!s.includes(a)) throw new Error(a.slice(0, 50)); s = s.replace(a, () => b); };
r(`            <div class="flex size-16 items-center justify-center [&_*]:size-16 [&_*]:text-lg">
                <UserInfo :user="user" />
            </div>`, `            <Avatar class="size-16 overflow-hidden rounded-full">
                <AvatarImage v-if="user.avatar" :src="user.avatar" :alt="user.name" />
                <AvatarFallback class="bg-sidebar-accent text-lg text-sidebar-accent-foreground">
                    {{ getInitials(user.name) }}
                </AvatarFallback>
            </Avatar>`);
r(`["import Heading from '@/components/Heading.vue';", "import Heading from '@/components/Heading.vue';\nimport UserInfo from '@/components/UserInfo.vue';"],`, `["import Heading from '@/components/Heading.vue';", "import Heading from '@/components/Heading.vue';\nimport { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';\nimport { useInitials } from '@/composables/useInitials';"],`);
r("const avatar = useForm(", "const { getInitials } = useInitials();\nconst avatar = useForm(");
fs.writeFileSync('apply-sidebar.cjs', s);
