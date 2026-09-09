<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue';
import BotController from '@/actions/App/Http/Controllers/BotController';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowUpRight, Bot, Boxes, Cpu, MemoryStick, Plus, Search } from '@lucide/vue';
import { computed, ref } from 'vue';

interface Runtime { state:string; provider:string; external_id:string; vcpu:number; memory_mb:number; generation:number }
interface BotRow { public_id:string; name:string; slug:string; description?:string; state:string; model:string; current_version:number; runtime?:Runtime|null }
const props = defineProps<{ bots: BotRow[] }>();
const query = ref('');
const visible = computed(() => props.bots.filter((bot) => `${bot.name} ${bot.slug} ${bot.model}`.toLowerCase().includes(query.value.toLowerCase())));
</script>

<template>
  <Head title="Bots" />
  <AppShell>
    <div class="mx-auto max-w-[1450px]">
      <div class="flex flex-col gap-5 md:flex-row md:items-end md:justify-between"><div><p class="text-[11px] font-medium uppercase tracking-[.16em] text-violet-300/80">Fleet</p><h1 class="mt-2 text-3xl font-semibold tracking-[-.04em]">Persistent teammates</h1><p class="mt-2 text-sm text-zinc-500">Configure identity, tools, memory, network policy and dedicated compute.</p></div><Link :href="BotController.create().url" class="inline-flex h-10 items-center gap-2 rounded-xl bg-white px-4 text-xs font-semibold text-black"><Plus :size="14" />New bot</Link></div>
      <div class="surface mt-8 overflow-hidden rounded-2xl">
        <div class="border-b border-white/[.06] p-4"><label class="flex max-w-sm items-center gap-2 rounded-xl border border-white/[.07] bg-black/20 px-3 py-2.5"><Search :size="14" class="text-zinc-600" /><input v-model="query" class="w-full bg-transparent text-xs outline-none placeholder:text-zinc-700" placeholder="Search fleet" /></label></div>
        <div class="grid gap-px bg-white/[.055] sm:grid-cols-2 xl:grid-cols-3">
          <Link v-for="bot in visible" :key="bot.public_id" :href="BotController.show({ bot: bot.public_id }).url" class="group bg-[#0d0f14] p-5 transition hover:bg-[#11141b]">
            <div class="flex items-start justify-between"><div class="grid size-10 place-items-center rounded-xl border border-white/[.08] bg-white/[.035]"><Bot :size="18" /></div><div class="flex items-center gap-1.5 rounded-full border border-white/[.07] px-2 py-1 text-[9px] text-zinc-500"><span :class="['size-1.5 rounded-full', bot.runtime?.state === 'running' ? 'bg-emerald-400' : 'bg-zinc-600']" />{{ bot.runtime?.state ?? 'not deployed' }}</div></div>
            <h2 class="mt-5 flex items-center gap-1.5 text-[15px] font-medium">{{ bot.name }}<ArrowUpRight :size="13" class="text-zinc-700 transition group-hover:text-zinc-400" /></h2><p class="mt-1 line-clamp-2 min-h-9 text-[11px] leading-[18px] text-zinc-600">{{ bot.description || 'No description yet.' }}</p>
            <div class="mt-5 grid grid-cols-3 gap-2 border-t border-white/[.055] pt-4 text-[10px] text-zinc-600"><span class="flex items-center gap-1"><Boxes :size="11" />v{{ bot.current_version }}</span><span class="flex items-center gap-1"><Cpu :size="11" />{{ bot.runtime?.vcpu ?? '—' }}</span><span class="flex items-center gap-1"><MemoryStick :size="11" />{{ bot.runtime ? `${Math.round(bot.runtime.memory_mb/1024)} GB` : '—' }}</span></div>
          </Link>
        </div>
        <div v-if="!visible.length" class="grid place-items-center px-6 py-20 text-center"><Bot :size="24" class="text-zinc-700" /><h3 class="mt-4 text-sm font-medium">No bots found</h3><p class="mt-1 text-xs text-zinc-600">Adjust your search or create a teammate.</p></div>
      </div>
    </div>
  </AppShell>
</template>
