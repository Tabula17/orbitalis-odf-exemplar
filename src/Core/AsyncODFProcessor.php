<?php

namespace Tabula17\Orbitalis\Odf\Co\Core;


use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Event;
use Tabula17\Orbitalis\Odf\Co\Components\IO\AsyncZipManager;
use Tabula17\Orbitalis\Odf\Co\Components\Job;
use Tabula17\Orbitalis\Odf\Co\Components\Template\AsyncTemplateEngine;
use Tabula17\Satelles\Odf\ExporterInterface;
use Tabula17\Satelles\Odf\OdfContainerInterface;

/**
 * Class AsyncODFProcessor
 *
 * Handles asynchronous processing of Open Document Format (ODF) files.
 * This class works with a pool of jobs, providing the capability to process
 * tasks such as templating, generating, compressing, and resolving actions
 * concurrently. It leverages coroutines and asynchronous operations to achieve
 * non-blocking execution and increase system throughput.
 *
 * Responsibilities:
 * - Add and manage jobs for concurrent processing.
 * - Define and associate resolvers for dynamic processing logic.
 * - Carry out asynchronous operations for file handling (e.g., compression,
 *   extraction, templating).
 * - Tackle large file deletions with options for progress reporting and concurrency.
 * - Report back job status (success, error, timestamps, etc.) upon task completion.
 */
class AsyncODFProcessor
{
    private array $jobsPool = [];
    private array $resolvers = [];

    public function __construct(
        private readonly OdfContainerInterface $fileContainer,
        private readonly AsyncTemplateEngine   $templateEngine,
        private readonly AsyncZipManager       $zipManager
    )
    {
    }

    public function addJob(Job $job): void
    {
        $this->jobsPool[] = $job;
    }

    public function addResolver(string $name, ExporterInterface $resolver): AsyncODFProcessor
    {
        $this->resolvers[$name] = $resolver;
        return $this;
    }

