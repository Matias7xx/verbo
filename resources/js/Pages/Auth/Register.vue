<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Dropdown from 'primevue/dropdown';
import Message from 'primevue/message';

const form = useForm({
    name: '',
    matricula: '', // Novo Campo
    cargo: '',     // Novo Campo
    email: '',
    password: '',
    password_confirmation: '',
});

// Lista de cargos para padronização
const cargosDisponiveis = [
    'Delegado',
    'Escrivão',
    'Agente de Investigação',
    'Perito',
    'Administrativo'
];

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-slate-900 p-4">
        <Head title="Registro - PCPB" />

        <Card style="width: 30rem; overflow: hidden">
            <template #header>
                <div class="flex justify-center pt-6 pb-2">
                    <div class="text-center">
                        <i class="pi pi-user-plus text-4xl text-blue-800"></i>
                        <h2 class="text-2xl font-bold text-gray-800 mt-2">Novo Acesso</h2>
                        <span class="text-sm text-gray-500">Cadastro de Policial/Servidor</span>
                    </div>
                </div>
            </template>

            <template #content>
                <form @submit.prevent="submit" class="flex flex-col gap-4">
                    
                    <div class="flex flex-col gap-2">
                        <label for="name" class="font-semibold text-gray-700">Nome Completo</label>
                        <InputText id="name" v-model="form.name" class="w-full" :invalid="!!form.errors.name" autocomplete="name" autofocus />
                        <small class="text-red-500" v-if="form.errors.name">{{ form.errors.name }}</small>
                    </div>

                    <div class="flex gap-4">
                        <div class="flex flex-col gap-2 w-1/2">
                            <label for="matricula" class="font-semibold text-gray-700">Matrícula</label>
                            <InputText id="matricula" v-model="form.matricula" class="w-full" :invalid="!!form.errors.matricula" />
                            <small class="text-red-500" v-if="form.errors.matricula">{{ form.errors.matricula }}</small>
                        </div>

                        <div class="flex flex-col gap-2 w-1/2">
                            <label for="cargo" class="font-semibold text-gray-700">Cargo</label>
                            <Dropdown 
                                id="cargo" 
                                v-model="form.cargo" 
                                :options="cargosDisponiveis" 
                                placeholder="Selecione" 
                                class="w-full" 
                                :invalid="!!form.errors.cargo"
                            />
                            <small class="text-red-500" v-if="form.errors.cargo">{{ form.errors.cargo }}</small>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label for="email" class="font-semibold text-gray-700">Email Institucional</label>
                        <InputText id="email" type="email" v-model="form.email" class="w-full" :invalid="!!form.errors.email" autocomplete="username" />
                        <small class="text-red-500" v-if="form.errors.email">{{ form.errors.email }}</small>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label for="password" class="font-semibold text-gray-700">Senha</label>
                        <Password 
                            id="password" 
                            v-model="form.password" 
                            toggleMask 
                            class="w-full" 
                            inputClass="w-full"
                            :invalid="!!form.errors.password" 
                            autocomplete="new-password"
                            promptLabel="Escolha uma senha forte"
                            weakLabel="Fraca"
                            mediumLabel="Média"
                            strongLabel="Forte"
                        />
                        <small class="text-red-500" v-if="form.errors.password">{{ form.errors.password }}</small>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label for="password_confirmation" class="font-semibold text-gray-700">Confirmar Senha</label>
                        <Password 
                            id="password_confirmation" 
                            v-model="form.password_confirmation" 
                            :feedback="false" 
                            toggleMask 
                            class="w-full" 
                            inputClass="w-full"
                            :invalid="!!form.errors.password_confirmation" 
                            autocomplete="new-password" 
                        />
                        <small class="text-red-500" v-if="form.errors.password_confirmation">{{ form.errors.password_confirmation }}</small>
                    </div>

                    <Button type="submit" label="Registrar" icon="pi pi-check" :loading="form.processing" severity="info" class="w-full mt-2" />
                </form>
            </template>

            <template #footer>
                <div class="flex justify-center mt-2">
                    <Link
                        :href="route('login')"
                        class="text-sm text-gray-600 hover:text-blue-800 transition-colors"
                    >
                        Já possui cadastro? Clique aqui para entrar.
                    </Link>
                </div>
            </template>
        </Card>
    </div>
</template>

<style scoped>
/* Garante que o input do Password ocupe 100% da largura */
:deep(.p-password input) {
    width: 100%;
}
</style>