<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue';
import ApprovalController from '@/actions/App/Http/Controllers/ApprovalController';
import { Head, router } from '@inertiajs/vue3';
import { Check, Clock3, ShieldAlert, X } from '@lucide/vue';

type Approval = { public_id:string; action:string; risk:string; arguments:any; status:string; expires_at?:string|null; created_at:string };
const props = defineProps<{ approvals:{ data:Approval[] } }>();
const decide = (approval:Approval, decision:'approved'|'rejected') => router.patch(ApprovalController.update({ approvalRequest: approval.public_id }).url, { decision }, { preserveScroll:true });
</script>

<template>
  <Head title="Approvals" />
  <AppShell>
    <div class="mx-auto max-w-5xl"><div><p class="text-[11px] uppercase tracking-[.16em] text-violet-300/80">Human gates</p><h1 class="mt-2 text-3xl font-semibold tracking-[-.04em]">Approval inbox</h1><p class="mt-2 text-sm text-zinc-500">High-impact bot actions stop here until a human resolves them.</p></div>
      <div class="surface mt-8 overflow-hidden rounded-2xl"><div v-if="approvals.data.length" class="divide-y divide-white/[.055]"><article v-for="approval in approvals.data" :key="approval.public_id" class="p-5"><div class="flex flex-col gap-4 md:flex-row md:items-start"><div class="grid size-9 shrink-0 place-items-center rounded-xl border border-amber-400/10 bg-amber-500/[.06]"><ShieldAlert :size="16" class="text-amber-300" /></div><div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><h2 class="text-sm font-medium">{{ approval.action }}</h2><span :class="['rounded-full border px-2 py-0.5 text-[9px] uppercase', approval.risk === 'critical' ? 'border-red-500/20 text-red-300' : 'border-amber-500/15 text-amber-300']">{{ approval.risk }}</span><span class="rounded-full border border-white/[.07] px-2 py-0.5 text-[9px] text-zinc-500">{{ approval.status }}</span></div><pre class="mt-3 overflow-x-auto rounded-xl border border-white/[.055] bg-black/25 p-3 text-[10px] leading-5 text-zinc-500">{{ JSON.stringify(approval.arguments, null, 2) }}</pre><div class="mt-3 flex items-center gap-1 text-[9px] text-zinc-700"><Clock3 :size="10" />Requested {{ new Date(approval.created_at).toLocaleString() }}</div></div><div v-if="approval.status === 'pending'" class="flex shrink-0 gap-2"><button class="grid size-9 place-items-center rounded-xl border border-red-500/15 bg-red-500/[.04] text-red-300 hover:bg-red-500/[.09]" title="Reject" @click="decide(approval,'rejected')"><X :size="15" /></button><button class="inline-flex h-9 items-center gap-2 rounded-xl bg-white px-3 text-[11px] font-semibold text-black" @click="decide(approval,'approved')"><Check :size="14" />Approve</button></div></div></article></div><div v-else class="grid place-items-center px-6 py-24 text-center"><Check :size="24" class="text-emerald-400/60" /><h2 class="mt-4 text-sm font-medium">Inbox clear</h2><p class="mt-1 text-xs text-zinc-600">No bot is waiting for human approval.</p></div></div>
    </div>
  </AppShell>
</template>
