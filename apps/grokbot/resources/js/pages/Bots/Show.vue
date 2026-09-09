<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue';
import BotDeploymentController from '@/actions/App/Http/Controllers/BotDeploymentController';
import { Head, router } from '@inertiajs/vue3';
import { Activity, Bot, Boxes, Cpu, HardDrive, Play, Radio, ShieldCheck, Square, TerminalSquare } from '@lucide/vue';

interface Runtime { provider:string; external_id:string; state:string; vcpu:number; memory_mb:number; disk_gb:number; image:string; generation:number; last_heartbeat_at?:string }
interface BotView { public_id:string; name:string; slug:string; description?:string; state:string; model:string; tools?:string[]; network_policy?:any; runtime_profile?:any; memory_enabled:boolean; current_version:number; runtime?:Runtime|null }
interface ActivityRow { public_id:string; type:string; level:string; summary:string; payload?:any; occurred_at:string }
const props = defineProps<{ bot:BotView; activities:ActivityRow[] }>();
const deploy = () => router.post(BotDeploymentController.store({ bot: props.bot.public_id }).url);
const stop = () => router.delete(BotDeploymentController.destroy({ bot: props.bot.public_id }).url);
</script>

<template>
  <Head :title="bot.name" />
  <AppShell>
    <div class="mx-auto max-w-[1450px]">
      <div class="flex flex-col gap-5 md:flex-row md:items-start md:justify-between"><div class="flex items-start gap-4"><div class="grid size-12 place-items-center rounded-2xl border border-violet-400/15 bg-violet-500/[.08]"><Bot :size="21" /></div><div><div class="flex items-center gap-2"><h1 class="text-2xl font-semibold tracking-[-.035em]">{{ bot.name }}</h1><span class="rounded-full border border-white/[.07] px-2 py-0.5 text-[9px] text-zinc-500">v{{ bot.current_version }}</span></div><p class="mt-1 text-xs text-zinc-500">{{ bot.slug }} · {{ bot.model }}</p><p class="mt-3 max-w-2xl text-sm leading-6 text-zinc-500">{{ bot.description || 'No description.' }}</p></div></div><div class="flex gap-2"><button v-if="bot.runtime?.state === 'running'" class="inline-flex h-10 items-center gap-2 rounded-xl border border-white/[.09] bg-white/[.035] px-4 text-xs text-zinc-300" @click="stop"><Square :size="13" />Stop runtime</button><button v-else class="inline-flex h-10 items-center gap-2 rounded-xl bg-white px-4 text-xs font-semibold text-black" @click="deploy"><Play :size="13" />Deploy bot</button></div></div>

      <div class="mt-8 grid gap-4 xl:grid-cols-[1.45fr_.8fr]">
        <div class="space-y-4">
          <section class="surface rounded-2xl p-5"><div class="flex items-center justify-between"><div><h2 class="text-sm font-medium">Dedicated runtime</h2><p class="mt-1 text-[11px] text-zinc-600">This compute identity is never shared with another bot.</p></div><div class="flex items-center gap-2 text-[10px]" :class="bot.runtime?.state === 'running' ? 'text-emerald-400' : 'text-zinc-600'"><span :class="['size-1.5 rounded-full', bot.runtime?.state === 'running' ? 'bg-emerald-400' : 'bg-zinc-600']" />{{ bot.runtime?.state ?? 'not provisioned' }}</div></div>
            <div v-if="bot.runtime" class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><div class="rounded-xl border border-white/[.06] bg-black/20 p-3"><Cpu :size="13" class="text-zinc-600" /><div class="mt-3 text-lg font-medium">{{ bot.runtime.vcpu }}</div><div class="text-[10px] text-zinc-600">vCPU</div></div><div class="rounded-xl border border-white/[.06] bg-black/20 p-3"><Boxes :size="13" class="text-zinc-600" /><div class="mt-3 text-lg font-medium">{{ Math.round(bot.runtime.memory_mb/1024) }} GB</div><div class="text-[10px] text-zinc-600">Memory</div></div><div class="rounded-xl border border-white/[.06] bg-black/20 p-3"><HardDrive :size="13" class="text-zinc-600" /><div class="mt-3 text-lg font-medium">{{ bot.runtime.disk_gb }} GB</div><div class="text-[10px] text-zinc-600">Disk</div></div><div class="rounded-xl border border-white/[.06] bg-black/20 p-3"><Radio :size="13" class="text-zinc-600" /><div class="mt-3 truncate text-xs font-medium">{{ bot.runtime.provider }}</div><div class="text-[10px] text-zinc-600">Provider · gen {{ bot.runtime.generation }}</div></div></div>
            <div v-else class="mt-5 rounded-xl border border-dashed border-white/[.08] px-4 py-8 text-center text-xs text-zinc-600">No compute exists yet. Deployment will provision a dedicated VM from the saved policy.</div>
          </section>
          <section class="surface rounded-2xl"><div class="border-b border-white/[.06] px-5 py-4"><h2 class="flex items-center gap-2 text-sm font-medium"><Activity :size="15" />Activity</h2></div><div class="divide-y divide-white/[.05]"><div v-for="event in activities" :key="event.public_id" class="flex gap-3 px-5 py-3.5"><div class="mt-0.5 grid size-7 place-items-center rounded-lg bg-white/[.035]"><TerminalSquare :size="13" class="text-violet-300" /></div><div><div class="text-[11px] text-zinc-300">{{ event.summary }}</div><div class="mt-1 text-[9px] text-zinc-700">{{ event.type }} · {{ new Date(event.occurred_at).toLocaleString() }}</div></div></div><div v-if="!activities.length" class="px-5 py-16 text-center text-xs text-zinc-600">No runtime activity yet.</div></div></section>
        </div>
        <aside class="space-y-4"><section class="surface rounded-2xl p-5"><h2 class="flex items-center gap-2 text-sm font-medium"><ShieldCheck :size="15" />Policy snapshot</h2><dl class="mt-5 space-y-4 text-xs"><div><dt class="text-[10px] uppercase tracking-wider text-zinc-600">Memory</dt><dd class="mt-1.5">{{ bot.memory_enabled ? 'Persistent' : 'Disabled' }}</dd></div><div><dt class="text-[10px] uppercase tracking-wider text-zinc-600">Tools</dt><dd class="mt-2 flex flex-wrap gap-1.5"><span v-for="tool in bot.tools" :key="tool" class="rounded-lg border border-white/[.07] bg-white/[.03] px-2 py-1 text-[10px] text-zinc-400">{{ tool }}</span><span v-if="!bot.tools?.length" class="text-zinc-600">None</span></dd></div><div><dt class="text-[10px] uppercase tracking-wider text-zinc-600">Network</dt><dd class="mt-1.5 text-zinc-400">{{ bot.network_policy?.mode ?? 'deny-by-default' }}</dd></div></dl></section></aside>
      </div>
    </div>
  </AppShell>
</template>
