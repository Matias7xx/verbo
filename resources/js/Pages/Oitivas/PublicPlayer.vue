<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import Tag from 'primevue/tag';
import Divider from 'primevue/divider';
import Button from 'primevue/button';

// Props vindas do Controller
const props = defineProps({
    oitiva: Object,
    url_video: String,
    viewer_info: Object 
});

const preventContextMenu = (e) => {
    e.preventDefault();
};

// --- LÓGICA DE TELA CHEIA SEGURA ---
const playerContainer = ref(null);
const isFullscreen = ref(false);

const toggleFullscreen = async () => {
    if (!playerContainer.value) return;

    if (!document.fullscreenElement) {
        // Entrar em tela cheia no CONTAINER (inclui vídeo e marca d'água)
        try {
            await playerContainer.value.requestFullscreen();
        } catch (err) {
            console.error(`Erro ao ativar tela cheia: ${err.message}`);
        }
    } else {
        // Sair da tela cheia
        if (document.exitFullscreen) {
            document.exitFullscreen();
        }
    }
};

// Monitorar mudanças para atualizar o estado e garantir segurança
const updateFullscreenState = () => {
    isFullscreen.value = !!document.fullscreenElement;
    
    // SEGURANÇA: Se o usuário conseguiu colocar APENAS o vídeo em tela cheia 
    // (burlando o botão), forçamos a saída para garantir a marca d'água.
    if (document.fullscreenElement && document.fullscreenElement.tagName === 'VIDEO') {
        document.exitFullscreen();
    }
};

onMounted(() => {
    document.addEventListener('fullscreenchange', updateFullscreenState);
});

onUnmounted(() => {
    document.removeEventListener('fullscreenchange', updateFullscreenState);
});
// ------------------------------------
</script>

