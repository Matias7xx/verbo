<script setup>
import { Head } from '@inertiajs/vue3';
import Card from 'primevue/card';
import Button from 'primevue/button';
import Message from 'primevue/message';

defineProps({
    message: String
});
</script>

<template>
    <Head title="Acesso Negado - VERBO" />

    <div class="min-h-screen flex items-center justify-center bg-gray-100 p-4">
        <Card style="width: 30rem; text-align: center" class="shadow-lg border-t-4 border-red-500">
            <template #header>
                <div class="pt-6 flex justify-center">
                    <img
                        src="/images/brasao_pcpb.png"
                        alt="Brasão PCPB"
                        class="h-24 w-auto object-contain mb-4"
                    />
                </div>
            </template>

            <template #title>
                <h1 class="text-2xl font-bold text-gray-800">SISTEMA VERBO</h1>
                <h1 class="text-2xl font-bold text-gray-800">Link Inválido ou Expirado</h1>
            </template>

            <template #content>
                <div class="flex flex-col gap-4 text-gray-600 my-4">
                    <p>
                        A assinatura digital de segurança para esta sala de oitiva não é mais válida.
                    </p>
                    
                    <Message severity="warn" :closable="false">
                        Motivo comum: O link tem validade de 4 horas e o prazo excedeu.
                    </Message>

                    <p class="text-sm">
                        Não se preocupe. Solicite no <strong>SPP um novo link</strong> para acesso a esta oitiva.
                    </p>
                </div>
            </template>

            <template #footer>
                <div class="flex justify-center">
                    <Button 
                        label="Fechar Janela" 
                        icon="pi pi-times" 
                        severity="secondary" 
                        outlined 
                        @click="() => { window.close() }" 
                    />
                </div>
            </template>
        </Card>
    </div>
</template>