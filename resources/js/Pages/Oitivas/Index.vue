<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Card from 'primevue/card';

defineProps({
    oitivas: Object // Objeto de paginação do Laravel
});

const getStatusSeverity = (oitiva) => {
    return oitiva.caminho_arquivo_video ? 'success' : 'warn';
};

const getStatusLabel = (oitiva) => {
    return oitiva.caminho_arquivo_video ? 'Concluída' : 'Pendente';
};

// Formata data (Ex: 31/12/2025 14:30)
const formatDate = (dateString) => {
    return new Date(dateString).toLocaleString('pt-BR');
};

const abrirSala = (id) => {
    router.get(route('oitivas.show', id));
};
</script>

<template>
    <Head title="Minhas Gravações" />

    <AuthenticatedLayout>
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Minhas Gravações</h1>
            <Button label="Nova Oitiva" icon="pi pi-plus" @click="router.get(route('oitivas.create'))" />
        </div>

        <Card>
            <template #content>
                <DataTable :value="oitivas.data" tableStyle="min-width: 50rem" stripedRows>
                    <template #empty>Nenhuma oitiva encontrada.</template>
                    
                    <Column field="numero_inquerito" header="Inquérito"></Column>
                    
                    <Column field="declarante.nome_completo" header="Declarante"></Column>
                    
                    <Column field="tipo_oitiva" header="Tipo">
                        <template #body="slotProps">
                            <span class="uppercase text-sm">{{ slotProps.data.tipo_oitiva }}</span>
                        </template>
                    </Column>
                    
                    <Column header="Data">
                        <template #body="slotProps">
                            {{ formatDate(slotProps.data.created_at) }}
                        </template>
                    </Column>

                    <Column header="Status">
                        <template #body="slotProps">
                            <Tag :value="getStatusLabel(slotProps.data)" :severity="getStatusSeverity(slotProps.data)" />
                        </template>
                    </Column>

                    <Column header="Ações">
                        <template #body="slotProps">
                            <Button 
                                icon="pi pi-external-link" 
                                label="Abrir Sala" 
                                size="small" 
                                :severity="slotProps.data.caminho_arquivo_video ? 'secondary' : 'primary'"
                                @click="abrirSala(slotProps.data.id)" 
                            />
                        </template>
                    </Column>
                </DataTable>

                <div class="mt-4 flex justify-center gap-2" v-if="oitivas.links.length > 3">
                    <Link 
                        v-for="(link, key) in oitivas.links" 
                        :key="key" 
                        :href="link.url ?? '#'" 
                        class="px-3 py-1 border rounded"
                        :class="{ 'bg-blue-600 text-white': link.active, 'text-gray-500': !link.url }"
                        v-html="link.label" 
                    />
                </div>
            </template>
        </Card>
    </AuthenticatedLayout>
</template>