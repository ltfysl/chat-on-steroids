<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue';
import BotController from '@/actions/App/Http/Controllers/BotController';
import { Head, Link } from '@inertiajs/vue3';
import { Activity, ArrowUpRight, Bot, Boxes, CheckCircle2, Clock3, Cpu, Plus, ShieldAlert, TerminalSquare } from '@lucide/vue';

interface Runtime { state: string; external_id: string; memory_mb: number; vcpu: number }
interface BotRow { public_id: string; name: string; state: string; model: string; runtime?: Runtime | null }
interface ActivityRow { public_id: string; type: string; summary: string; level: string; occurred_at: string }
const props = defineProps<{ metrics: { bots:number; running:number; pendingApprovals:number; events24h:number }; bots: BotRow[]; activity: ActivityRow[] }>();
const cards = [
  { label: 'Bots', value: props.metrics.bots, note: 'Persistent teammates', icon: Bot },
  { label: 'Online', value: props.metrics.running, note: 'Dedicated runtimes', icon: Boxes },
  { label: 'Approvals', value: props.metrics.pendingApprovals, note: 'Need human input', icon: ShieldAlert },
  { label: '24h events', value: props.metrics.events24h, note: 'Audited activity', icon: Activity },
];
</script>

<template>
  <Head title="Overview" />
  <AppShell>
    <div class="mx-auto max-w-[1500px]">
      <section class="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
        <div><div class="mb-2 flex items-center gap-2 text-[11px] font-medium uppercase tracking-[.16em] text-violet-300/80"><span class="size-1.5 rounded-full bg-violet-400" />Live operations</div><h1 class="text-3xl font-semibold tracking-[-.04em] md:text-[38px]">Your AI team, at a glance.</h1><p class="mt-2 max-w-xl text-sm leading-6 text-zinc-500">Persistent bots, isolated computers and human approvals in one operational surface.</p></div>
        <Link :href="BotController.create().url" class="inline-flex h-10 items-center gap-2 rounded-xl bg-white px-4 text-xs font-semibold text-black shadow-[0_8px_30px_rgba(255,255,255,.08)] transition hover:bg-zinc-200"><Plus :size="14" />New bot</Link>
      </section>

      <section class="mt-8 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article v-for="card in cards" :key="card.label" class="surface rounded-2xl p-4">
          <div class="flex items-center justify-between"><span class="text-xs text-zinc-500">{{ card.label }}</span><component :is="card.icon" :size="15" class="text-zinc-600" /></div>
          <div class="mt-5 text-[28px] font-semibold tracking-[-.04em]">{{ card.value }}</div><p class="mt-1 text-[11px] text-zinc-600">{{ card.note }}</p>
        </article>
      </section>

      <section class="mt-4 grid gap-4 xl:grid-cols-[1.5fr_.85fr]">
        <div class="surface overflow-hidden rounded-2xl">
          <div class="flex items-center justify-between border-b border-white/[.06] px-5 py-4"><div><h2 class="text-sm font-medium">Bot fleet</h2><p class="mt-1 text-[11px] text-zinc-600">Each bot owns exactly one runtime identity.</p></div><Link :href="BotController.index().url" class="flex items-center gap-1 text-[11px] text-zinc-500 hover:text-white">View all <ArrowUpRight :size="12" /></Link></div>
          <div v-if="bots.length" class="divide-y divide-white/[.055]">
            <Link v-for="bot in bots" :key="bot.public_id" :href="BotController.show({ bot: bot.public_id }).url" class="grid grid-cols-[1fr_auto] gap-4 px-5 py-4 transition hover:bg-white/[.025] md:grid-cols-[1.2fr_.8fr_.7fr_auto] md:items-center">
              <div class="flex min-w-0 items-center gap-3"><div class="grid size-9 shrink-0 place-items-center rounded-xl border border-white/[.08] bg-white/[.04]"><Bot :size="16" /></div><div class="min-w-0"><div class="truncate text-[13px] font-medium">{{ bot.name }}</div><div class="truncate text-[11px] text-zinc-600">{{ bot.model }}</div></div></div>
              <div class="hidden text-[11px] text-zinc-500 md:block"><span :class="['mr-2 inline-block size-1.5 rounded-full', bot.runtime?.state === 'running' ? 'bg-emerald-400' : 'bg-zinc-600']" />{{ bot.runtime?.state ?? 'not deployed' }}</div>
              <div class="hidden items-center gap-2 text-[11px] text-zinc-600 md:flex"><Cpu :size="13" />{{ bot.runtime ? `${bot.runtime.vcpu} vCPU · ${Math.round(bot.runtime.memory_mb/1024)} GB` : '—' }}</div>
              <ArrowUpRight :size="14" class="text-zinc-700" />
            </Link>
          </div>
          <div v-else class="grid place-items-center px-6 py-20 text-center"><div class="grid size-12 place-items-center rounded-2xl border border-white/[.07] bg-white/[.03]"><Bot :size="20" class="text-zinc-500" /></div><h3 class="mt-4 text-sm font-medium">Build your first teammate</h3><p class="mt-1 max-w-xs text-xs leading-5 text-zinc-600">Configure tools, memory and network access before the runtime ever starts.</p></div>
        </div>

        <div class="surface rounded-2xl">
          <div class="flex items-center justify-between border-b border-white/[.06] px-5 py-4"><div><h2 class="text-sm font-medium">Activity stream</h2><p class="mt-1 text-[11px] text-zinc-600">Auditable runtime events</p></div><span class="flex items-center gap-1.5 text-[10px] text-emerald-400"><span class="size-1.5 animate-pulse rounded-full bg-emerald-400" />Live</span></div>
          <div class="max-h-[520px] divide-y divide-white/[.05] overflow-auto">
            <div v-for="event in activity" :key="event.public_id" class="flex gap-3 px-5 py-3.5"><div class="mt-0.5 grid size-7 shrink-0 place-items-center rounded-lg bg-white/[.035]"><TerminalSquare v-if="event.type.includes('runtime')" :size="13" class="text-violet-300" /><CheckCircle2 v-else :size="13" class="text-zinc-500" /></div><div class="min-w-0"><div class="truncate text-[11px] text-zinc-300">{{ event.summary }}</div><div class="mt-1 flex items-center gap-1 text-[9px] text-zinc-700"><Clock3 :size="10" />{{ new Date(event.occurred_at).toLocaleString() }}</div></div></div>
            <div v-if="!activity.length" class="px-5 py-16 text-center text-xs text-zinc-600">No activity yet. Runtime events appear here.</div>
          </div>
        </div>
      </section>
    </div>
  </AppShell>
</template>
