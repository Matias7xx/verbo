<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import Tag from 'primevue/tag';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import { useTranscription } from '@/composables/useTranscription';
import axios from 'axios';

// Props vindas do Controller
const props = defineProps({
    oitiva: Object,
    url_video: String,
    viewer_info: Object,
    transcription_url: String
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
        try {
            await playerContainer.value.requestFullscreen();
        } catch (err) {
            console.error(`Erro ao ativar tela cheia: ${err.message}`);
        }
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        }
    }
};

const updateFullscreenState = () => {
    isFullscreen.value = !!document.fullscreenElement;
    if (document.fullscreenElement && document.fullscreenElement.tagName === 'VIDEO') {
        document.exitFullscreen();
    }
};

// --- LÓGICA DE TRANSCRIÇÃO ---
const videoElement = ref(null);
const transcriptionContainer = ref(null);

const {
    transcriptionItems,
    currentHighlightIndex,
    isLoading: isLoadingTranscription,
    hasError: transcriptionError,
    userIsScrolling,
    seekToTime,
    scrollToHighlighted,
    downloadSRT,
    handleUserScroll
} = useTranscription(videoElement, props.transcription_url);

// Watch para fazer scroll automático quando o highlight mudar
watch(currentHighlightIndex, () => {
    if (transcriptionContainer.value && !userIsScrolling.value) {
        scrollToHighlighted(transcriptionContainer.value);
    }
});

const handleTranscriptionClick = (item) => {
    seekToTime(item.start);
};

const handleDownloadTranscription = async () => {
    const filename = `transcricao_${props.oitiva.numero_inquerito}_${props.oitiva.declarante.nome_completo}`.replace(/ /g, '_');
    const success = await downloadSRT(filename);

    if (!success) {
        alert('Não foi possível baixar a transcrição.');
    }
};

// --- LÓGICA DE DOWNLOAD DO VÍDEO ---
const downloadStatus = ref('pending');
const isDownloading = ref(false);
const pollingInterval = ref(null);
const pollingStartTime = ref(null);
const MAX_POLLING_TIME = 5 * 60 * 1000; // 5 minutos

const iniciarDownload = async () => {
    try {
        isDownloading.value = true;
        downloadStatus.value = 'processing';

        const response = await axios.post(
            route('public.oitiva.iniciar-download', {
                oitiva: props.oitiva.id
            })
        );

        if (response.data.status === 'processing') {
            pollingStartTime.value = Date.now();
            startPolling();
        } else if (response.data.status === 'completed') {
            downloadStatus.value = 'completed';
            isDownloading.value = false;
        }
    } catch (error) {
        console.error('Erro ao iniciar download:', error);
        downloadStatus.value = 'failed';
        isDownloading.value = false;
    }
};

const startPolling = () => {
    if (pollingInterval.value) {
        clearInterval(pollingInterval.value);
    }

    pollingInterval.value = setInterval(async () => {
        if (Date.now() - pollingStartTime.value > MAX_POLLING_TIME) {
            clearInterval(pollingInterval.value);
            isDownloading.value = false;
            downloadStatus.value = 'failed';
            console.error('Timeout: processamento demorou mais de 5 minutos');
            return;
        }

        try {
            const response = await axios.get(
                route('public.oitiva.status-download', {
                    oitiva: props.oitiva.id
                })
            );

            downloadStatus.value = response.data.status;

            if (response.data.ready) {
                clearInterval(pollingInterval.value);
                isDownloading.value = false;
                downloadStatus.value = 'completed';
            }
        } catch (error) {
            console.error('Erro ao verificar status:', error);
            clearInterval(pollingInterval.value);
            isDownloading.value = false;
            downloadStatus.value = 'failed';
        }
    }, 3000);
};

const baixarZip = () => {
    const downloadUrl = route('public.oitiva.download-zip', {
        oitiva: props.oitiva.id
    });
    window.open(downloadUrl, '_blank');
};

const resetDownload = () => {
    downloadStatus.value = 'pending';
    isDownloading.value = false;
    if (pollingInterval.value) {
        clearInterval(pollingInterval.value);
    }
};

// --- MODAL HASH ---
const showHashDialog = ref(false);

// --- ATALHOS DE TECLADO ---
const handleKeyboardShortcuts = (e) => {
    if (!videoElement.value) return;
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

    switch(e.key) {
        case ' ':
            e.preventDefault();
            if (videoElement.value.paused) {
                videoElement.value.play();
            } else {
                videoElement.value.pause();
            }
            break;
        case 'ArrowLeft':
            e.preventDefault();
            videoElement.value.currentTime = Math.max(0, videoElement.value.currentTime - 5);
            break;
        case 'ArrowRight':
            e.preventDefault();
            videoElement.value.currentTime = Math.min(
                videoElement.value.duration,
                videoElement.value.currentTime + 5
            );
            break;
        case 'f':
            e.preventDefault();
            toggleFullscreen();
            break;
    }
};

