<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue';
import MessageController from '@/actions/App/Http/Controllers/MessageController';
import { Head, useForm } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { Bot, CircleStop, CornerDownLeft, LoaderCircle, TerminalSquare } from '@lucide/vue';
import { ref } from 'vue';

type BotRow = { public_id:string; name:string; model:string; state:string; runtime?:{state:string;external_id:string}|null };
type MessageRow = { public_id:string; sender_user_id?:number|null; sender_bot_id?:number|null; role:string; status:string; sequence:number; content:string; metadata?:any; created_at:string };
const props = defineProps<{ conversation:{public_id:string;title:string;state:string}; bots:BotRow[]; messages:MessageRow[] }>();
const messages = ref(props.messages.map((message) => ({ ...message })));
const form = useForm({ content: '', bot_id: props.bots[0]?.public_id ?? null });

useEcho(`conversation.${props.conversation.public_id}`, '.message.chunk', (event:any) => {
  const target = messages.value.find((message:any) => message.public_id === event.message_public_id || (message as any).id === event.message_id);
  if (target) { target.content += event.delta; target.status = 'streaming'; }
});

const submit = () => {
  const content = form.content.trim();
  if (!content || form.processing) return;
  form.content = content;
  form.submit(MessageController.store({ conversation: props.conversation.public_id }), {
    preserveScroll: true,
    onSuccess: () => { form.content = ''; },
  });
};
</script>

<template>
  <Head :title="conversation.title" />
  <AppShell>
    <div class="mx-auto flex h-[calc(100vh-7.5rem)] max-w-5xl flex-col overflow-hidden rounded-2xl border border-white/[.07] bg-[#0b0d12]/80 shadow-2xl">
      <header class="flex h-16 shrink-0 items-center border-b border-white/[.06] px-5"><div class="grid size-8 place-items-center rounded-xl border border-white/[.07] bg-white/[.035]"><Bot :size="15" /></div><div class="ml-3"><h1 class="text-sm font-medium">{{ conversation.title }}</h1><p class="mt-0.5 text-[10px] text-zinc-600">{{ bots.map(bot => bot.name).join(', ') }} · persistent thread</p></div><div class="ml-auto flex items-center gap-1.5 text-[9px] text-emerald-400"><span class="size-1.5 rounded-full bg-emerald-400" />{{ bots[0]?.runtime?.state ?? 'offline' }}</div></header>
      <section class="flex-1 space-y-7 overflow-y-auto px-5 py-6 md:px-10">
        <div v-for="message in messages" :key="message.public_id" :class="['flex gap-3', message.role === 'user' ? 'justify-end' : 'justify-start']">
          <div v-if="message.role !== 'user'" class="mt-1 grid size-7 shrink-0 place-items-center rounded-lg border border-violet-400/10 bg-violet-500/[.07]"><Bot :size="13" /></div>
          <div :class="['max-w-[78%]', message.role === 'user' ? 'rounded-2xl rounded-br-md bg-white px-4 py-2.5 text-black' : 'pt-1']"><div class="whitespace-pre-wrap text-[13px] leading-6">{{ message.content }}<span v-if="message.status === 'streaming'" class="ml-1 inline-block h-4 w-[2px] animate-pulse bg-violet-400 align-middle" /></div><div v-if="message.status === 'failed'" class="mt-2 text-[10px] text-red-300">{{ message.metadata?.error ?? 'Runtime failed.' }}</div></div>
        </div>
        <div v-if="!messages.length" class="grid h-full place-items-center text-center"><div><div class="mx-auto grid size-12 place-items-center rounded-2xl border border-white/[.07] bg-white/[.03]"><TerminalSquare :size="20" class="text-zinc-500" /></div><h2 class="mt-4 text-sm font-medium">Start a persistent thread</h2><p class="mt-1 text-xs text-zinc-600">Messages and streamed chunks survive refreshes and reconnects.</p></div></div>
      </section>
      <form class="shrink-0 border-t border-white/[.06] bg-black/20 p-4 md:px-6" @submit.prevent="submit"><div class="rounded-2xl border border-white/[.08] bg-white/[.035] p-2 shadow-inner"><textarea v-model="form.content" rows="2" class="max-h-40 min-h-14 w-full resize-none bg-transparent px-2 py-1 text-[13px] leading-5 outline-none placeholder:text-zinc-700" placeholder="Message your bot…" @keydown.meta.enter.prevent="submit" @keydown.ctrl.enter.prevent="submit" /><div class="flex items-center gap-2 px-1 pb-1"><select v-model="form.bot_id" class="max-w-48 rounded-lg border border-white/[.07] bg-black/40 px-2 py-1 text-[10px] text-zinc-400"><option v-for="bot in bots" :key="bot.public_id" :value="bot.public_id">{{ bot.name }} · {{ bot.model }}</option></select><span class="ml-auto text-[9px] text-zinc-700">⌘ Enter</span><button :disabled="form.processing || !form.content.trim()" class="grid size-8 place-items-center rounded-xl bg-white text-black disabled:opacity-30"><LoaderCircle v-if="form.processing" :size="14" class="animate-spin" /><CornerDownLeft v-else :size="14" /></button></div></div></form>
    </div>
  </AppShell>
</template>
