import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

/**
 * Composable Vue para gerenciamento de transcrição
 */
export function useTranscription(videoRef, transcriptionUrl) {
    const transcriptionItems = ref([]);
    const currentHighlightIndex = ref(-1);
    const isLoading = ref(true);
    const hasError = ref(false);
    const userIsScrolling = ref(false);

    let scrollTimeout = null;
    let throttleTimer = null; // Throttle timer
    let cachedSrtContent = null;

    /**
     * Normaliza quebras de linha (evita regex múltiplas)
     */
    const normalizeLineBreaks = (text) => {
        return text.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
    };

    const parseSRT = (srtContent) => {
        const normalizedContent = normalizeLineBreaks(srtContent.trim());

        return normalizedContent
            .split(/\n\n+/)
            .map(block => {
                const lines = block.split('\n').filter(l => l.trim());
                if (lines.length < 2) return null;

                // Busca a linha com timestamp
                const timeLineIndex = lines.findIndex(l => l.includes('-->'));
                if (timeLineIndex === -1) return null;

                // Aceita vírgula OU ponto como separador de milissegundos
                const timeMatch = lines[timeLineIndex].match(
                    /(\d{1,2}):(\d{2}):(\d{2})[,.](\d{3})\s*-->\s*(\d{1,2}):(\d{2}):(\d{2})[,.](\d{3})/
                );

                if (!timeMatch) return null;

                // Texto começa após a linha de timestamp
                const textLines = lines.slice(timeLineIndex + 1);
                if (textLines.length === 0) return null;

                return {
                    start: parseTime(timeMatch[1], timeMatch[2], timeMatch[3], timeMatch[4]),
                    end: parseTime(timeMatch[5], timeMatch[6], timeMatch[7], timeMatch[8]),
                    text: textLines.join(' ').trim()
                };
            })
            .filter(Boolean);
    };

    /**
     * Converte timestamp SRT para segundos
     */
    const parseTime = (hours, minutes, seconds, milliseconds) => {
        return (
            parseInt(hours, 10) * 3600 +
            parseInt(minutes, 10) * 60 +
            parseInt(seconds, 10) +
            parseInt(milliseconds, 10) / 1000
        );
    };

    /**
     * Converte segundos para formato SRT (HH:MM:SS,mmm)
     */
    const formatTime = (totalSeconds) => {
        const hours = Math.floor(totalSeconds / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const seconds = Math.floor(totalSeconds % 60);
        const milliseconds = Math.floor((totalSeconds % 1) * 1000);

        return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')},${String(milliseconds).padStart(3, '0')}`;
    };

    /**
     * Carrega a transcrição com retry automático (3 tentativas)
     * Timeout de 10 segundos por tentativa
     */
    const loadTranscription = async (retriesLeft = 3) => {
        if (!transcriptionUrl) {
            isLoading.value = false;
            return;
        }

        try {
            isLoading.value = true;
            hasError.value = false;

            const response = await axios.get(transcriptionUrl, {
                timeout: 10000
            });

            cachedSrtContent = response.data.replace(/--&gt;/g, '-->');
            transcriptionItems.value = parseSRT(cachedSrtContent);
            isLoading.value = false;
        } catch (error) {
            console.error('Erro ao carregar transcrição:', error);

            // Retry automático
            if (retriesLeft > 1) {
                await new Promise(resolve => setTimeout(resolve, 1000));
                return loadTranscription(retriesLeft - 1);
            }

            hasError.value = true;
            isLoading.value = false;
        }
    };

    const updateHighlight = () => {
        if (!videoRef.value || transcriptionItems.value.length === 0) return;

        const currentTime = videoRef.value.currentTime;
        const items = transcriptionItems.value;

        // Busca binária otimizada
        let left = 0;
        let right = items.length - 1;
        let found = -1;

        while (left <= right) {
            const mid = Math.floor((left + right) / 2);
            const item = items[mid];

            // Tolerância aumentada de 150ms
            // Isso compensa a granularidade do evento timeupdate (~250ms)
            // look-ahead de 100ms para antecipar transições
            const tolerance = 0.15; // 150ms
            const lookAhead = 0.1;  // 100ms de antecipação

            if (currentTime >= (item.start - lookAhead - tolerance) &&
                currentTime <= (item.end + tolerance)) {
                found = mid;
                break;
            }

            if (currentTime < item.start) {
                right = mid - 1;
            } else {
                left = mid + 1;
            }
        }

        // Só atualiza se mudou
        if (currentHighlightIndex.value !== found) {
            currentHighlightIndex.value = found;
        }
    };

    // Throttle de 250ms
    const scheduleUpdate = () => {
        if (throttleTimer) return; // Se já houver um timer, ignora

        throttleTimer = setTimeout(() => {
            updateHighlight();
            throttleTimer = null; // Libera o timer
        }, 250); // Executa no máximo a cada 250ms
    };

    /**
     * Pula para um momento específico do vídeo
     */
    const seekToTime = (time) => {
        if (!videoRef.value) return;

        try {
            videoRef.value.currentTime = time;
            if (videoRef.value.paused) {
                videoRef.value.play().catch(() => {
                    // Ignora erros de autoplay
                });
            }
        } catch (error) {
            console.error('Erro ao buscar tempo:', error);
        }
    };

    /**
     * Detecta quando usuário está scrollando manualmente
     */
    const handleUserScroll = () => {
        userIsScrolling.value = true;
        clearTimeout(scrollTimeout);

        scrollTimeout = setTimeout(() => {
            userIsScrolling.value = false;
        }, 3000);
    };

    /**
     * Scroll automático - só rola se necessário
     */
    let lastScrollIndex = -1;
    const scrollToHighlighted = (containerRef) => {
        if (currentHighlightIndex.value === -1 || !containerRef || userIsScrolling.value) {
            return;
        }

        // Evita scroll desnecessário se o índice não mudou
        if (lastScrollIndex === currentHighlightIndex.value) {
            return;
        }

        const highlightedElement = containerRef.querySelector('.transcription-item-highlighted');

        if (highlightedElement) {
            // Verifica se o elemento já está visível antes de rolar
            const rect = highlightedElement.getBoundingClientRect();
            const containerRect = containerRef.getBoundingClientRect();

            const isVisible = (
                rect.top >= containerRect.top &&
                rect.bottom <= containerRect.bottom
            );

            // Só rola se não estiver visível
            if (!isVisible) {
                highlightedElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center',
                    inline: 'nearest'
                });
            }

            lastScrollIndex = currentHighlightIndex.value;
        }
    };

    /**
     * Reconstrói SRT a partir dos itens parseados
     */
    const reconstructSRT = () => {
        return transcriptionItems.value
            .map((item, index) => {
                return `${index + 1}\n${formatTime(item.start)} --> ${formatTime(item.end)}\n${item.text}\n`;
            })
            .join('\n');
    };

    /**
     * Baixa a transcrição como arquivo .srt
     * Usa cache quando possível para evitar nova requisição
     */
    const downloadSRT = async (filename = 'transcricao') => {
        try {
            let srtContent;

            // Prioridade: cache > reconstrução > nova requisição
            if (cachedSrtContent) {
                srtContent = cachedSrtContent;
            } else if (transcriptionItems.value.length > 0) {
                srtContent = reconstructSRT();
            } else {
                const response = await axios.get(transcriptionUrl);
                srtContent = response.data.replace(/--&gt;/g, '-->');
            }

            const blob = new Blob([srtContent], { type: 'text/plain;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');

            link.href = url;
            link.download = `${filename}.srt`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            // Limpeza de memória
            URL.revokeObjectURL(url);

            return true;
        } catch (error) {
            console.error('Erro ao baixar transcrição:', error);
            return false;
        }
    };

    /**
     * Limpeza de recursos
     */
    const cleanup = () => {
        if (videoRef.value) {
            videoRef.value.removeEventListener('timeupdate', scheduleUpdate);
        }
        if (throttleTimer) {
            clearTimeout(throttleTimer);
            throttleTimer = null;
        }
        clearTimeout(scrollTimeout);
        scrollTimeout = null;
    };

    onMounted(() => {
        if (videoRef.value) {
            videoRef.value.addEventListener('timeupdate', scheduleUpdate);
        }
        loadTranscription();
    });

    // Limpeza ao desmontar
    onUnmounted(() => {
        cleanup();
    });

    return {
        transcriptionItems,
        currentHighlightIndex,
        isLoading,
        hasError,
        userIsScrolling,
        seekToTime,
        scrollToHighlighted,
        downloadSRT,
        loadTranscription,
        handleUserScroll
    };
}