<template>
    <Head :title="`Assistindo Inquérito ${oitiva.numero_inquerito}`" />

    <div class="min-h-screen bg-gray-50 text-gray-900 p-4 md:p-8 flex flex-col items-center">

        <div class="w-full max-w-5xl flex justify-between items-center mb-6 bg-white p-4 rounded shadow-sm border border-gray-200">
            <div class="flex items-center gap-3">
                <img src="/images/brasao_pcpb.png" alt="Logo" class="h-12 w-auto object-contain" />
                <div>
                    <h1 class="text-xl font-bold text-gray-800">Sistema VERBO</h1>
                    <span class="text-xs text-gray-500 uppercase tracking-wider">Módulo de Visualização Segura</span>
                </div>
            </div>
            <div class="text-right hidden md:block">
                <div class="text-sm text-gray-500">Acesso monitorado de:</div>
                <div class="font-bold text-blue-800">{{ viewer_info.nome }}</div>
                <div class="text-xs text-gray-600 font-mono">Matrícula: {{ viewer_info.matricula }}</div>
            </div>
        </div>

        <div class="w-full max-w-5xl mb-8">
            
            <div 
                ref="playerContainer"
                class="bg-black rounded-lg shadow-lg overflow-hidden border border-gray-300 relative group flex flex-col justify-center"
            >
                
                <video
                    controls
                    controlsList="nodownload nofullscreen noremoteplayback"
                    disablePictureInPicture
                    @contextmenu="preventContextMenu"
                    class="w-full max-h-[70vh] mx-auto block object-contain relative z-10"
                    :class="{ 'h-screen max-h-screen': isFullscreen }"
                >
                    <source :src="url_video" type="video/mp4">
                    Seu navegador não suporta a reprodução deste vídeo.
                </video>

                <div 
                    class="absolute top-0 left-0 w-full z-20 pointer-events-none overflow-hidden select-none flex flex-wrap content-start justify-center gap-12 p-8"
                    :style="{ height: isFullscreen ? 'calc(100% - 90px)' : 'calc(100% - 80px)' }"
                >
                    <div 
                        v-for="i in 40" 
                        :key="i"
                        class="transform -rotate-12 font-mono font-bold text-lg whitespace-nowrap"
                        :style="{ 
                            color: 'rgba(0, 0, 0, 0.15)'
                        }"
                    >
                        {{ viewer_info.matricula }} • {{ viewer_info.nome }}
                    </div>
                </div>

                <div class="absolute bottom-20 right-6 z-30">
                    <Button 
                        @click="toggleFullscreen" 
                        rounded 
                        severity="secondary" 
                        :icon="isFullscreen ? 'pi pi-window-minimize' : 'pi pi-window-maximize'" 
                        :aria-label="isFullscreen ? 'Sair da Tela Cheia' : 'Tela Cheia'"
                        class="bg-white text-black hover:bg-gray-200 border-none shadow-lg w-12 h-12"
                    />
                </div>

            </div>
            
            <p class="text-xs text-gray-400 mt-2 text-center" v-if="!isFullscreen">
                <i class="pi pi-info-circle mr-1"></i>
                Utilize o botão flutuante <i class="pi pi-window-maximize mx-1"></i> no vídeo para tela cheia segura.
            </p>
        </div>

        <div class="w-full max-w-5xl grid grid-cols-1 md:grid-cols-5 gap-6">
            <div class="md:col-span-2 bg-white rounded p-6 shadow-sm border border-gray-200">
                <h2 class="text-xl font-bold mb-4 flex items-center gap-2 text-gray-800">
                    <i class="pi pi-file text-blue-600"></i>
                    Dados do Procedimento
                </h2>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs text-gray-500 uppercase font-semibold">Número do Inquérito</label>
                        <p class="font-mono text-lg text-gray-900">{{ oitiva.numero_inquerito }}</p>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 uppercase font-semibold">Tipo de Oitiva</label>
                        <p class="text-gray-900 capitalize">{{ oitiva.tipo_oitiva }}</p>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 uppercase font-semibold">Delegado Responsável</label>
                        <p class="text-gray-900">{{ oitiva.nome_delegado_responsavel }}</p>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 uppercase font-semibold">Data da Gravação</label>
                        <p class="text-gray-900">{{ new Date(oitiva.created_at).toLocaleDateString('pt-BR') }}</p>
                    </div>
                </div>

                <Divider class="bg-gray-200" />

                <div>
                    <label class="text-xs text-gray-500 uppercase font-semibold">Declarante</label>
                    <p class="text-xl font-bold text-gray-800">{{ oitiva.declarante.nome_completo }}</p>
                    <p class="text-sm text-gray-600" v-if="oitiva.declarante.cpf">CPF: {{ oitiva.declarante.cpf }}</p>
                </div>
            </div>

            <div class="bg-white rounded p-6 shadow-sm border border-gray-200 flex flex-col justify-between">
                <div>
                    <h3 class="font-bold text-gray-700 mb-3">Autenticidade</h3>
                    <div class="mb-4">
                        <Tag severity="success" value="Integridade Verificada" icon="pi pi-check-circle" class="w-full mb-2" />
                        <p class="text-xs text-gray-500 text-justify leading-snug">
                            Este vídeo possui assinatura digital e hash de integridade armazenado em banco de dados auditável.
                        </p>
                    </div>
                    
                    <div class="bg-gray-50 p-3 rounded border border-gray-200 overflow-hidden">
                        <label class="text-[10px] text-gray-500 block font-bold mb-1">HASH SHA-256 (REGISTRO)</label>
                        <p class="text-[10px] font-mono text-green-700 break-all leading-tight font-bold">
                            {{ oitiva.hash_arquivo_video }}
                        </p>
                    </div>
                </div>

                <div class="mt-6 p-3 bg-blue-50 border border-blue-200 rounded">
                    <p class="text-xs text-blue-800 flex items-start gap-2 leading-snug">
                        <i class="pi pi-info-circle mt-0.5"></i>
                        <span>Você está acessando este conteúdo sob a matrícula <strong>{{ viewer_info.matricula }}</strong>. O acesso está sendo registrado.</span>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Tenta esconder o botão nativo de fullscreen no Chrome/Edge para evitar confusão */
video::-webkit-media-controls-fullscreen-button {
    display: none !important;
}
</style>