<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { type SharedData, type User } from '@/types';
import { ArrowLeft, CircleUserRound, LogOut, Palette, ShieldCheck } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * The shell shared by every settings page: header, settings navigation and account identity.
 * Pages supply only their own content through the default slot.
 */
withDefaults(defineProps<{ title?: string }>(), { title: 'Settings' });

const page = usePage<SharedData>();
// Reactive, so the sidebar reflects a name change the moment it is saved.
const user = computed(() => page.props.auth.user as User);

const initials = computed(
    () =>
        user.value.name
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((word) => word.charAt(0).toUpperCase())
            .join('') || '?',
);

const navItems = [
    { label: 'Profile', href: '/settings/profile', icon: CircleUserRound },
    { label: 'Password', href: '/settings/password', icon: ShieldCheck },
    { label: 'Appearance', href: '/settings/appearance', icon: Palette },
];

const isCurrent = (href: string) => page.url.split('?')[0] === href;

const logout = () => router.post(route('logout'));
</script>

<template>
    <div class="min-h-screen bg-[#F4F7F7] text-[#0F172A] dark:bg-[#0F172A] dark:text-slate-100">
        <header class="sticky top-0 z-40 border-b border-[#E1E8E7] bg-white/95 backdrop-blur dark:border-slate-800 dark:bg-slate-900/95">
            <nav class="mx-auto flex h-16 max-w-5xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8" aria-label="Main navigation">
                <Link :href="route('dashboard')" class="flex shrink-0 items-center rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40" aria-label="quickFolio dashboard">
                    <AppLogo size="md" />
                </Link>

                <!-- Always has an icon: the old button was an empty teal box on mobile -->
                <Link
                    :href="route('dashboard')"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-[#DCE6E4] bg-white px-3.5 text-sm font-semibold text-[#334155] transition hover:border-[#00695C] hover:text-[#00695C] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-teal-400 dark:hover:text-teal-300"
                >
                    <ArrowLeft class="h-4 w-4" aria-hidden="true" />
                    Dashboard
                </Link>
            </nav>
        </header>

        <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
            <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ title }}</h1>

            <div class="mt-6 grid gap-6 lg:mt-8 lg:grid-cols-[220px_minmax(0,1fr)]">
                <aside class="min-w-0">
                    <div class="hidden items-center gap-3 px-2 pb-5 lg:flex">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[#E8F4F1] text-sm font-bold text-[#00695C] dark:bg-teal-900/50 dark:text-teal-300" aria-hidden="true">{{ initials }}</span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">{{ user.name }}</p>
                            <p class="truncate text-xs text-[#64748B] dark:text-slate-400">{{ user.email }}</p>
                        </div>
                    </div>

                    <nav aria-label="Settings" class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:-mx-6 sm:px-6 lg:mx-0 lg:flex-col lg:gap-1 lg:overflow-visible lg:px-0 lg:pb-0">
                        <Link
                            v-for="item in navItems"
                            :key="item.href"
                            :href="item.href"
                            :aria-current="isCurrent(item.href) ? 'page' : undefined"
                            class="inline-flex min-h-10 shrink-0 items-center gap-3 rounded-xl border px-3.5 text-sm font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40 lg:border-transparent"
                            :class="isCurrent(item.href)
                                ? 'border-[#BFD4CE] bg-[#E8F4F1] font-semibold text-[#00695C] dark:border-teal-900 dark:bg-teal-900/40 dark:text-teal-300'
                                : 'border-[#E1E8E7] bg-white text-[#64748B] hover:bg-[#F4F7F7] hover:text-[#00695C] dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-teal-300 lg:bg-transparent dark:lg:bg-transparent'"
                        >
                            <component :is="item.icon" class="h-4 w-4" aria-hidden="true" />
                            {{ item.label }}
                        </Link>

                        <button
                            type="button"
                            class="inline-flex min-h-10 shrink-0 items-center gap-3 rounded-xl border border-[#E1E8E7] bg-white px-3.5 text-sm font-medium text-[#64748B] transition hover:border-red-200 hover:bg-red-50 hover:text-red-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-300 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-red-900 dark:hover:bg-red-950/40 dark:hover:text-red-400 lg:mt-3 lg:border-transparent lg:bg-transparent dark:lg:bg-transparent"
                            @click="logout"
                        >
                            <LogOut class="h-4 w-4" aria-hidden="true" />
                            Log out
                        </button>
                    </nav>
                </aside>

                <div class="min-w-0 space-y-6">
                    <slot />
                </div>
            </div>
        </main>
    </div>
</template>
