<script setup>
import { ref, onUnmounted, computed, nextTick } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import RecordRTC from 'recordrtc';
import axios from 'axios';

// PrimeVue Components
import Card from 'primevue/card';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import ProgressBar from 'primevue/progressbar';
import Tag from 'primevue/tag';
import Panel from 'primevue/panel';
import Dialog from 'primevue/dialog';
import Message from 'primevue/message';
import Divider from 'primevue/divider';

// Props
const props = defineProps({
    oitiva: Object,
    url_video: String
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

// Função de Upload Atualizada
const uploadChunk = async (blob, isFinal = false) => {
    // Se não é o final e o blob está vazio, ignora
    if (blob.size === 0 && !isFinal) return;

    const formData = new FormData();
    
    // CORREÇÃO: Só anexa o arquivo se ele tiver conteúdo (> 0 bytes)
    if (blob.size > 0) {
        formData.append('video_part', blob, `part_${partNumber.value}.webm`);
    }
    
    formData.append('part_number', partNumber.value);
    formData.append('is_recording_complete', isFinal ? 1 : 0);
    
    try {
        isUploading.value = true;
        
        await axios.post(route('oitivas.upload', props.oitiva.id), formData);
        
        partNumber.value++;

        if (isFinal) {
            // Recarrega a página para atualizar o status visual
            router.reload({ only: ['oitiva'] });
        }
    } catch (error) {
        console.error("Erro no upload do chunk:", error.response?.data || error);
        // Sugestão: Mostrar mensagem de erro visual para o usuário
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

// --- BIOMETRIA (Lógica de Modal) ---
const modalBiometria = ref(false);
const tipoBiometriaAtual = ref(''); // 'declarante' ou 'representante'

const abrirModalBiometria = (tipo) => {
    tipoBiometriaAtual.value = tipo;
    modalBiometria.value = true;
};

const simularColetaBiometria = () => {
    // Aqui entraria a integração com o hardware de biometria
    // Vamos simular um POST para a rota de salvar
    router.post(route('oitivas.biometrias', props.oitiva.id), {
        tipo: tipoBiometriaAtual.value,
        // biometria_base64: '...' 
    }, {
        onSuccess: () => modalBiometria.value = false
    });
};

// --- FORMATAÇÃO ---
const formatDate = (date) => new Date(date).toLocaleString('pt-BR');
</script>

<template>
    <Head :title="`${oitiva.declarante.nome_completo} - Oitiva`" />

    <AuthenticatedLayout>
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Sala de Oitiva</h1>
                <p class="text-sm text-gray-500">Inquérito: {{ oitiva.numero_inquerito }}</p>
            </div>
            <nav class="text-sm">
                <ol class="flex text-gray-500 space-x-2">
                    <li><Link :href="route('dashboard')" class="hover:text-blue-600">Home</Link></li>
                    <li>/</li>
                    <li><Link :href="route('oitivas.index')" class="hover:text-blue-600">Oitivas</Link></li>
                    <li>/</li>
                    <li class="text-gray-800 font-semibold">{{ oitiva.declarante.nome_completo }}</li>
                </ol>
            </nav>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="lg:col-span-3">
                
                <Panel v-if="oitiva.caminho_arquivo_video" header="Registro Finalizado" toggleable>
                    <div class="text-center py-4">
                        <video controls class="w-full max-w-4xl mx-auto rounded shadow-lg bg-black">
                            <source :src="url_video" type="video/mp4">
                            Seu navegador não suporta a tag de vídeo.
                        </video>
                        <div class="mt-4">
                            <Tag severity="success" value="Arquivo Assinado Digitalmente" icon="pi pi-lock" />
                            <p class="text-xs text-gray-500 mt-2 font-mono">HASH: {{ oitiva.hash_arquivo_video }}</p>
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

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <Card>
                <template #title>Assinaturas Biométricas</template>
                <template #content>
                    <div class="grid grid-cols-2 gap-4 text-center">
                        
                        <div class="border p-4 rounded bg-gray-50">
                            <h4 class="font-bold text-gray-700 mb-2">Declarante</h4>
                            <div v-if="oitiva.biometria_declarante" class="mb-2">
                                <img :src="`/storage/${oitiva.biometria_declarante}`" class="h-24 mx-auto border" />
                            </div>
                            <div v-else class="h-24 flex items-center justify-center text-gray-400 border border-dashed mb-2">
                                Sem Assinatura
                            </div>
                            
                            <Button 
                                v-if="!oitiva.biometria_declarante"
                                label="Coletar Biometria" 
                                icon="pi pi-fingerprint" 
                                size="small" 
                                @click="abrirModalBiometria('declarante')"
                            />
                        </div>

                        <div class="border p-4 rounded bg-gray-50">
                            <h4 class="font-bold text-gray-700 mb-2">Representante</h4>
                            <div v-if="oitiva.representante">
                                <div v-if="oitiva.biometria_representante" class="mb-2">
                                    <img :src="`/storage/${oitiva.biometria_representante}`" class="h-24 mx-auto border" />
                                </div>
                                <div v-else class="h-24 flex items-center justify-center text-gray-400 border border-dashed mb-2">
                                    Sem Assinatura
                                </div>
                                <Button 
                                    v-if="!oitiva.biometria_representante"
                                    label="Coletar Biometria" 
                                    icon="pi pi-fingerprint" 
                                    size="small"
                                    severity="secondary"
                                    @click="abrirModalBiometria('representante')"
                                />
                            </div>
                            <div v-else class="flex items-center justify-center h-full text-sm text-gray-400">
                                Não aplicável
                            </div>
                        </div>

                    </div>
                </template>
            </Card>

            <Card>
                <template #title>Registro de Acessos</template>
                <template #content>
                    <DataTable :value="oitiva.audits || []" size="small" scrollable scrollHeight="200px" stripedRows>
                        <template #empty>Nenhum registro de acesso.</template>
                        <Column field="user.name" header="Usuário"></Column>
                        <Column field="ip_address" header="IP"></Column>
                        <Column header="Data">
                            <template #body="slotProps">
                                {{ formatDate(slotProps.data.created_at) }}
                            </template>
                        </Column>
                    </DataTable>
                </template>
            </Card>
        </div>

        <Dialog v-model:visible="modalBiometria" header="Coleta de Impressão Digital" :style="{ width: '30rem' }" modal>
            <div class="text-center">
                <i class="pi pi-wifi text-4xl text-blue-500 mb-4 animate-pulse"></i>
                <p class="mb-4">Posicione o <strong>indicador direito</strong> do {{ tipoBiometriaAtual }} no leitor biométrico.</p>
                <p class="text-sm text-gray-500">Aguardando leitura do dispositivo...</p>
            </div>
            <template #footer>
                <Button label="Cancelar" icon="pi pi-times" text @click="modalBiometria = false" />
                <Button label="Simular Captura" icon="pi pi-check" severity="success" @click="simularColetaBiometria" autofocus />
            </template>
        </Dialog>

    </AuthenticatedLayout>
</template>