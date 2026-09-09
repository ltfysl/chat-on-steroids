<script setup lang="ts">
import AuthController from '@/actions/App/Http/Controllers/AuthController';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowRight, Building2, Sparkles } from '@lucide/vue';

const form = useForm({ name: '', email: '', workspace: '', password: '', password_confirmation: '' });
</script>

<template>
  <Head title="Create workspace" />
  <main class="grid min-h-screen place-items-center px-5 py-10">
    <div class="w-full max-w-[460px]">
      <div class="mb-8 flex items-center gap-3"><div class="grid size-10 place-items-center rounded-2xl border border-violet-400/20 bg-violet-500/10"><Sparkles :size="18" /></div><div><div class="text-sm font-semibold">Grokbot</div><div class="text-[10px] text-zinc-600">Persistent AI operations</div></div></div>
      <form class="surface rounded-[24px] p-6 md:p-7" @submit.prevent="form.submit(AuthController.store())">
        <div class="grid size-9 place-items-center rounded-xl border border-white/[.07] bg-white/[.03]"><Building2 :size="16" /></div><h1 class="mt-5 text-2xl font-semibold tracking-[-.04em]">Create your control plane.</h1><p class="mt-2 text-xs leading-5 text-zinc-500">Your first isolated workspace and owner account are created atomically.</p>
        <div class="mt-7 grid gap-4 sm:grid-cols-2"><label class="block text-[11px] text-zinc-500">Your name<input v-model="form.name" required class="mt-1.5 w-full rounded-xl border border-white/[.08] bg-black/30 px-3 py-2.5 text-sm outline-none" /></label><label class="block text-[11px] text-zinc-500">Workspace<input v-model="form.workspace" required class="mt-1.5 w-full rounded-xl border border-white/[.08] bg-black/30 px-3 py-2.5 text-sm outline-none" /></label><label class="block text-[11px] text-zinc-500 sm:col-span-2">Email<input v-model="form.email" type="email" required class="mt-1.5 w-full rounded-xl border border-white/[.08] bg-black/30 px-3 py-2.5 text-sm outline-none" /></label><label class="block text-[11px] text-zinc-500">Password<input v-model="form.password" type="password" required class="mt-1.5 w-full rounded-xl border border-white/[.08] bg-black/30 px-3 py-2.5 text-sm outline-none" /></label><label class="block text-[11px] text-zinc-500">Confirm<input v-model="form.password_confirmation" type="password" required class="mt-1.5 w-full rounded-xl border border-white/[.08] bg-black/30 px-3 py-2.5 text-sm outline-none" /></label></div>
        <div v-if="Object.keys(form.errors).length" class="mt-4 space-y-1 text-[11px] text-red-300"><div v-for="(error,key) in form.errors" :key="key">{{ error }}</div></div>
        <button :disabled="form.processing" class="mt-6 flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-white text-xs font-semibold text-black disabled:opacity-50">Create workspace <ArrowRight :size="14" /></button>
      </form>
      <p class="mt-5 text-center text-[11px] text-zinc-600">Already have an account? <Link :href="AuthController.login().url" class="text-zinc-300 hover:text-white">Sign in</Link></p>
    </div>
  </main>
</template>
