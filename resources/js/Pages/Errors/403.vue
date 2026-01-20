<script setup>
import { Head } from '@inertiajs/vue3';
import Button from 'primevue/button';

defineProps({
    message: String
});
</script>

<template>
    <Head title="403 - Acesso Negado" />

    <div class="min-h-screen flex items-center justify-center bg-gray-50 p-4">
        <div class="w-full max-w-2xl">

            <!-- Navbar -->
            <div class="bg-black p-4 shadow-md mb-3 flex items-center justify-center border-b border-neutral-800">
                <div class="flex items-center gap-3">
                    <img src="/images/brasao_pcpb.png" alt="Logo" class="h-10 w-auto object-contain" />
                    <div>
                        <h1 class="text-lg font-bold text-white">Sistema VERBO</h1>
                        <span class="text-xs text-neutral-400 uppercase tracking-wider">Polícia Civil da Paraíba</span>
                    </div>
                </div>
            </div>

            <!-- Container principal -->
            <div class="bg-white shadow-sm border border-gray-200 p-8 md:p-12 text-center" style="border-radius: 8px;">

                <!-- Ícone de erro -->
                <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-gray-100 mb-6">
                    <i class="pi pi-lock text-6xl text-red-600"></i>
                </div>

                <!-- Código e título -->
                <div class="mb-6">
                    <h2 class="text-6xl font-bold text-gray-300 mb-2">403</h2>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Acesso Negado</h3>
                    <p class="text-gray-600">Link inválido ou expirado</p>
                </div>

                <!-- Mensagem principal -->
                <div class="max-w-lg mx-auto mb-8">
                    <p class="text-gray-700 leading-relaxed mb-4">
                        A assinatura digital de segurança para esta sala de oitiva não é mais válida.
                    </p>

                    <!-- Box de aviso -->
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 text-left mb-4">
                        <div class="flex items-start gap-3">
                            <i class="pi pi-exclamation-triangle text-yellow-600 mt-0.5"></i>
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-yellow-900 mb-1">Motivo Comum</p>
                                <p class="text-sm text-yellow-800">
                                    O link tem validade de <strong>4 horas</strong> e o prazo foi excedido.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Instrução -->
                    <div class="bg-blue-50 border border-blue-200 p-4 text-left" style="border-radius: 6px;">
                        <div class="flex items-start gap-3">
                            <i class="pi pi-info-circle text-blue-600 mt-0.5"></i>
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-blue-900 mb-1">Como Resolver</p>
                                <p class="text-sm text-blue-800">
                                    Solicite um <strong>novo link de acesso</strong> através do Sistema de Procedimentos Policiais (SPP).
                                </p>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- Rodapé com informação de suporte -->
                <div class="mt-8 pt-6 border-t border-gray-200">
                    <p class="text-xs text-gray-500">
                        Em caso de dúvidas, solicite atendimento à Diretoria de Tecnologia da Informação <a href="https://helpdesk.apps.pc.pb.gov.br/login" class="text-blue-500 underline">por meio de um chamado.</a>
                    </p>
                </div>
            </div>

        </div>
    </div>
</template>

<style scoped>
/* Animação ícone */
@keyframes pulse-slow {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.pi-lock {
    animation: pulse-slow 3s ease-in-out infinite;
}

/* Transição nos botões */
:deep(.p-button) {
    transition: all 0.2s ease;
}

:deep(.p-button:hover:not(:disabled)) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}
</style>
