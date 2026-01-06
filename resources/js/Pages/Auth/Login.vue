<script setup>
import { useForm, Head } from '@inertiajs/vue3';
import Checkbox from 'primevue/checkbox';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Card from 'primevue/card';
import Message from 'primevue/message';

defineProps({
    canResetPassword: { type: Boolean, },
    status: { type: String, },
});

const form = useForm({
    email: '', // Pode ser alterado para 'matricula' futuramente
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-slate-900 p-4">
        <Head title="Login - PCPB" />

        <Card style="width: 25rem; overflow: hidden">
            <template #header>
                <div class="flex justify-center pt-6 pb-2">
                    <div class="text-center">
                        <i class="pi pi-shield text-4xl text-blue-800"></i>
                        <h2 class="text-2xl font-bold text-gray-800 mt-2">VERBO</h2>
                        <span class="text-sm text-gray-500">Sistema Verbo de Vídeo-Oitivas Policiais</span>
                    </div>
                </div>
            </template>

            <template #content>
                <form @submit.prevent="submit" class="flex flex-col gap-4">
                    
                    <div v-if="status" class="mb-4 font-medium text-sm text-green-600">
                        {{ status }}
                    </div>

                    <div class="flex flex-col gap-2">
                        <label for="email" class="font-semibold text-gray-700">Email / Matrícula</label>
                        <InputText id="email" v-model="form.email" class="w-full" :invalid="!!form.errors.email" autocomplete="username" />
                        <small class="text-red-500" v-if="form.errors.email">{{ form.errors.email }}</small>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label for="password" class="font-semibold text-gray-700">Senha</label>
                        <Password id="password" v-model="form.password" :feedback="false" toggleMask class="w-full" :invalid="!!form.errors.password" inputClass="w-full" autocomplete="current-password" />
                        <small class="text-red-500" v-if="form.errors.password">{{ form.errors.password }}</small>
                    </div>

                    <div class="flex align-items-center gap-2">
                        <Checkbox v-model="form.remember" binary inputId="remember" />
                        <label for="remember" class="text-sm text-gray-600">Lembrar-me</label>
                    </div>

                    <Button type="submit" label="Entrar no Sistema" icon="pi pi-sign-in" :loading="form.processing" severity="info" class="w-full mt-2" />
                </form>
            </template>
            
            <template #footer>
                <div class="text-center text-xs text-gray-400 mt-2">
                    Polícia Civil da Paraíba &copy; {{ new Date().getFullYear() }}
                </div>
            </template>
        </Card>
    </div>
</template>