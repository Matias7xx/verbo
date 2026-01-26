<script setup>
import { ref, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import RecordRTC from 'recordrtc';
import axios from 'axios';

// Componentes PrimeVue (Botões, Cards, etc...)
import Button from 'primevue/button';
import Panel from 'primevue/panel';
import Tag from 'primevue/tag';
import ProgressBar from 'primevue/progressbar';
import Toast from 'primevue/toast';
import ConfirmDialog from 'primevue/confirmdialog';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';

const props = defineProps({
    oitiva: Object,
    upload_url: String // <--- Recebemos a URL assinada aqui
});

const toast = useToast();
const confirm = useConfirm();

// --- ESTADOS DO GRAVADOR ---
const videoCanvas = ref(null); // Ref para o elemento <canvas>
const isPreviewing = ref(false);
const isRecording = ref(false);
const isUploading = ref(false);
const isStopping = ref(false);
const recordingTime = ref('00:00:00');
const volumeLevel = ref(0);
const partNumber = ref(1);

// Variáveis de controle (não reativas)
let mediaStream = null;
let audioContext = null;
let recorder = null;
let timerInterval = null;
let animationFrameId = null;
let secondsCounter = 0;

// --- LÓGICA DE ÁUDIO (VISUALIZER) ---
const initAudioMonitor = async (stream) => {
    try {
        audioContext = new AudioContext();
        const source = audioContext.createMediaStreamSource(stream);
        const analyser = audioContext.createAnalyser();
        analyser.fftSize = 256;
        const dataArray = new Uint8Array(analyser.frequencyBinCount);
        source.connect(analyser);

        const updateVolume = () => {
            if (!isPreviewing.value) return;
            analyser.getByteFrequencyData(dataArray);
            const volume = dataArray.reduce((a, b) => a + b) / dataArray.length;
            volumeLevel.value = Math.min(Math.round(volume / 1.5), 100);
            animationFrameId = requestAnimationFrame(updateVolume);
        };
        updateVolume();
    } catch (e) {
        console.error("Erro no áudio monitor:", e);
        toast.add({
            severity: 'warn',
            summary: 'Aviso',
            detail: 'Monitor de áudio não disponível',
            life: 3000
        });
    }
};

// --- LÓGICA DE VÍDEO (CANVAS + OVERLAY) ---
const startPreview = async () => {
    try {
        mediaStream = await navigator.mediaDevices.getUserMedia({
            video: { width: { ideal: 1280 }, height: { ideal: 720 } },
            audio: true
        });

        isPreviewing.value = true;

        // Inicia monitor de áudio
        initAudioMonitor(mediaStream);

        // Configura vídeo oculto para desenhar no canvas
        const videoElement = document.createElement("video");
        videoElement.srcObject = mediaStream;
        videoElement.muted = true;
        await videoElement.play();

        // Configura Canvas
        const canvas = videoCanvas.value;
        const ctx = canvas.getContext("2d");
        canvas.width = 1280;
        canvas.height = 720;

        const drawFrame = () => {
            if (!isPreviewing.value) return;

            // 1. Desenha o vídeo
            ctx.drawImage(videoElement, 0, 0, canvas.width, canvas.height);

            // 2. Desenha a Faixa Preta Transparente
            ctx.fillStyle = "rgba(0,0,0,0.6)";
            ctx.fillRect(0, canvas.height - 60, canvas.width, 60);

            // 3. Desenha o Texto (Dados do Inquérito)
            ctx.fillStyle = "white";
            ctx.font = "bold 24px Arial";
            ctx.shadowColor = "black";
            ctx.shadowBlur = 4;

            const textoNome = props.oitiva.declarante.nome_completo || 'Declarante Desconhecido';
            const textoInquerito = `Inquérito: ${props.oitiva.numero_inquerito}`;

            ctx.fillText(textoNome, 20, canvas.height - 32);
            ctx.font = "18px Arial";
            ctx.fillText(textoInquerito, 20, canvas.height - 10);

            // Indicador visual de REC no Canvas
            if (isRecording.value) {
                ctx.fillStyle = "red";
                ctx.beginPath();
                ctx.arc(canvas.width - 40, 40, 15, 0, 2 * Math.PI);
                ctx.fill();
                ctx.font = "bold 16px Arial";
                ctx.fillStyle = "white";
                ctx.fillText("REC", canvas.width - 85, 45);
            }

            requestAnimationFrame(drawFrame);
        };
        drawFrame();

    } catch (error) {
        console.error("Erro ao acessar câmera:", error);

        let errorMessage = "Erro ao acessar câmera";
        if (error.name === 'NotAllowedError') {
            errorMessage = "Permissão de câmera negada. Autorize o acesso e tente novamente.";
        } else if (error.name === 'NotFoundError') {
            errorMessage = "Nenhuma câmera encontrada no dispositivo.";
        }

        toast.add({
            severity: 'error',
            summary: 'Erro',
            detail: errorMessage,
            life: 5000
        });
    }
};

// --- LÓGICA DE GRAVAÇÃO (RECORDRTC) ---
const startRecording = () => {
    if (!videoCanvas.value) return;

    isStopping.value = false;

    const canvasStream = videoCanvas.value.captureStream(30);
    const audioTrack = mediaStream.getAudioTracks()[0];
    if (audioTrack) canvasStream.addTrack(audioTrack);

    recorder = new RecordRTC(canvasStream, {
        type: 'video',
        mimeType: 'video/webm;codecs=vp9,opus',
        timeSlice: 5000,
        ondataavailable: (blob) => {
            // Removido if (isStopping.value) return
            // Isso impedia o último chunk de ser enviado!
            uploadChunk(blob, false);
        },
    });

    recorder.startRecording();
    isRecording.value = true;
    startTimer();

    toast.add({
        severity: 'info',
        summary: 'Gravação Iniciada',
        detail: 'A oitiva está sendo gravada',
        life: 2000
    });
};

// Função para Parar com confirmação
const confirmStopRecording = () => {
    confirm.require({
        message: 'Tem certeza que deseja finalizar a gravação? Esta ação não pode ser desfeita.',
        header: 'Confirmar Finalização',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Sim, Finalizar',
        rejectLabel: 'Cancelar',
        accept: () => {
            stopRecording();
        }
    });
};

const stopRecording = () => {
    if (!recorder) return;

    // Ativa a trava imediatamente para bloquear o ondataavailable
    isStopping.value = true;
    isRecording.value = false;
    stopTimer();

    toast.add({
        severity: 'info',
        summary: 'Finalizando',
        detail: 'Processando gravação final...',
        life: 3000
    });

    recorder.stopRecording(async () => {
        await new Promise(resolve => setTimeout(resolve, 500));

        // Verifica se ainda tem algum chunk não enviado
        const lastBlob = recorder.getBlob();
        if (lastBlob && lastBlob.size > 0) {
            console.log('Enviando último chunk não processado:', (lastBlob.size / 1024).toFixed(2), 'KB');
            await uploadChunk(lastBlob, false);
        }

        // Agora sinaliza o fim da gravação (sem enviar blob)
        console.log('Sinalizando fim da gravação');
        await uploadChunk(new Blob([]), true);

        // Limpeza
        if (mediaStream) {
            mediaStream.getTracks().forEach(track => track.stop());
        }
        isPreviewing.value = false;
        // isStopping.value volta a ser false na próxima vez que iniciar
    });
};

// DIFERENÇA CRÍTICA NA FUNÇÃO DE UPLOAD:
const uploadChunk = async (blob, isFinal = false) => {
    if (blob.size === 0 && !isFinal) return;

    const formData = new FormData();
    if (blob.size > 0) {
        // Forçando MIME Type
        const fileObj = new File([blob], `part_${partNumber.value}.webm`, { type: 'video/webm' });
        formData.append('video_part', fileObj);
    }
    formData.append('part_number', partNumber.value);
    formData.append('is_recording_complete', isFinal ? 1 : 0);

    try {
        isUploading.value = true;

        // USA A URL ASSINADA PASSADA VIA PROP
        // O Axios manterá a query string (?signature=...) se ela estiver na url
        await axios.post(props.upload_url, formData);

        partNumber.value++;

        if (isFinal) {
            toast.add({
                severity: 'success',
                summary: 'Concluído!',
                detail: 'Oitiva finalizada e salva com sucesso',
                life: 5000
            });

            setTimeout(() => {
                window.close();
            }, 2000);
        }
    } catch (error) {
        console.error("Erro upload:", error);

        let errorDetail = "Erro ao enviar dados. Verifique sua conexão.";
        if (error.response) {
            if (error.response.status === 413) {
                errorDetail = "Arquivo muito grande. Tente gravar em sessões menores.";
            } else if (error.response.status >= 500) {
                errorDetail = "Erro no servidor. Tente novamente.";
            }
        } else if (error.request) {
            errorDetail = "Sem conexão com o servidor. Verifique sua internet.";
        }

        toast.add({
            severity: 'error',
            summary: 'Erro no Upload',
            detail: errorDetail,
            life: 5000
        });

        // Se for o chunk final e falhou, tenta novamente após delay
        if (isFinal) {
            setTimeout(() => {
                uploadChunk(blob, true);
            }, 3000);
        }
    } finally {
        isUploading.value = false;
    }
};

// --- UTILITÁRIOS ---
const startTimer = () => {
    secondsCounter = 0;
    timerInterval = setInterval(() => {
        secondsCounter++;
        recordingTime.value = new Date(secondsCounter * 1000).toISOString().substring(11, 19);
    }, 1000);
};

const stopTimer = () => {
    clearInterval(timerInterval);
};

// Limpeza ao sair da página
onUnmounted(() => {
    if (mediaStream) mediaStream.getTracks().forEach(t => t.stop());
    if (timerInterval) clearInterval(timerInterval);
    if (animationFrameId) cancelAnimationFrame(animationFrameId);
    if (audioContext) audioContext.close();
});

// --- FORMATAÇÃO ---
const formatDate = (date) => new Date(date).toLocaleString('pt-BR');

</script>

<template>
    <Head title="Gravação de Oitiva" />

    <Toast />
    <ConfirmDialog />

    <div class="min-h-screen bg-gray-100 p-6 flex flex-col items-center">

        <div class="w-full max-w-5xl bg-black p-4 shadow-md mb-4 flex justify-between items-center border-b rounded border-neutral-800">
            <div class="flex items-center gap-3 pr-4 border-neutral-700">
                <img src="/images/logo-pc-branca.png" class="h-12 w-auto object-contain pr-4 border-r border-neutral-500" alt="Logo" />
                <div>
                    <h1 class="text-xl font-bold text-white">Sistema VERBO</h1>
                    <span class="text-sm text-neutral-400">Módulo de Gravação Externa</span>
                </div>
            </div>
            <div class="text-right">
                <div class="font-bold text-white">Inquérito: {{ oitiva.numero_inquerito }}</div>
                <div class="text-sm text-neutral-400">Delegado: {{ oitiva.nome_delegado_responsavel }}</div>
            </div>
        </div>

        <div class="w-full max-w-5xl">
            <div class="lg:col-span-3">

                <Panel v-if="oitiva.caminho_arquivo_video" header="Procedimento Concluído" class="border-green-500 border-t-4 shadow-md">
                    <div class="flex flex-col items-center justify-center py-10 text-center">

                        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gray-100 mb-6">
                            <i class="pi pi-check-circle text-5xl text-green-600"></i>
                        </div>

                        <h2 class="text-2xl font-bold text-gray-800 mb-2">Oitiva Já Realizada</h2>

                        <p class="text-gray-600 max-w-lg mx-auto mb-6">
                            A gravação de vídeo para o inquérito <strong>{{ oitiva.numero_inquerito }}</strong> já foi finalizada e o arquivo encontra-se armazenado com segurança.
                        </p>

                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 max-w-xl w-full text-left flex gap-3">
                            <i class="pi pi-info-circle text-blue-500 text-xl mt-1"></i>
                            <div>
                                <h4 class="font-bold text-blue-900 text-sm uppercase mb-1">Como assistir?</h4>
                                <p class="text-sm text-blue-800 leading-relaxed">
                                    Este ambiente é exclusivo para <strong>gravação</strong>. Para visualizar o vídeo, retorne ao sistema de gestão e solicite o <strong>Link de Visualização</strong>.
                                </p>
                            </div>
                        </div>

                        <div class="mt-8">
                            <Tag severity="success" value="Arquivo Assinado Digitalmente" icon="pi pi-lock" rounded></Tag>
                            <p class="text-xs text-gray-400 font-mono mt-2 break-all">HASH SHA-256: {{ oitiva.hash_arquivo_video }}</p>
                        </div>

                    </div>
                </Panel>

                <Panel v-else header="Captura e Gravação" class="border border-gray-200 shadow-sm">
                    <template #icons>
                        <Tag
                            :severity="isRecording ? 'danger' : null"
                            :value="isRecording ? 'GRAVANDO' : 'PRONTO'"
                            :icon="isRecording ? 'pi pi-circle-fill' : 'pi pi-check-circle'"
                            :class="!isRecording ? 'bg-gray-500 border-gray-600' : ''"
                        />
                    </template>

                    <div class="flex flex-col items-center gap-4 py-2">

                        <div class="flex gap-3 mb-2 w-full justify-center">
                            <Button
                                v-if="!isPreviewing"
                                label="Ativar Câmera"
                                icon="pi pi-camera"
                                @click="startPreview"
                                size="large"
                                severity="secondary"
                                class="px-8"
                            />

                            <template v-else>
                                <Button
                                    label="Iniciar Gravação"
                                    icon="pi pi-circle-fill"
                                    severity="danger"
                                    :disabled="isRecording"
                                    @click="startRecording"
                                    size="large"
                                    class="w-48 font-semibold"
                                />
                                <Button
                                    label="Parar e Salvar"
                                    icon="pi pi-stop-circle"
                                    :disabled="!isRecording"
                                    @click="confirmStopRecording"
                                    size="large"
                                    class="w-48 font-semibold bg-neutral-700 hover:bg-neutral-800 border-neutral-700"
                                />
                            </template>
                        </div>

                        <div class="relative bg-black border border-gray-800 shadow-lg overflow-hidden" style="min-height: 480px; width: 100%; max-width: 854px; border-radius: 8px;">
                            <div v-if="!isPreviewing" class="absolute inset-0 flex items-center justify-center text-gray-500">
                                <div class="text-center">
                                    <i class="pi pi-video text-6xl mb-3 text-gray-600"></i>
                                    <p class="text-gray-500 mb-4">Clique em "Ativar Câmera" para iniciar</p>
                                    <div class="text-xs text-gray-400 space-y-1">
                                        <div class="flex items-center justify-center gap-2">
                                            <i class="pi pi-check-circle text-green-500"></i>
                                            <span>Permita acesso à câmera e microfone</span>
                                        </div>
                                        <div class="flex items-center justify-center gap-2">
                                            <i class="pi pi-check-circle text-green-500"></i>
                                            <span>Verifique sua conexão de internet</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <canvas ref="videoCanvas" class="w-full h-full object-contain"></canvas>

                            <!-- Timer de Gravação -->
                            <div v-if="isRecording" class="absolute top-4 right-4 bg-red-600 text-white px-4 py-2 font-mono text-lg animate-pulse shadow-lg" style="border-radius: 6px;">
                                <i class="pi pi-circle-fill mr-2 text-xs"></i>
                                {{ recordingTime }}
                            </div>
                        </div>

                        <!-- Monitor de Áudio -->
                        <div class="w-full max-w-4xl mt-3">
                            <div class="flex items-center justify-between mb-2">
                                <label class="text-sm text-gray-700 font-semibold flex items-center gap-2">
                                    <i class="pi pi-volume-up text-gray-600"></i>
                                    Monitor de Áudio
                                </label>
                                <span class="text-xs text-gray-500 font-mono">{{ volumeLevel }}%</span>
                            </div>
                            <ProgressBar
                                :value="volumeLevel"
                                :showValue="false"
                                style="height: 16px; border-radius: 8px;"
                                :pt="{
                                    value: {
                                        style: {
                                            background: volumeLevel < 30 ? '#22c55e' : volumeLevel < 70 ? '#eab308' : '#ef4444',
                                            borderRadius: '8px'
                                        }
                                    },
                                    root: {
                                        style: {
                                            backgroundColor: '#e5e7eb'
                                        }
                                    }
                                }"
                            />
                        </div>
                    </div>
                </Panel>
            </div>
        </div>

        <div class="mt-4 text-gray-500 text-xs flex items-center gap-2">
            <i class="pi pi-lock text-green-600"></i>
            <span>Conexão Segura</span>
            <span class="text-gray-400">•</span>
            <span class="font-mono">UUID: {{ oitiva.uuid }}</span>
        </div>
    </div>
</template>

<style scoped>
:deep(.p-button) {
    font-weight: 600;
    transition: all 0.2s;
}

:deep(.p-button:hover:not(:disabled)) {
    transform: translateY(-1px);
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
}

/* Panel header */
:deep(.p-panel .p-panel-header) {
    background: linear-gradient(to right, #f9fafb, #ffffff);
    border-bottom: 2px solid #e5e7eb;
    font-weight: 600;
    font-size: 1.1rem;
    padding: 1rem 1.25rem;
}

/* Tag neutra */
:deep(.p-tag.bg-gray-500) {
    background-color: #6b7280 !important;
    color: white;
}

/* Animação canvas */
canvas {
    transition: opacity 0.3s ease;
}

/* contraste do timer */
@keyframes pulse-slow {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
}

.animate-pulse {
    animation: pulse-slow 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
</style>
