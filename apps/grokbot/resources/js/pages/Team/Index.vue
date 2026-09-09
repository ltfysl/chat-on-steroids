<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue';
import TeamController from '@/actions/App/Http/Controllers/TeamController';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Shield, UserRound } from '@lucide/vue';

type Member = { id:number; name:string; email:string; role:string };
const props = defineProps<{ members:Member[] }>();
const page = usePage();
const auth = page.props.auth as any;
const updateRole = (member:Member, role:string) => router.patch(TeamController.update({ user: member.id }).url, { role }, { preserveScroll:true });
</script>

<template>
  <Head title="Team" />
  <AppShell>
    <div class="mx-auto max-w-5xl"><div><p class="text-[11px] uppercase tracking-[.16em] text-violet-300/80">Access control</p><h1 class="mt-2 text-3xl font-semibold tracking-[-.04em]">Workspace team</h1><p class="mt-2 text-sm text-zinc-500">Roles control operational access; approval decisions require admin or owner privileges.</p></div>
      <div class="surface mt-8 overflow-hidden rounded-2xl"><div class="divide-y divide-white/[.055]"><div v-for="member in members" :key="member.id" class="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center"><div class="grid size-9 place-items-center rounded-xl border border-white/[.07] bg-white/[.035]"><UserRound :size="15" /></div><div class="min-w-0 flex-1"><div class="text-[13px] font-medium">{{ member.name }}</div><div class="mt-0.5 truncate text-[10px] text-zinc-600">{{ member.email }}</div></div><div class="flex items-center gap-2"><Shield :size="13" class="text-zinc-600" /><select :value="member.role" :disabled="auth.role !== 'owner' || member.role === 'owner'" class="rounded-xl border border-white/[.08] bg-black/30 px-3 py-2 text-[11px] text-zinc-300 disabled:opacity-50" @change="updateRole(member, ($event.target as HTMLSelectElement).value)"><option value="owner">Owner</option><option value="admin">Admin</option><option value="member">Member</option><option value="viewer">Viewer</option></select></div></div></div></div>
    </div>
  </AppShell>
</template>
