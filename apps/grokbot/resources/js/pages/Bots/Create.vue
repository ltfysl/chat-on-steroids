<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue';
import BotController from '@/actions/App/Http/Controllers/BotController';
import { Head, useForm } from '@inertiajs/vue3';
import { Bot, BrainCircuit, Cpu, Globe2, Save, Wrench } from '@lucide/vue';

const form = useForm({
  name: '', slug: '', description: '', model: 'gpt-5.6', system_prompt: '', tools: [] as string[], memory_enabled: true,
  runtime_profile: { vcpu: 2, memory_mb: 4096, disk_gb: 20, image: 'grokbot-runtime:stable' },
  network_policy: { mode: 'deny-by-default', allow: [] as string[] },
});
const toolOptions = ['browser', 'terminal', 'files', 'git', 'http', 'computer'];
const syncSlug = () => { if (!form.slug) form.slug = form.name.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, ''); };
const submit = () => form.submit(BotController.store());
</script>

<template>
  <Head title="New bot" />
  <AppShell>
    <form class="mx-auto max-w-5xl" @submit.prevent="submit">
      <div class="flex items-end justify-between"><div><p class="text-[11px] uppercase tracking-[.16em] text-violet-300/80">New teammate</p><h1 class="mt-2 text-3xl font-semibold tracking-[-.04em]">Define the bot before compute exists.</h1><p class="mt-2 text-sm text-zinc-500">Policies are stored and versioned before the first VM can start.</p></div><button :disabled="form.processing" class="inline-flex h-10 items-center gap-2 rounded-xl bg-white px-4 text-xs font-semibold text-black disabled:opacity-50"><Save :size="14" />Create bot</button></div>
      <div class="mt-8 grid gap-4 lg:grid-cols-2">
        <section class="surface rounded-2xl p-5"><div class="flex items-center gap-2 text-sm font-medium"><Bot :size="16" />Identity</div><div class="mt-5 space-y-4"><label class="block text-[11px] text-zinc-500">Name<input v-model="form.name" required maxlength="100" class="mt-1.5 w-full rounded-xl border border-white/[.08] bg-black/25 px-3 py-2.5 text-sm outline-none focus:border-violet-400/40" @blur="syncSlug" /></label><label class="block text-[11px] text-zinc-500">Slug<input v-model="form.slug" required class="mt-1.5 w-full rounded-xl border border-white/[.08] bg-black/25 px-3 py-2.5 text-sm outline-none" /></label><label class="block text-[11px] text-zinc-500">Description<textarea v-model="form.description" rows="3" class="mt-1.5 w-full resize-none rounded-xl border border-white/[.08] bg-black/25 px-3 py-2.5 text-sm outline-none" /></label></div></section>
        <section class="surface rounded-2xl p-5"><div class="flex items-center gap-2 text-sm font-medium"><BrainCircuit :size="16" />Intelligence</div><div class="mt-5 space-y-4"><label class="block text-[11px] text-zinc-500">Model<input v-model="form.model" class="mt-1.5 w-full rounded-xl border border-white/[.08] bg-black/25 px-3 py-2.5 text-sm outline-none" /></label><label class="block text-[11px] text-zinc-500">System prompt<textarea v-model="form.system_prompt" rows="5" class="mt-1.5 w-full resize-none rounded-xl border border-white/[.08] bg-black/25 px-3 py-2.5 text-sm leading-6 outline-none" placeholder="Role, constraints, workflow, definition of done…" /></label><label class="flex items-center justify-between rounded-xl border border-white/[.07] bg-white/[.02] p-3 text-xs"><span>Persistent memory</span><input v-model="form.memory_enabled" type="checkbox" /></label></div></section>
        <section class="surface rounded-2xl p-5"><div class="flex items-center gap-2 text-sm font-medium"><Wrench :size="16" />Capabilities</div><div class="mt-5 grid grid-cols-2 gap-2 sm:grid-cols-3"><label v-for="tool in toolOptions" :key="tool" class="flex items-center gap-2 rounded-xl border border-white/[.07] bg-white/[.02] p-3 text-xs capitalize"><input v-model="form.tools" type="checkbox" :value="tool" />{{ tool }}</label></div></section>
        <section class="surface rounded-2xl p-5"><div class="flex items-center gap-2 text-sm font-medium"><Cpu :size="16" />Dedicated compute</div><div class="mt-5 grid grid-cols-3 gap-3"><label class="text-[10px] text-zinc-500">vCPU<input v-model.number="form.runtime_profile.vcpu" type="number" min="1" max="32" class="mt-1.5 w-full rounded-xl border border-white/[.08] bg-black/25 px-3 py-2.5 text-sm" /></label><label class="text-[10px] text-zinc-500">Memory MB<input v-model.number="form.runtime_profile.memory_mb" type="number" min="256" step="256" class="mt-1.5 w-full rounded-xl border border-white/[.08] bg-black/25 px-3 py-2.5 text-sm" /></label><label class="text-[10px] text-zinc-500">Disk GB<input v-model.number="form.runtime_profile.disk_gb" type="number" min="1" class="mt-1.5 w-full rounded-xl border border-white/[.08] bg-black/25 px-3 py-2.5 text-sm" /></label></div><div class="mt-4 flex items-center gap-2 rounded-xl border border-emerald-500/10 bg-emerald-500/[.035] p-3 text-[11px] text-emerald-200/70"><Globe2 :size="13" />Network starts deny-by-default. Explicit allowlists can be added later.</div></section>
      </div>
      <div v-if="Object.keys(form.errors).length" class="mt-4 rounded-xl border border-red-500/15 bg-red-500/[.05] p-4 text-xs text-red-300"><div v-for="(error,key) in form.errors" :key="key">{{ error }}</div></div>
    </form>
  </AppShell>
</template>
