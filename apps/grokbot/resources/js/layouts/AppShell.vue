<script setup lang="ts">
import BotController from '@/actions/App/Http/Controllers/BotController';
import { dashboard, logout } from '@/routes';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Activity, Bot, Boxes, Command, LogOut, Radio, Search, Settings, ShieldCheck, Sparkles } from '@lucide/vue';
import { computed } from 'vue';

const page = usePage();
const auth = computed(() => page.props.auth as any);
const navigation = [
  { label: 'Overview', href: () => dashboard().url, icon: Activity },
  { label: 'Bots', href: () => BotController.index().url, icon: Bot },
  { label: 'Runtimes', href: () => BotController.index().url + '?view=runtimes', icon: Boxes },
  { label: 'Bus', href: () => dashboard().url + '?panel=bus', icon: Radio },
  { label: 'Approvals', href: () => dashboard().url + '?panel=approvals', icon: ShieldCheck },
];
</script>

<template>
  <div class="min-h-screen text-zinc-100">
    <aside class="fixed inset-y-0 left-0 z-40 hidden w-[268px] border-r border-white/[.07] bg-black/25 px-4 py-5 backdrop-blur-2xl lg:flex lg:flex-col">
      <div class="flex items-center gap-3 px-2">
        <div class="grid size-9 place-items-center rounded-xl border border-violet-400/20 bg-violet-500/10 shadow-[inset_0_1px_0_rgba(255,255,255,.08)]"><Sparkles :size="18" /></div>
        <div><div class="text-[14px] font-semibold tracking-tight">Grokbot</div><div class="text-[11px] text-zinc-500">Control plane</div></div>
      </div>
      <div class="mt-6 flex items-center gap-2 rounded-xl border border-white/[.06] bg-white/[.035] px-3 py-2.5 text-xs text-zinc-500"><Search :size="14" /><span>Search bots, runs, memory</span><kbd class="ml-auto rounded border border-white/10 px-1.5 py-0.5 text-[10px]">⌘K</kbd></div>
      <nav class="mt-6 space-y-1">
        <Link v-for="item in navigation" :key="item.label" :href="item.href()" class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13px] text-zinc-400 transition hover:bg-white/[.05] hover:text-white">
          <component :is="item.icon" :size="16" class="text-zinc-500 group-hover:text-violet-300" />{{ item.label }}
        </Link>
      </nav>
      <div class="mt-auto rounded-2xl border border-white/[.07] bg-white/[.03] p-3">
        <div class="flex items-center gap-3"><div class="grid size-8 place-items-center rounded-full bg-gradient-to-br from-violet-400 to-indigo-600 text-xs font-semibold">{{ auth.user?.name?.slice(0, 2).toUpperCase() }}</div><div class="min-w-0"><div class="truncate text-xs font-medium">{{ auth.user?.name }}</div><div class="truncate text-[10px] text-zinc-500">{{ auth.workspace?.name }} · {{ auth.role }}</div></div></div>
        <div class="mt-3 flex gap-2"><button class="flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-white/[.07] py-1.5 text-[11px] text-zinc-400 hover:text-white"><Settings :size="12" />Settings</button><button class="rounded-lg border border-white/[.07] px-2.5 text-zinc-500 hover:text-white" @click="router.post(logout().url)"><LogOut :size="13" /></button></div>
      </div>
    </aside>

    <main class="min-h-screen lg:pl-[268px]">
      <header class="sticky top-0 z-30 flex h-16 items-center border-b border-white/[.06] bg-[#07080b]/70 px-5 backdrop-blur-xl lg:px-8">
        <div class="flex items-center gap-2 text-xs text-zinc-500"><Command :size="14" /><span>{{ auth.workspace?.name }}</span><span>/</span><span class="text-zinc-200">Operations</span></div>
        <div class="ml-auto flex items-center gap-2"><div class="flex items-center gap-2 rounded-full border border-emerald-500/15 bg-emerald-500/[.06] px-2.5 py-1 text-[10px] text-emerald-300"><span class="status-dot size-1.5 rounded-full bg-emerald-400" />Realtime healthy</div></div>
      </header>
      <div class="px-5 py-7 lg:px-8 lg:py-8"><slot /></div>
    </main>
  </div>
</template>
