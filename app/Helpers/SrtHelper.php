<?php

namespace App\Helpers;

/**
 * Helper para processar e validar arquivos SRT
 */
class SrtHelper
{
    /**
     * Normaliza e valida um arquivo SRT
     * Remove duplicatas, corrige sobreposições e valida formato
     */
    public static function normalize(string $srtContent): string
    {
        $lines = explode("\n", $srtContent);
        $entries = [];
        $currentEntry = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) {
                if (!empty($currentEntry)) {
                    $entries[] = $currentEntry;
                    $currentEntry = [];
                }
                continue;
            }

            $currentEntry[] = $line;
        }

        // Adiciona última entrada se existir
        if (!empty($currentEntry)) {
            $entries[] = $currentEntry;
        }

        // Processa e valida cada entrada
        $processedEntries = [];
        $index = 1;

        foreach ($entries as $entry) {
            $processed = self::processEntry($entry, $index);

            if ($processed) {
                $processedEntries[] = $processed;
                $index++;
            }
        }

        // Divide segmentos muito longos em segmentos menores
        $processedEntries = self::splitLongSegments($processedEntries);

        // Reindexar após divisão
        foreach ($processedEntries as $i => $entry) {
            $processedEntries[$i]['index'] = $i + 1;
        }

        // Remove sobreposições
        $processedEntries = self::removeOverlaps($processedEntries);

        // Reconstrói o SRT
        return self::rebuildSrt($processedEntries);
    }

    /**
     * Processa uma entrada individual do SRT
     */
    private static function processEntry(array $entry, int $index): ?array
    {
        if (count($entry) < 2) {
            return null;
        }

        // Encontra a linha de timestamp
        $timelineIndex = -1;
        foreach ($entry as $i => $line) {
            if (strpos($line, '-->') !== false) {
                $timelineIndex = $i;
                break;
            }
        }

        if ($timelineIndex === -1) {
            return null;
        }

        // Extrai timestamps
        $timeline = $entry[$timelineIndex];
        $timestamps = self::parseTimestamps($timeline);

        if (!$timestamps) {
            return null;
        }

        // Texto começa após a linha de timestamp
        $textLines = array_slice($entry, $timelineIndex + 1);
        $text = implode(' ', array_filter($textLines));

        if (empty($text)) {
            return null;
        }

        return [
            'index' => $index,
            'start' => $timestamps['start'],
            'end' => $timestamps['end'],
            'start_formatted' => $timestamps['start_formatted'],
            'end_formatted' => $timestamps['end_formatted'],
            'text' => trim($text)
        ];
    }

    /**
     * Parse timestamps do formato SRT
     * Aceita vírgula ou ponto como separador de milissegundos
     */
    private static function parseTimestamps(string $timeline): ?array
    {
        // Normaliza vírgula para ponto
        $timeline = str_replace(',', '.', $timeline);

        if (!preg_match('/(\d{1,2}):(\d{2}):(\d{2})\.(\d{3})\s*-->\s*(\d{1,2}):(\d{2}):(\d{2})\.(\d{3})/', $timeline, $matches)) {
            return null;
        }

        $start = self::timeToSeconds($matches[1], $matches[2], $matches[3], $matches[4]);
        $end = self::timeToSeconds($matches[5], $matches[6], $matches[7], $matches[8]);

        // Valida que o fim é maior que o início
        if ($end <= $start) {
            return null;
        }

        return [
            'start' => $start,
            'end' => $end,
            'start_formatted' => self::secondsToSrt($start),
            'end_formatted' => self::secondsToSrt($end)
        ];
    }

    /**
     * Converte tempo SRT para segundos
     */
    private static function timeToSeconds(string $hours, string $minutes, string $seconds, string $ms): float
    {
        return (int)$hours * 3600 + (int)$minutes * 60 + (int)$seconds + (int)$ms / 1000;
    }

    /**
     * Converte segundos para formato SRT (HH:MM:SS,mmm)
     */
    private static function secondsToSrt(float $totalSeconds): string
    {
        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $seconds = floor($totalSeconds % 60);
        $milliseconds = floor(($totalSeconds - floor($totalSeconds)) * 1000);

        return sprintf(
            '%02d:%02d:%02d,%03d',
            $hours,
            $minutes,
            $seconds,
            $milliseconds
        );
    }

    /**
     * Divide segmentos muito longos em segmentos menores
     */
    private static function splitLongSegments(array $entries): array
    {
        $maxWords = 20; // Máximo de palavras por segmento
        $result = [];

        foreach ($entries as $entry) {
            $words = preg_split('/\s+/', $entry['text']);
            $wordCount = count($words);

            // Se o segmento tem menos que o máximo, mantém como está
            if ($wordCount <= $maxWords) {
                $result[] = $entry;
                continue;
            }

            // Divide em múltiplos segmentos
            $duration = $entry['end'] - $entry['start'];
            $wordsPerSecond = $wordCount / $duration;

            $chunks = array_chunk($words, $maxWords);
            $currentStart = $entry['start'];

            foreach ($chunks as $chunkWords) {
                $chunkWordCount = count($chunkWords);
                $chunkDuration = $chunkWordCount / $wordsPerSecond;
                $chunkEnd = min($currentStart + $chunkDuration, $entry['end']);

                $result[] = [
                    'index' => count($result) + 1,
                    'start' => $currentStart,
                    'end' => $chunkEnd,
                    'start_formatted' => self::secondsToSrt($currentStart),
                    'end_formatted' => self::secondsToSrt($chunkEnd),
                    'text' => implode(' ', $chunkWords)
                ];

                $currentStart = $chunkEnd;
            }
        }

        return $result;
    }

    /**
     * Remove sobreposições entre legendas
     * Se houver sobreposição, ajusta o fim da primeira para terminar no início da segunda
     */
    private static function removeOverlaps(array $entries): array
    {
        if (empty($entries)) {
            return [];
        }

        $cleaned = [];
        $previous = null;

        foreach ($entries as $entry) {
            if ($previous) {
                // Se houver sobreposição, ajusta
                if ($entry['start'] < $previous['end']) {
                    // Deixa 50ms de gap
                    $previous['end'] = max($previous['start'] + 0.1, $entry['start'] - 0.05);
                    $previous['end_formatted'] = self::secondsToSrt($previous['end']);
                }

                $cleaned[] = $previous;
            }

            $previous = $entry;
        }

        // Adiciona a última entrada
        if ($previous) {
            $cleaned[] = $previous;
        }

        return $cleaned;
    }

    /**
     * Reconstrói o arquivo SRT a partir das entradas processadas
     */
    private static function rebuildSrt(array $entries): string
    {
        $lines = [];

        foreach ($entries as $entry) {
            $lines[] = $entry['index'];
            $lines[] = $entry['start_formatted'] . ' --> ' . $entry['end_formatted'];
            $lines[] = $entry['text'];
            $lines[] = ''; // Linha vazia entre entradas
        }

        return implode("\n", $lines);
    }

    /**
     * Valida se um SRT é válido
     */
    public static function validate(string $srtContent): array
    {
        $errors = [];
        $warnings = [];

        if (empty($srtContent)) {
            $errors[] = 'Conteúdo SRT vazio';
            return ['valid' => false, 'errors' => $errors, 'warnings' => $warnings];
        }

        if (!str_contains($srtContent, '-->')) {
            $errors[] = 'Nenhum timestamp encontrado no formato SRT';
            return ['valid' => false, 'errors' => $errors, 'warnings' => $warnings];
        }

        $segmentCount = substr_count($srtContent, '-->');

        if ($segmentCount === 0) {
            $errors[] = 'Nenhum segmento válido encontrado';
        }

        if ($segmentCount < 10) {
            $warnings[] = "Apenas {$segmentCount} segmentos encontrados - transcrição pode estar incompleta";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'segment_count' => $segmentCount
        ];
    }
}
