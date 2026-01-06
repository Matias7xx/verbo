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

const props = defineProps({
    oitiva: Object,
    upload_url: String // <--- Recebemos a URL assinada aqui
});

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
            // Normaliza para 0-100
            volumeLevel.value = Math.min(Math.round(volume / 1.5), 100); 
            animationFrameId = requestAnimationFrame(updateVolume);
        };
        updateVolume();
    } catch (e) {
        console.error("Erro no áudio monitor", e);
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
        alert("Erro ao acessar câmera: " + error.message);
    }
};

// --- LÓGICA DE GRAVAÇÃO (RECORDRTC) ---
const startRecording = () => {
    if (!videoCanvas.value) return;

    // Reinicia a trava
    isStopping.value = false; // <--- ADICIONE ISTO

    const canvasStream = videoCanvas.value.captureStream(30);
    const audioTrack = mediaStream.getAudioTracks()[0];
    if (audioTrack) canvasStream.addTrack(audioTrack);

    recorder = new RecordRTC(canvasStream, {
        type: 'video',
        mimeType: 'video/webm;codecs=vp9,opus',
        timeSlice: 5000,
        // MODIFICAÇÃO AQUI: Verifique se estamos parando
        ondataavailable: (blob) => {
            if (isStopping.value) return; // Se estiver parando, ignora este evento automático
            uploadChunk(blob, false);
        },
    });

    recorder.startRecording();
    isRecording.value = true;
    startTimer();
};

// Função para Parar
const stopRecording = () => {
    if (!recorder) return;

    // Ativa a trava imediatamente para bloquear o ondataavailable
    isStopping.value = true; // <--- ADICIONE ISTO
    isRecording.value = false;
    stopTimer();

    recorder.stopRecording(() => {
        // Pega o blob final (que contém o restante do buffer)
        const blob = recorder.getBlob();
        
        // Envia explicitamente como FINAL
        uploadChunk(blob, true);
        
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
            alert('Oitiva finalizada com sucesso! Você já pode fechar esta janela.');
            window.close(); // Tenta fechar a aba
        }
    } catch (error) {
        console.error("Erro upload:", error);
        alert("Erro no envio. Verifique a conexão.");
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
    <Head title="Gravação de Oitiva - VERBO" />

    <div class="min-h-screen bg-gray-100 p-6 flex flex-col items-center">
        
        <div class="w-full max-w-5xl bg-white p-4 rounded shadow mb-4 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <img src="/images/brasao_pcpb_nome.png" class="h-10" alt="Logo" /> <div>
                    <h1 class="text-xl font-bold text-gray-800">Sistema VERBO</h1>
                    <span class="text-sm text-gray-500">Módulo de Gravação Externa</span>
                </div>
            </div>
            <div class="text-right">
                <div class="font-bold text-blue-800">Inquérito: {{ oitiva.numero_inquerito }}</div>
                <div class="text-sm">Delegado: {{ oitiva.nome_delegado_responsavel }}</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="lg:col-span-3">
                
                <Panel v-if="oitiva.caminho_arquivo_video" header="Procedimento Concluído" class="border-green-500 border-t-4 shadow-md">
                    <div class="flex flex-col items-center justify-center py-10 text-center">
                        
                        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-green-100 mb-6">
                            <i class="pi pi-check-circle text-5xl text-green-600"></i>
                        </div>

                        <h2 class="text-2xl font-bold text-gray-800 mb-2">Oitiva Já Realizada</h2>
                        
                        <p class="text-gray-600 max-w-lg mx-auto mb-6">
                            A gravação de vídeo para o inquérito <strong>{{ oitiva.numero_inquerito }}</strong> já foi finalizada e o arquivo encontra-se armazenado com segurança.
                        </p>

                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 max-w-xl w-full text-left flex gap-3">
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

                <Panel v-else header="Captura e Gravação" class="border-blue-500 border-t-4">
                    <template #icons>
                        <Tag :severity="isRecording ? 'danger' : 'info'" :value="isRecording ? 'GRAVANDO' : 'AGUARDANDO'" />
                    </template>

                    <div class="flex flex-col items-center">
                        
                        <div class="flex gap-3 mb-4 w-full justify-center">
                            <Button 
                                v-if="!isPreviewing" 
                                label="Ativar Câmera" 
                                icon="pi pi-camera" 
                                @click="startPreview" 
                            />
                            
                            <template v-else>
                                <Button 
                                    label="Iniciar Gravação" 
                                    icon="pi pi-circle-fill" 
                                    severity="danger" 
                                    :disabled="isRecording" 
                                    @click="startRecording" 
                                    class="w-40"
                                />
                                <Button 
                                    label="Parar e Salvar" 
                                    icon="pi pi-stop-circle" 
                                    severity="secondary" 
                                    :disabled="!isRecording" 
                                    @click="stopRecording" 
                                    class="w-40"
                                />
                            </template>
                        </div>

                        <div class="relative bg-black rounded shadow-lg overflow-hidden" style="min-height: 480px; width: 100%; max-width: 854px;">
                            <div v-if="!isPreviewing" class="absolute inset-0 flex items-center justify-center text-gray-500">
                                <div class="text-center">
                                    <i class="pi pi-video text-6xl mb-2"></i>
                                    <p>Clique em "Ativar Câmera" para iniciar</p>
                                </div>
                            </div>
                            
                            <canvas ref="videoCanvas" class="w-full h-full object-contain"></canvas>

                            <div v-if="isRecording" class="absolute top-4 right-4 bg-red-600 text-white px-3 py-1 rounded font-mono text-xl animate-pulse shadow">
                                {{ recordingTime }}
                            </div>
                        </div>

                        <div class="w-full max-w-4xl mt-4">
                            <label class="text-xs text-gray-500 uppercase font-bold">Monitor de Áudio</label>
                            <ProgressBar :value="volumeLevel" :showValue="false" style="height: 10px;" :pt="{
                                value: { style: { background: 'linear-gradient(to right, #4ade80, #ef4444)' } }
                            }" />
                        </div>
                    </div>
                </Panel>
            </div>
        </div>

        <div class="mt-6 text-gray-400 text-sm">
            Conexão Segura &bull; UUID: {{ oitiva.uuid }}
        </div>
    </div>
</template>