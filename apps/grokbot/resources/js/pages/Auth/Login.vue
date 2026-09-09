<script setup lang="ts">
import AuthController from '@/actions/App/Http/Controllers/AuthController';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowRight, LockKeyhole, Sparkles } from '@lucide/vue';

const form = useForm({ email: '', password: '', remember: false });
</script>

<template>
  <Head title="Sign in" />
  <main class="grid min-h-screen place-items-center px-5 py-10">
    <div class="w-full max-w-[420px]">
      <div class="mb-8 flex items-center gap-3"><div class="grid size-10 place-items-center rounded-2xl border border-violet-400/20 bg-violet-500/10"><Sparkles :size="18" /></div><div><div class="text-sm font-semibold">Grokbot</div><div class="text-[10px] text-zinc-600">Persistent AI operations</div></div></div>
      <form class="surface rounded-[24px] p-6 md:p-7" @submit.prevent="form.submit(AuthController.authenticate())">
        <div class="grid size-9 place-items-center rounded-xl border border-white/[.07] bg-white/[.03]"><LockKeyhole :size="16" /></div><h1 class="mt-5 text-2xl font-semibold tracking-[-.04em]">Welcome back.</h1><p class="mt-2 text-xs leading-5 text-zinc-500">Sign in to your workspace and resume your bot fleet.</p>
        <div class="mt-7 space-y-4"><label class="block text-[11px] text-zinc-500">Email<input v-model="form.email" type="email" autocomplete="email" required autofocus class="mt-1.5 w-full rounded-xl border border-white/[.08] bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-violet-400/35" /></label><label class="block text-[11px] text-zinc-500">Password<input v-model="form.password" type="password" autocomplete="current-password" required class="mt-1.5 w-full rounded-xl border border-white/[.08] bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-violet-400/35" /></label><label class="flex items-center gap-2 text-[11px] text-zinc-500"><input v-model="form.remember" type="checkbox" />Keep this device signed in</label></div>
        <div v-if="form.errors.email" class="mt-3 text-[11px] text-red-300">{{ form.errors.email }}</div>
        <button :disabled="form.processing" class="mt-6 flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-white text-xs font-semibold text-black disabled:opacity-50">Sign in <ArrowRight :size="14" /></button>
      </form>
      <p class="mt-5 text-center text-[11px] text-zinc-600">New workspace? <Link :href="AuthController.register().url" class="text-zinc-300 hover:text-white">Create account</Link></p>
    </div>
  </main>
</template>
