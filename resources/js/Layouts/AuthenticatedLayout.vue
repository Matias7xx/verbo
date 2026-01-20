<script setup>
import { ref } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import Menubar from 'primevue/menubar';
import Button from 'primevue/button';
import Avatar from 'primevue/avatar';
import Badge from 'primevue/badge';
import Menu from 'primevue/menu';

const user = usePage().props.auth.user;
const menuUser = ref();

// Itens do Menu Principal
const items = ref([
    {
        label: 'Painel',
        icon: 'pi pi-home',
        command: () => router.get(route('dashboard'))
    },
    {
        label: 'Oitivas',
        icon: 'pi pi-video',
        items: [
            { label: 'Nova Oitiva', icon: 'pi pi-plus', command: () => router.get(route('oitivas.create')) },
            { label: 'Pesquisar', icon: 'pi pi-search' },
            { separator: true },
            { label: 'Minhas Gravações', icon: 'pi pi-folder' }
        ]
    },
    {
        label: 'Administrativo',
        icon: 'pi pi-briefcase',
        visible: user.cargo === 'Delegado' || true,
        items: [
            { label: 'Unidades', icon: 'pi pi-building' },
            { label: 'Usuários', icon: 'pi pi-users' }
        ]
    }
]);

// Menu do Usuário (Avatar)
const userMenuItems = ref([
    { label: 'Perfil', icon: 'pi pi-user', command: () => router.get(route('profile.edit')) },
    { separator: true },
    { label: 'Sair', icon: 'pi pi-power-off', command: () => router.post(route('logout')) }
]);

const toggleUserMenu = (event) => {
    menuUser.value.toggle(event);
};
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <Menubar :model="items" class="border-none px-6 py-2.5 shadow-md">
            <template #start>
                <div class="flex items-center gap-2 mr-4">
                    <img src="/images/logo-pc-branca.png" alt="Logo" class="h-12 w-auto object-contain pr-4 border-r border-neutral-500" />
                    <span class="font-bold text-xl text-white tracking-tight">VERBO</span>
                </div>
            </template>

            <template #end>
                <div class="flex items-center gap-3">
                    <span class="text-sm text-white hidden md:block font-medium">
                        {{ user.name }}
                    </span>
                    <Avatar
                        label="P"
                        class="cursor-pointer bg-neutral-700 text-white hover:bg-neutral-600 transition-all"
                        shape="circle"
                        @click="toggleUserMenu"
                    />
                    <Menu ref="menuUser" :model="userMenuItems" :popup="true" />
                </div>
            </template>
        </Menubar>

        <main class="p-6 max-w-7xl mx-auto">
            <slot />
        </main>
    </div>
</template>
