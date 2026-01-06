<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage, Link } from '@inertiajs/vue3'; // Adicionado Link
import { computed } from 'vue';

// Componentes PrimeVue
import InputText from 'primevue/inputtext';
import InputMask from 'primevue/inputmask';
import Dropdown from 'primevue/dropdown';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Message from 'primevue/message';

// Props vindas do Controller (OitivaController@create)
const props = defineProps({
    delegados: {
        type: Array,
        default: () => []
    }
});

// Acesso ao usuário logado para lógica de permissão
const page = usePage();
const user = computed(() => page.props.auth.user);

// Verifica se o usuário logado NÃO é delegado (se for agente/escrivão, precisa selecionar o delegado)
const precisaSelecionarDelegado = computed(() => {
    return user.value.cargo !== 'Delegado';
});

// Listas estáticas para os Selects
const vinculos = [
    'Pai/Mãe', 'Avô/Avó', 'Tio/Tia', 'Conselheiro Tutelar', 'Advogado', 'Curador', 'Outro'
];

const tiposOitiva = [
    'declaracao', 'interrogatorio', 'depoimento', 'reconhecimento', 'acareacao'
];

// O Formulário (Inertia)
const form = useForm({
    // Dados do Procedimento
    numero_inquerito: '',
    tipo_oitiva: null, // Campo obrigatório adicionado
    delegado_id: null,
    
    // Dados do Declarante
    declarante_nome: '',
    declarante_cpf: '',
    
    // Dados do Representante
    representante_nome: '',
    representante_cpf: '',
    vinculo: null,
});

const submit = () => {
    form.post(route('oitivas.store'), {
        onSuccess: () => {
            // O redirecionamento acontece no controller para a rota 'oitivas.show'
        }
    });
};
</script>

<template>
    <Head title="Nova Vídeo Oitiva" />

    <AuthenticatedLayout>
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Nova Vídeo Oitiva</h1>
                <p class="text-sm text-gray-500">Sistema VERBO</p>
            </div>
            <nav class="text-sm">
                <ol class="flex text-gray-500 space-x-2">
                    <li><Link :href="route('dashboard')" class="hover:text-blue-600">Home</Link></li>
                    <li>/</li>
                    <li class="text-gray-800 font-semibold">Nova Oitiva</li>
                </ol>
            </nav>
        </div>

        <div class="max-w-5xl mx-auto">
            <form @submit.prevent="submit">
                <Card>
                    <template #title>
                        <div class="flex items-center gap-2 border-b pb-2 mb-4">
                            <i class="pi pi-file-edit text-blue-600"></i>
                            <span>Abertura de Sala de Oitiva</span>
                        </div>
                    </template>

                    <template #content>
                        <Message v-if="form.hasErrors" severity="error" class="mb-4">
                            Por favor, verifique os campos obrigatórios.
                        </Message>

                        <h4 class="text-gray-700 font-semibold mb-3">1. Dados do Procedimento</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            
                            <div class="flex flex-col gap-2">
                                <label for="inquerito" class="font-medium text-gray-700">Número do Inquérito *</label>
                                <InputText 
                                    id="inquerito" 
                                    v-model="form.numero_inquerito" 
                                    placeholder="Ex: 001.2025.000123" 
                                    :invalid="!!form.errors.numero_inquerito"
                                />
                                <small class="text-red-500" v-if="form.errors.numero_inquerito">{{ form.errors.numero_inquerito }}</small>
                            </div>

                            <div class="flex flex-col gap-2">
                                <label for="tipo_oitiva" class="font-medium text-gray-700">Tipo de Procedimento *</label>
                                <Dropdown 
                                    id="tipo_oitiva" 
                                    v-model="form.tipo_oitiva" 
                                    :options="tiposOitiva" 
                                    placeholder="Selecione" 
                                    class="w-full"
                                    :invalid="!!form.errors.tipo_oitiva"
                                />
                                <small class="text-red-500" v-if="form.errors.tipo_oitiva">{{ form.errors.tipo_oitiva }}</small>
                            </div>

                            <div v-if="precisaSelecionarDelegado" class="flex flex-col gap-2">
                                <label for="delegado" class="font-medium text-gray-700">Delegado Responsável *</label>
                                <Dropdown 
                                    id="delegado" 
                                    v-model="form.delegado_id" 
                                    :options="delegados" 
                                    optionLabel="name" 
                                    optionValue="id" 
                                    placeholder="Pesquisar Delegado"
                                    filter 
                                    class="w-full"
                                    :invalid="!!form.errors.delegado_id"
                                />
                                <small class="text-red-500" v-if="form.errors.delegado_id">{{ form.errors.delegado_id }}</small>
                            </div>
                        </div>

                        <h4 class="text-gray-700 font-semibold mb-3 border-t pt-4">2. Dados do Declarante (Ouvido)</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div class="flex flex-col gap-2">
                                <label for="declarante" class="font-medium text-gray-700">Nome Completo *</label>
                                <InputText id="declarante" v-model="form.declarante_nome" :invalid="!!form.errors.declarante_nome" />
                                <small class="text-red-500" v-if="form.errors.declarante_nome">{{ form.errors.declarante_nome }}</small>
                            </div>

                            <div class="flex flex-col gap-2">
                                <label for="cpf" class="font-medium text-gray-700">CPF</label>
                                <InputMask 
                                    id="cpf" 
                                    v-model="form.declarante_cpf" 
                                    mask="999.999.999-99" 
                                    placeholder="000.000.000-00"
                                    :invalid="!!form.errors.declarante_cpf"
                                />
                                <small class="text-red-500" v-if="form.errors.declarante_cpf">{{ form.errors.declarante_cpf }}</small>
                            </div>
                        </div>

                        <h4 class="text-gray-700 font-semibold mb-3 border-t pt-4">3. Dados do Representante/Acompanhante (Opcional)</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div class="flex flex-col gap-2">
                                <label for="rep_nome" class="font-medium text-gray-700">Nome</label>
                                <InputText id="rep_nome" v-model="form.representante_nome" />
                            </div>

                            <div class="flex flex-col gap-2">
                                <label for="rep_cpf" class="font-medium text-gray-700">CPF</label>
                                <InputMask id="rep_cpf" v-model="form.representante_cpf" mask="999.999.999-99" />
                            </div>

                            <div class="flex flex-col gap-2">
                                <label for="vinculo" class="font-medium text-gray-700">Vínculo</label>
                                <Dropdown 
                                    id="vinculo" 
                                    v-model="form.vinculo" 
                                    :options="vinculos" 
                                    placeholder="Selecione" 
                                    class="w-full" 
                                />
                            </div>
                        </div>

                    </template>

                    <template #footer>
                        <div class="flex justify-end gap-2">
                            <Button label="Cancelar" severity="secondary" outlined @click="$inertia.visit(route('dashboard'))" />
                            <Button label="Criar Sala e Avançar" icon="pi pi-arrow-right" iconPos="right" type="submit" :loading="form.processing" severity="success" />
                        </div>
                    </template>
                </Card>
            </form>
        </div>
    </AuthenticatedLayout>
</template>