    public function processConcurrent(Channel $channel): Channel
    {
        /**
         * @var Job $job
         */
        while ($job = array_shift($this->jobsPool)) {
            Coroutine::create(function () use ($job, $channel) {
                try {
                    $job->updatedAt = date_create()->format('c');
                    $tempDir = $this->await($this->zipManager->extractAsync($job->template, $job->workingDir . DIRECTORY_SEPARATOR . $job->outputName));
                    $parts = $this->await($this->fileContainer->loadFile($tempDir));
                    $processed = $this->await($this->templateEngine->renderAsync($job->data, $job->workingDir));
                    $this->await($this->fileContainer->saveFile());
                    $job->results[] = $this->await($this->zipManager->compressAsync($tempDir, $job->outputDir . DIRECTORY_SEPARATOR . $job->outputName . '.odt'));
                    $this->await($this->deleteFileOrDirectoryAsync($job->workingDir));
                    $job->completedAt = date_create()->format('c');
                    $job->success = true;
                    if ($job->actions) {
                        $i = 0;
                        foreach ($job->actions as $action) {
                            $this->verboseLog('Processing action: ' . $action);
                            if (isset($this->resolvers[$action])) {
                                $this->verboseLog('Processing with resolver: ' . $this->resolvers[$action]->exporterName);
                                /**
                                 * @var ExporterInterface $resolver
                                 *
                                 * Variable resolver responsible for dynamically resolving and returning
                                 * the value of variables at runtime. The resolver determines the value
                                 * based on context, configuration, or other logic defined during its usage.
                                 *
                                 * This class/object provides a mechanism to abstract variable extraction
                                 * by implementing a consistent interface for resolving variable values.
                                 *
                                 * It can be used in scenarios where variable dependencies and values
                                 * need dynamic computation based on runtime conditions or specific logic.
                                 *
                                 * Responsibilities:
                                 * - Identify and resolve variables within the defined scope.
                                 * - Allows pluggable or overridable logic for variable computation.
                                 * - Supports dynamic evaluation of variables instead of static values.
                                 */
                                $resolver = $this->resolvers[$action];
                                $parameters = is_array($job->parameters) && array_key_exists($action, $job->parameters) ? $job->parameters[$action] : $job->parameters;
                                $parentResult = $job->results[$i];
                                if (is_string($parentResult)) {
                                    $nextJob = [$parentResult, $parameters];
                                } else {
                                    $this->verboseLog('Parent result: ' . var_export($parentResult, true));
                                    $nextJob = [array_shift($parentResult), array_merge($parameters, array_shift($parentResult))];
                                }
                                $data = $this->await($resolver->processFile(...$nextJob));
                                $job->results[] = $data;
                                $this->verboseLog('Action result: ' . var_export($data, true));
                                $i++;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    $job->success = false;
                    $job->errorMessage = $e->getMessage();
                    $job->updatedAt = date_create()->format('c');
                    $job->completedAt = null;
                }
                $channel->push($job);
            });
            Event::wait();
        }
        return $channel;
    }

    private function await(\Generator $generator): mixed
    {
        $value = null;
        while ($generator->valid()) {
            try {
                $value = $generator->current();

                // Si es otra corrutina, la procesamos recursivamente
                if ($value instanceof \Generator) {
                    $value = $this->await($value);
                }

                $generator->send($value);
            } catch (\Throwable $e) {
                $generator->throw($e);
            }
        }
        return $generator->getReturn();
    }

    public function getJobs(): array
    {
        return $this->jobsPool;
    }

    /**
     * Elimina archivos o directorios de forma asíncrona y no bloqueante
     *
     * @param string $filename Ruta a eliminar
     * @param float $chunkDelay Microsegundos entre operaciones para cooperación
     * @return \Generator Devuelve bool indicando éxito
     */
    public function deleteFileOrDirectoryAsync(string $filename, float $chunkDelay = 0.001): \Generator
    {
        // Primera pausa para cooperación
        yield Coroutine::sleep(0.001);

        if (!file_exists($filename)) {
            yield true;
            return true;
        }

        // Operación no bloqueante para archivos
        if (!is_dir($filename)) {
            $result = unlink($filename);
            yield $result;
            return $result;
        }

        // Procesar directorio de forma recursiva
        $items = scandir($filename);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $filename . DIRECTORY_SEPARATOR . $item;

            // Procesar en corrutinas separadas para directorios grandes
            if (is_dir($path)) {
                $success = yield from $this->deleteFileOrDirectoryAsync($path, $chunkDelay);
            } else {
                $success = unlink($path);
                yield Coroutine::sleep($chunkDelay);
            }

            if (!$success) {
                yield false;
                return false;
            }
        }
        $result = rmdir($filename);
        yield $result;
        return $result;
    }

    public function deleteLargeDirectoryAsync(string $dirPath, int $concurrency = 5): \Generator
    {
        if (!is_dir($dirPath)) {
            yield false;
            return false;
        }

        $channel = new Coroutine\Channel($concurrency);
        $success = true;

        $deleteTask = function (string $path) use ($channel) {
            try {
                if (is_dir($path)) {
                    $result = yield from $this->deleteLargeDirectoryAsync($path);
                } else {
                    $result = unlink($path);
                }
                $channel->push(['path' => $path, 'success' => $result]);
            } catch (\Throwable $e) {
                $channel->push(['path' => $path, 'success' => false]);
            }
        };

        $items = scandir($dirPath);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;

            $path = $dirPath . DIRECTORY_SEPARATOR . $item;
            Coroutine::create($deleteTask, $path);
        }

        // Esperar resultados
        $remaining = count($items) - 2; // Excluir . y ..
        while ($remaining > 0) {
            $result = $channel->pop();
            if (!$result['success']) {
                $success = false;
            }
            $remaining--;
            yield Coroutine::sleep(0.001); // Cooperación
        }

        // Eliminar el directorio padre si todo fue bien
        if ($success) {
            $success = rmdir($dirPath);
        }

        yield $success;
        return $success;
    }

    public function deleteWithProgressAsync(
        string    $path,
        ?callable $progressCallback = null
    ): \Generator
    {
        $total = 0;
        $processed = 0;

        if (is_dir($path)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            $total = iterator_count($iterator);
        } else {
            $total = 1;
        }

        $deleteItem = function ($itemPath) use ($total, &$processed, $progressCallback) {
            $result = is_dir($itemPath) ? rmdir($itemPath) : unlink($itemPath);
            $processed++;

            if ($progressCallback) {
                $progressCallback([
                    'current' => $itemPath,
                    'processed' => $processed,
                    'total' => $total,
                    'progress' => $total > 0 ? ($processed / $total) * 100 : 100
                ]);
            }

            yield Coroutine::sleep(0.001);
            return $result;
        };

        if (is_dir($path)) {
            foreach ($iterator as $item) {
                $success = yield from $deleteItem($item->getPathname());
                if (!$success) {
                    yield false;
                    return false;
                }
            }
            $success = yield from $deleteItem($path);
        } else {
            $success = yield from $deleteItem($path);
        }

        yield $success;
        return $success;
    }

    private function verboseLog($msg): void
    {
        if (defined('VERBOSE') && VERBOSE) {
            echo $msg . PHP_EOL;
        }
    }
}