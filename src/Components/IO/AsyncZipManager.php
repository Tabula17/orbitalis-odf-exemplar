<?php

namespace Tabula17\Orbitalis\Odf\Co\Components\IO;


use Swoole\Coroutine;

use ZipArchive;

/**
 * Manages asynchronous operations for ZIP file compression and extraction.
 * Provides methods to handle large ZIP files while ensuring minimal blocking
 * through the use of generators and cooperative multitasking.
 */
class AsyncZipManager
{
    public function extractAsync(string $filePath, string $extractTo): \Generator
    {
        // Primer yield indica "estoy iniciando"
        yield Coroutine::sleep(0.001);

        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new \RuntimeException("Cannot open ZIP file");
        }

        // Extracción con pausas periódicas
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $zip->extractTo($extractTo, $zip->getNameIndex($i));

            if ($i % 10 === 0) {
                yield Coroutine::sleep(0.001); // Cooperación
            }
        }

        $zip->close();
        return $extractTo;
    }

    public function extractTo(string $filePath, string $extractTo): string
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new \RuntimeException("Failed to open ZIP file");
        }

        // Proceso asíncrono seguro
        $result = $zip->extractTo($extractTo);
        $zip->close();

        if (!$result) {
            throw new \RuntimeException("Extraction failed");
        }

        return $extractTo;
    }

    public function compressAsync(string $sourceDir, string $outputFile): \Generator
    {
        yield Coroutine::sleep(0.001);
        $sourceDir = rtrim($sourceDir, '/');
        // Usar streams para archivos grandes
        $zip = new ZipArchive();
        $zip->open($outputFile, ZipArchive::CREATE);

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($sourceDir) );
                // Usar streams para archivos > 5MB
                if (filesize($filePath) > 5 * 1024 * 1024) {
                    $stream = fopen($filePath, 'rb');
                    $zip->addFromString($relativePath, $stream);
                    fclose($stream);
                } else {
                    $zip->addFile($filePath, $relativePath);
                }

                yield Coroutine::sleep(0.001);
            }
        }
        $zip->close();
        return $outputFile;
    }

    public function compress(string $sourceDir, string $outputFile, $co = false): string
    {

        $zip = new ZipArchive();

        if ($zip->open($outputFile, ZipArchive::CREATE) !== true) {
            throw new \RuntimeException("Cannot create ZIP file");
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($sourceDir) + 1);

                $zip->addFile($filePath, $relativePath);
                // Permitir a Swoole manejar otros eventos
                if($co){
                    Coroutine::sleep(0.001);
                }
            }
        }

        $zip->close();
        return $outputFile;
    }
}