onMounted(() => {
    document.addEventListener('fullscreenchange', updateFullscreenState);
    document.addEventListener('keydown', handleKeyboardShortcuts);
});

onUnmounted(() => {
    if (pollingInterval.value) {
        clearInterval(pollingInterval.value);
    }
    document.removeEventListener('fullscreenchange', updateFullscreenState);
    document.removeEventListener('keydown', handleKeyboardShortcuts);
});
</script>

<template>
    <Head :title="`Assistindo Inquérito ${oitiva.numero_inquerito}`" />

    <div class="min-h-screen bg-gray-50 text-gray-900 p-4 md:p-6">

        <!-- Header -->
        <div class="max-w-[1600px] mx-auto mb-6">
            <div class="bg-black p-4 shadow-md border border-neutral-800 rounded-lg flex justify-between items-center">
                <div class="flex items-center gap-3 pr-4">
                    <img src="/images/logo-pc-branca.png" alt="Logo" class="h-12 w-auto object-contain pr-4 border-r border-neutral-500" />
                    <div>
                        <h1 class="text-xl font-bold text-white">Sistema VERBO</h1>
                        <span class="text-xs text-neutral-400 uppercase tracking-wider">Módulo de Visualização Segura</span>
                    </div>
                </div>
                <div class="text-right hidden md:block">
                    <div class="text-sm text-neutral-400">Acesso monitorado de:</div>
                    <div class="font-bold text-white">{{ viewer_info.nome }}</div>
                    <div class="text-xs text-neutral-400 font-mono">Matrícula: {{ viewer_info.matricula }}</div>
                </div>
            </div>
        </div>

        <!-- Vídeo + Transcrição -->
        <div class="max-w-[1600px] mx-auto mb-6">
            <div class="grid grid-cols-1 lg:grid-cols-10 gap-6">

                <!-- Vídeo (70%) -->
                <div class="lg:col-span-7">
                    <div
                        ref="playerContainer"
                        class="bg-black shadow-lg border border-gray-800 relative group player-container"
                        style="border-radius: 8px;"
                    >
                        <video
                            ref="videoElement"
                            controls
                            controlsList="nodownload nofullscreen noremoteplayback"
                            disablePictureInPicture
                            @contextmenu="preventContextMenu"
                            class="w-full mx-auto block object-contain relative z-10 video-element"
                        >
                            <source :src="url_video" type="video/mp4">
                            Seu navegador não suporta a reprodução deste vídeo.
                        </video>

                        <!-- Marca d'água -->
                        <div
                            class="absolute top-0 left-0 w-full z-20 pointer-events-none overflow-hidden select-none flex flex-wrap content-start justify-center gap-12 p-8 watermark-container"
                        >
                            <div v-for="i in 40" :key="i" class="transform -rotate-12 font-mono font-bold text-lg whitespace-nowrap" style="color: rgba(0, 0, 0, 0.15)">
                                {{ viewer_info.matricula }} • {{ viewer_info.nome }}
                            </div>
                        </div>

                        <!-- Botão fullscreen -->
                        <div class="absolute bottom-20 right-6 z-30">
                            <Button
                                @click="toggleFullscreen"
                                rounded
                                :icon="isFullscreen ? 'pi pi-times' : 'pi pi-window-maximize'"
                                class="fullscreen-btn-v2"
                                :pt="{
                                    root: {
                                        style: 'background-color: #000000 !important; color: #ffffff !important; border: 1px solid rgba(255, 255, 255, 0.2); width: 2.5rem; height: 2.5rem; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);'
                                    }
                                }"
                            />
                        </div>
                    </div>

                    <!-- Atalhos -->
                    <div class="flex items-center justify-center gap-2 text-xs text-gray-500 mt-3" v-if="!isFullscreen">
                        <i class="pi pi-info-circle"></i>
                        <span>Atalhos: Espaço (play/pause) • Setas (±5s) • F (tela cheia)</span>
                    </div>
                </div>

                <!-- Transcrição (30%) -->
                <div class="lg:col-span-3">
                    <div class="bg-white shadow-sm border border-gray-200 rounded-lg" style="height: 75vh;">
                        <div class="p-4 border-b border-gray-200">
                            <h3 class="font-bold text-lg text-gray-800 flex items-center gap-2 mb-3">
                                <i class="pi pi-file-edit text-black text-xl"></i>
                                Transcrição
                            </h3>

                            <Button
                                v-if="transcriptionItems.length > 0"
                                @click="handleDownloadTranscription"
                                icon="pi pi-download"
                                label="Baixar .srt"
                                size="small"
                                outlined
                                class="w-full"
                                :pt="{ root: { style: 'border-color: #000000 !important; color: #000000 !important;' } }"
                            />
                        </div>

                        <div
                            ref="transcriptionContainer"
                            @scroll="handleUserScroll"
                            class="overflow-y-auto p-4 space-y-3"
                            style="height: calc(75vh - 120px);"
                        >
                            <!-- Loading -->
                            <div v-if="isLoadingTranscription" class="flex flex-col items-center justify-center h-full text-gray-500">
                                <i class="pi pi-spin pi-spinner text-3xl mb-3"></i>
                                <p>Carregando...</p>
                            </div>

                            <!-- Error -->
                            <div v-else-if="transcriptionError" class="flex flex-col items-center justify-center h-full text-gray-500">
                                <i class="pi pi-exclamation-triangle text-3xl mb-3 text-red-500"></i>
                                <p>Erro ao carregar</p>
                            </div>

                            <!-- Empty -->
                            <div v-else-if="!transcriptionItems || transcriptionItems.length === 0" class="flex flex-col items-center justify-center h-full text-gray-500">
                                <i class="pi pi-file text-3xl mb-3"></i>
                                <p class="text-center px-4">Não disponível</p>
                            </div>

                            <!-- Items -->
                            <div
                                v-else
                                v-for="(item, index) in transcriptionItems"
                                :key="index"
                                @click="handleTranscriptionClick(item)"
                                :class="{
                                    'transcription-item-highlighted': currentHighlightIndex === index,
                                    'transcription-item': currentHighlightIndex !== index
                                }"
                                class="p-3 rounded cursor-pointer transition-all duration-200 border"
                            >
                                <p class="text-sm text-gray-800 leading-relaxed">{{ item.text }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card Compacto de Informações e Ações -->
        <div class="max-w-[1600px] mx-auto">
            <div class="bg-white shadow-sm border border-gray-200 rounded-lg p-6">

                <!-- Info linha única -->
                <div class="flex flex-wrap items-center gap-x-8 gap-y-3 mb-5 pb-5 border-b border-gray-200">
                    <div class="flex items-center gap-2">
                        <i class="pi pi-file text-gray-600"></i>
                        <span class="text-xs text-gray-600 uppercase font-semibold">Inquérito:</span>
                        <span class="font-mono font-bold text-gray-900">{{ oitiva.numero_inquerito }}</span>
                    </div>

                    <div class="flex items-center gap-2">
                        <i class="pi pi-user text-gray-600"></i>
                        <span class="text-xs text-gray-600 uppercase font-semibold">Declarante:</span>
                        <span class="font-bold text-gray-900">{{ oitiva.declarante.nome_completo }}</span>
                    </div>

                    <div class="flex items-center gap-2">
                        <i class="pi pi-briefcase text-gray-600"></i>
                        <span class="text-xs text-gray-600 uppercase font-semibold">Delegado:</span>
                        <span class="text-gray-900">{{ oitiva.nome_delegado_responsavel }}</span>
                    </div>

                    <div class="flex items-center gap-2">
                        <i class="pi pi-calendar text-gray-600"></i>
                        <span class="text-xs text-gray-600 uppercase font-semibold">Data:</span>
                        <span class="text-gray-900">{{ new Date(oitiva.created_at).toLocaleDateString('pt-BR') }}</span>
                    </div>

                    <div class="flex items-center gap-2">
                        <Tag severity="success" value="Integridade Verificada" icon="pi pi-check-circle" style="font-size: 0.75rem;" />
                    </div>
                </div>

                <!-- Ações -->
                <div class="flex flex-wrap gap-3">
                    <!-- Download ZIP -->
                    <div v-if="downloadStatus === 'pending'" class="flex-1 min-w-[200px]">
                        <Button
                            @click="iniciarDownload"
                            :disabled="isDownloading"
                            icon="pi pi-download"
                            label="Preparar Download do Vídeo"
                            class="w-full"
                            :pt="{ root: { style: 'background-color: #000000 !important; border-color: #000000 !important;' } }"
                        />
                    </div>

                    <div v-if="downloadStatus === 'processing'" class="flex-1 min-w-[200px]">
                        <Button
                            disabled
                            icon="pi pi-spin pi-spinner"
                            label="Compactando vídeo..."
                            class="w-full"
                            :pt="{ root: { style: 'background-color: #000000 !important; border-color: #000000 !important;' } }"
                        />
                    </div>

                    <div v-if="downloadStatus === 'completed'" class="flex gap-3 flex-1">
                        <Button
                            @click="baixarZip"
                            icon="pi pi-download"
                            label="Baixar Vídeo (ZIP)"
                            severity="success"
                            class="flex-1"
                        />
                        <Button
                            @click="resetDownload"
                            icon="pi pi-refresh"
                            outlined
                            severity="secondary"
                        />
                    </div>

                    <div v-if="downloadStatus === 'failed'" class="flex-1 min-w-[200px]">
                        <Button
                            @click="iniciarDownload"
                            icon="pi pi-refresh"
                            label="Tentar Novamente"
                            severity="danger"
                            class="w-full"
                        />
                    </div>

                    <!-- Botão Ver Hash -->
                    <Button
                        @click="showHashDialog = true"
                        icon="pi pi-shield"
                        label="Ver Hash SHA-256"
                        outlined
                        severity="secondary"
                    />

                    <!-- Info Auditoria -->
                    <div class="flex items-center gap-2 px-4 py-2 bg-blue-50 border border-blue-200 rounded">
                        <i class="pi pi-info-circle text-blue-600"></i>
                        <span class="text-xs text-blue-800">
                            Acesso auditado sob matrícula <strong>{{ viewer_info.matricula }}</strong>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Hash -->
        <Dialog
            v-model:visible="showHashDialog"
            modal
            header="Hash de Integridade SHA-256"
            :style="{ width: '50rem' }"
            :breakpoints="{ '1199px': '75vw', '575px': '90vw' }"
        >
            <div class="space-y-4">
                <div class="flex items-start gap-3 p-4 bg-blue-50 border border-blue-200 rounded">
                    <i class="pi pi-info-circle text-blue-600 text-xl mt-0.5"></i>
                    <div>
                        <p class="text-sm text-blue-900 font-semibold mb-1">Verificação de Autenticidade</p>
                        <p class="text-xs text-blue-800 leading-relaxed">
                            Este hash SHA-256 garante que o vídeo não foi alterado desde sua gravação.
                            Qualquer modificação no arquivo geraria um hash completamente diferente.
                        </p>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-gray-100 to-gray-200 p-4 border border-gray-300 rounded">
                    <label class="text-xs text-gray-700 font-bold uppercase block mb-2">Hash do Arquivo</label>
                    <div class="bg-white p-4 rounded border border-gray-300 font-mono text-sm text-gray-900 break-all leading-relaxed select-all">
                        {{ oitiva.hash_arquivo_video }}
                    </div>
                </div>

            </div>
        </Dialog>

    </div>
</template>

<style scoped>
/* Video */
video::-webkit-media-controls-fullscreen-button {
    display: none !important;
}

/* Video padrão */
.video-element {
    max-height: 75vh;
    transition: all 0.3s ease;
}

/* Player container em fullscreen */
.player-container:fullscreen {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100vw !important;
    height: 100vh !important;
}

.player-container:fullscreen .video-element {
    width: 100% !important;
    height: 100vh !important;
    max-height: 100vh !important;
}

/* Marca d'água ajuste de altura */
.watermark-container {
    height: calc(100% - 80px);
}

.player-container:fullscreen .watermark-container {
    height: calc(100% - 90px);
}

/* Botão fullscreen posicionamento */
.player-container:fullscreen .fullscreen-btn {
    bottom: 5rem !important;
    right: 2rem !important;
}

/* Botão fullscreen */
:deep(.fullscreen-btn) {
    transition: all 0.2s ease !important;
    background-color: #ffffff !important;
    color: #1f2937 !important;
}

:deep(.fullscreen-btn-v2:hover) {
    background-color: #1a1a1a !important;
    border-color: rgba(255, 255, 255, 0.4) !important;
    transform: scale(1.05);
    transition: all 0.2s ease;
}

:deep(.fullscreen-btn .p-button-icon) {
    color: #1f2937 !important;
}

/* Transcrição */
.transcription-item {
    background-color: #f9fafb;
    border-color: #e5e7eb;
    transition: all 0.3s ease; /* Transição */
}

.transcription-item:hover {
    background-color: #e9ecef;
    border-color: #d1d5db;
}

.transcription-item-highlighted {
    background-color: rgba(0, 0, 0, 0.75) !important;
    border-color: transparent !important;
    border-left: 4px solid #bea55a !important; /* Barra dourada lateral */
    color: white !important;
    font-weight: 500 !important;
    box-shadow: 0 4px 12px -2px rgb(0 0 0 / 0.3);
    transition: all 0.3s ease;
}

.transcription-item-highlighted p {
    color: white !important;
}

/* Marca d'água */
.transform.-rotate-12 {
    user-select: none;
    -webkit-user-select: none;
}

/* Animações */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
