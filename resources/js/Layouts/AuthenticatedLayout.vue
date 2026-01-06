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
            { label: 'Nova Oitiva', icon: 'pi pi-plus', command: () => router.get(route('oitivas.create')) }, // Rota futura
            { label: 'Pesquisar', icon: 'pi pi-search' },
            { separator: true },
            { label: 'Minhas Gravações', icon: 'pi pi-folder' }
        ]
    },
    {
        label: 'Administrativo',
        icon: 'pi pi-briefcase',
        visible: user.cargo === 'Delegado' || true, // Exemplo de permissão
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
        <Menubar :model="items" class="border-none shadow-sm rounded-none px-6 py-3">
            <template #start>
                <div class="flex items-center gap-2 mr-4">
                    <i class="pi pi-shield text-2xl text-blue-900"></i>
                    <span class="font-bold text-xl text-blue-900 tracking-tight">VERBO</span>
                </div>
            </template>

            <template #end>
                <div class="flex items-center gap-3">
                    <span class="text-sm text-gray-600 hidden md:block">
                        Olá, <strong>{{ user.name }}</strong>
                    </span>
                    <Avatar 
                        label="P" 
                        class="cursor-pointer bg-blue-100 text-blue-800" 
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

<style>
/* Ajustes finos globais se necessário */
.p-menubar {
    background-color: #ffffff !important;
}
</style>