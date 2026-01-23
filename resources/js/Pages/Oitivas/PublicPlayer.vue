<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import Tag from 'primevue/tag';
import Divider from 'primevue/divider';
import Button from 'primevue/button';
import axios from 'axios';
import { router } from '@inertiajs/vue3'

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

// Estados do download
const downloadStatus = ref('pending');
const isDownloading = ref(false);
const pollingInterval = ref(null);

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

onMounted(() => {
    document.addEventListener('fullscreenchange', updateFullscreenState);
});

onUnmounted(() => {
    if (pollingInterval.value) {
        clearInterval(pollingInterval.value);
    }
    document.removeEventListener('fullscreenchange', updateFullscreenState);
});
</script>

<template>
    <Head :title="`Assistindo Inquérito ${oitiva.numero_inquerito}`" />

    <div class="min-h-screen bg-gray-50 text-gray-900 p-4 md:p-8 flex flex-col items-center">

        <div class="w-full max-w-5xl flex justify-between items-center mb-6 bg-black p-4 shadow-md border-b rounded border-neutral-800">
            <div class="flex items-center gap-3 pr-4 border-neutral-700">
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

        <div class="w-full max-w-5xl mb-8">
            <div
                ref="playerContainer"
                class="bg-black shadow-lg overflow-hidden border border-gray-800 relative group flex flex-col justify-center"
                style="border-radius: 8px;"
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
                    <div v-for="i in 40" :key="i" class="transform -rotate-12 font-mono font-bold text-lg whitespace-nowrap" style="color: rgba(0, 0, 0, 0.15)">
                        {{ viewer_info.matricula }} • {{ viewer_info.nome }}
                    </div>
                </div>

                <div class="absolute bottom-20 right-6 z-30">
                    <Button
                        @click="toggleFullscreen"
                        rounded
                        :icon="isFullscreen ? 'pi pi-window-minimize' : 'pi pi-window-maximize'"
                        size="large"
                        class="fullscreen-btn"
                        :pt="{ root: { style: 'background-color: #ffffff !important; color: #1f2937 !important; border: 2px solid #111827; width: 3.5rem; height: 3.5rem; box-shadow: 0 25px 50px -12px rgb(0 0 0 / 0.25);' } }"
                    />
                </div>
            </div>

            <div class="flex items-center justify-center gap-2 text-xs text-gray-500 mt-3" v-if="!isFullscreen">
                <i class="pi pi-info-circle"></i>
                <span>Utilize o botão</span>
                <i class="pi pi-window-maximize text-gray-600"></i>
                <span>no player para tela cheia segura</span>
            </div>
        </div>

        <div class="w-full max-w-5xl grid grid-cols-1 md:grid-cols-5 gap-6 items-start">

            <div class="md:col-span-2 space-y-6">
                <div class="bg-white p-6 shadow-sm border border-gray-200" style="border-radius: 8px;">
                    <h2 class="text-lg font-bold mb-5 flex items-center gap-2 text-gray-800">
                        <i class="pi pi-file-edit text-black text-xl"></i>
                        Dados do Procedimento
                    </h2>

                    <div class="space-y-5">
                        <div>
                            <label class="text-xs text-gray-600 uppercase font-semibold block mb-1">Número do Inquérito</label>
                            <p class="font-mono text-base text-gray-900 font-semibold">{{ oitiva.numero_inquerito }}</p>
                        </div>
                        <div>
                            <label class="text-xs text-gray-600 uppercase font-semibold block mb-1">Tipo de Oitiva</label>
                            <p class="text-gray-900 capitalize">{{ oitiva.tipo_oitiva }}</p>
                        </div>
                        <div>
                            <label class="text-xs text-gray-600 uppercase font-semibold block mb-1">Delegado Responsável</label>
                            <p class="text-gray-900">{{ oitiva.nome_delegado_responsavel }}</p>
                        </div>
                        <div>
                            <label class="text-xs text-gray-600 uppercase font-semibold block mb-1">Data da Gravação</label>
                            <p class="text-gray-900">{{ new Date(oitiva.created_at).toLocaleDateString('pt-BR') }}</p>
                        </div>

                        <div class="h-px bg-gray-200 my-5"></div>

                        <div>
                            <label class="text-xs text-gray-600 uppercase font-semibold block mb-2">Declarante</label>
                            <p class="text-xl font-bold text-gray-800">{{ oitiva.declarante.nome_completo }}</p>
                            <p class="text-sm text-gray-600 mt-1" v-if="oitiva.declarante.cpf">CPF: {{ oitiva.declarante.cpf }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="md:col-span-3 space-y-6">

                <div class="bg-white p-6 shadow-sm border border-gray-200" style="border-radius: 8px;">
                    <h3 class="font-bold text-lg text-gray-800 mb-4 flex items-center gap-2">
                        <i class="pi pi-shield text-black text-xl"></i>
                        Autenticidade & Segurança
                    </h3>

                    <div class="mb-4">
                        <Tag severity="success" value="Integridade Verificada" icon="pi pi-check-circle" class="mb-3" style="font-size: 0.875rem; padding: 0.5rem 1rem;" />
                        <p class="text-sm text-gray-600 leading-relaxed">
                            Este vídeo possui assinatura digital e hash de integridade armazenado em banco de dados auditável.
                        </p>
                    </div>

                    <div class="bg-gradient-to-br from-gray-100 to-gray-200 p-4 border border-gray-200 shadow-inner" style="border-radius: 8px;">
                        <div class="flex items-center gap-2 mb-2">
                            <i class="pi pi-lock text-green-600"></i>
                            <label class="text-xs text-green-700 font-bold uppercase">Hash SHA-256 (Registro)</label>
                        </div>
                        <div class="bg-white/90 backdrop-blur-sm p-3 rounded border border-green-400/50">
                            <p class="text-xs font-mono text-green-800 break-all leading-relaxed font-semibold">
                                {{ oitiva.hash_arquivo_video }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 shadow-sm border border-gray-200" style="border-radius: 8px;">
                    <h3 class="text-lg font-bold mb-4 flex items-center gap-2 text-gray-800">
                        <i class="pi pi-download text-black text-xl"></i>
                        Download do Vídeo
                    </h3>

                    <div class="space-y-4">
                        <div v-if="downloadStatus === 'pending'" class="text-center">
                            <p class="text-sm text-gray-600 mb-4 text-left">
                            Clique no botão abaixo para preparar o vídeo para download.
                            O vídeo será dividido em partes e compactado.
                            </p>
                            <Button @click="iniciarDownload" :disabled="isDownloading" icon="pi pi-download" label="Preparar Download" class="w-full" :pt="{ root: { style: 'background-color: #000000 !important; border-color: #000000 !important;' } }" />
                        </div>

                        <div v-if="downloadStatus === 'processing'" class="text-center py-4">
                            <i class="pi pi-spin pi-spinner text-4xl text-black mb-3"></i>
                            <p class="text-gray-700 font-semibold">Compactando vídeo, aguarde...</p>
                        </div>

                        <div v-if="downloadStatus === 'completed'" class="text-center">
                            <Button @click="baixarZip" icon="pi pi-download" label="Baixar Vídeo (ZIP)" class="w-full" severity="success" />
                            <p class="text-xs text-gray-500 mt-3 text-left">
                                O arquivo ZIP contém o vídeo dividido em partes de até 15MB
                            e um arquivo com os hashes SHA-256 para verificação de integridade.
                            </p>
                        </div>

                        <div v-if="downloadStatus === 'failed'" class="text-center">
                            <i class="pi pi-times-circle text-4xl text-red-600 mb-3"></i>
                            <Button @click="iniciarDownload" icon="pi pi-refresh" label="Tentar Novamente" class="w-full" severity="danger" />
                        </div>
                    </div>
                </div>

                <div class="p-4 bg-gradient-to-br from-gray-100 to-gray-200 border border-gray-200" style="border-radius: 8px;">
                    <div class="flex items-start gap-3">
                        <i class="pi pi-info-circle text-black text-lg mt-0.5"></i>
                        <div class="flex-1">
                            <p class="text-sm text-blue-800 font-semibold mb-1">Acesso Monitorado</p>
                            <p class="text-xs text-blue-700 leading-relaxed">
                                Este acesso está sendo registrado sob a matrícula <span class="font-bold text-lg">{{ viewer_info.matricula }}</span> para fins de auditoria.
                            </p>
                        </div>
                    </div>
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

video {
    transition: all 0.3s ease;
}

/* Botão de fullscreen */
:deep(.fullscreen-btn) {
    transition: all 0.2s ease !important;
    background-color: #ffffff !important;
    color: #1f2937 !important;
}

:deep(.fullscreen-btn:hover) {
    background-color: #f3f4f6 !important;
    transform: scale(1.1) !important;
}

:deep(.fullscreen-btn .p-button-icon) {
    color: #1f2937 !important;
}

:deep(.p-button:hover) {
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
}

/* Cards com hover */
.bg-white {
    transition: box-shadow 0.2s ease;
}

.bg-white:hover {
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
}

/* Tag */
:deep(.p-tag) {
    font-weight: 600;
}

/* Animação no hash */
.font-mono {
    transition: color 0.2s ease;
}

/* Marca d'água */
.transform.-rotate-12 {
    user-select: none;
    -webkit-user-select: none;
}

/* Sombra interna no box do hash */
.shadow-inner {
    box-shadow: inset 0 2px 4px 0 rgb(0 0 0 / 0.05);
}

/* Efeito glassmorphism no hash */
.bg-white\/60 {
    background-color: rgba(255, 255, 255, 0.6);
}

.backdrop-blur-sm {
    backdrop-filter: blur(4px);
}

/* Box da matrícula */
.bg-white.px-3 {
    transition: all 0.2s ease;
}

.bg-white.px-3:hover {
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

/* Animação cards */
@media (min-width: 768px) {
    .md\:col-span-2,
    .md\:col-span-3 {
        animation: fadeInUp 0.5s ease-out;
    }
}

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
