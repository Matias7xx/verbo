<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import Card from 'primevue/card';
import Button from 'primevue/button';

// Dados mockados para exemplo visual
const estatisticas = [
    { titulo: 'Oitivas Realizadas', valor: '12', icon: 'pi pi-video', color: 'text-blue-500', bg: 'bg-blue-100' },
    { titulo: 'Em Andamento', valor: '1', icon: 'pi pi-spin pi-spinner', color: 'text-orange-500', bg: 'bg-orange-100' },
    { titulo: 'Arquivadas', valor: '340', icon: 'pi pi-file', color: 'text-gray-500', bg: 'bg-gray-100' },
];

// Função auxiliar para navegação
const irParaNovaOitiva = () => {
    router.get(route('oitivas.create'));
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div v-for="stat in estatisticas" :key="stat.titulo" class="surface-card p-4 shadow-sm border-round rounded-lg bg-white flex justify-between items-center border border-gray-200">
                <div>
                    <span class="block text-500 font-medium mb-1 text-gray-500">{{ stat.titulo }}</span>
                    <div class="text-900 font-medium text-2xl text-gray-800">{{ stat.valor }}</div>
                </div>
                <div class="flex items-center justify-center w-10 h-10 rounded-full" :class="stat.bg">
                    <i :class="[stat.icon, stat.color, 'text-xl']"></i>
                </div>
            </div>
        </div>

        <Card>
            <template #title>
                <div class="flex items-center gap-2">
                    <i class="pi pi-bolt text-yellow-500"></i>
                    <span>Atalhos Rápidos</span>
                </div>
            </template>
            <template #content>
                <div class="flex flex-wrap gap-4">
                    <Button 
                        label="Iniciar Nova Oitiva" 
                        icon="pi pi-camera" 
                        severity="danger" 
                        @click="irParaNovaOitiva"
                    />
                    
                    <Button 
                        label="Pesquisar Inquérito" 
                        icon="pi pi-search" 
                        severity="secondary" 
                        outlined 
                    />

                    <Button 
                        label="Minhas Gravações" 
                        icon="pi pi-list" 
                        severity="info" 
                        outlined 
                        @click="router.get(route('oitivas.index'))"
                    />
                </div>
            </template>
        </Card>
    </AuthenticatedLayout>
</template>