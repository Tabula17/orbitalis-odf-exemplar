#!/usr/bin/env php
<?php
require __DIR__ . '/../vendor/autoload.php';
include __DIR__ . '/../examples/media/Data.php';
const VERBOSE = true;
use Random\RandomException;
use Swoole\Coroutine\Channel;
use Swoole\Runtime;
use Swoole\Coroutine;
use Tabula17\Orbitalis\Odf\Co\Components\IO\AsyncFileContainer;
use Tabula17\Orbitalis\Odf\Co\Components\IO\AsyncZipManager;
use Tabula17\Orbitalis\Odf\Co\Components\Job;
use Tabula17\Orbitalis\Odf\Co\Components\Template\AsyncTemplateEngine;
use Tabula17\Orbitalis\Odf\Co\Core\AsyncODFProcessor;
use Tabula17\Orbitalis\Odf\Co\Resolvers\UnoServerExporter;
use Tabula17\Orbitalis\Odf\Co\Resolvers\UnoServerStreamResolver;
use Tabula17\Satelles\Odf\Functions\Advanced;
use Tabula17\Satelles\Odf\Renderer\DataRenderer;

Runtime::enableCoroutine();


$dir = realpath(__DIR__);
$container = new AsyncFileContainer();

$functions = new Advanced($dir);
$renderer = new DataRenderer(null, $functions);
$templateEngine = new AsyncTemplateEngine($renderer, $container);
$zipManager = new AsyncZipManager();

$processor = new AsyncODFProcessor(
    $container, $templateEngine, $zipManager
);
$init = microtime(true);
echo 'Mocking data-> ' . $init . PHP_EOL;
// Procesamiento concurrente
$jobs = [];
$batchId = uniqid('batch_', true);
$reports = [
    '/templates/Report.odt',
    '/templates/Report_Complex.odt',
];
$reportData = [
    'Report.odt' => 'simple',
    'Report_Complex.odt' => 'complex',
];
$resolvers = [
    'unoServerPdf' => new UnoServerExporter('unoServerPdf', '127.0.0.1', 2003),
    'streamPdf' => new UnoServerStreamResolver('streamPdf', '127.0.0.1', 2003),
];
foreach ($resolvers as $name => $resolver) {
    $processor->addResolver($resolver->exporterName, $resolver);
}

$jobsLimit = 15;
echo 'Data for ' . $jobsLimit . '  Jobs'.PHP_EOL;

for ($i = 1; $i <= $jobsLimit; $i++) {
    try {
        $idx = random_int(0, count($reports) - 1);
        $report = $reports[$idx];
        $reportName = basename($report);
        $reportType = $reportData[$reportName];
        $data = $reportType === 'simple' ? random_data(dirname(__DIR__) . '/vendor/xvii/satelles-odf-relatio/Examples/Media') : random_data_complex();
        $baseId = substr(md5(random_bytes(8)), 0, 12);
        $processor->addJob(new Job(
            id: $batchId . '#' . $i,
            template: $dir . $report,
            workingDir: $dir . '/tmp/' . $baseId . DIRECTORY_SEPARATOR,
            outputDir: $dir . '/saves',
            outputName: 'Report_' . $baseId,
            data: $data,
            success: null,
            actions: ['streamPdf'],
            createdAt: date_create()->format('c'),
            parameters: [
                'unoServerPdf' => [
                    'format' => 'pdf',
                    'overwrite' => true,
                    'outputDir' => $dir . '/saves/fromFile'
                ],
                'streamPdf' => [
                    'format' => 'pdf',
                    'overwrite' => false,
                    'outputDir' => $dir . '/saves/stream'
                ]
            ]
        ));
    } catch (RandomException $e) {

    }
}
echo 'Elapsed -> ' . (microtime(true) - $init) . PHP_EOL . PHP_EOL;
$start = microtime(true);
echo 'CO START TIME-> ' . $start . PHP_EOL;

$channel = new Channel(count($processor->getJobs()));
$channel = $processor->processConcurrent($channel);
Coroutine\run(function () use ($channel) {
    // while (true) {
    $queue = $channel->stats()['queue_num'];
    while ($queue > 0) {
        /**
         * @var Job $result
         */
        $result = $channel->pop();
        // echo "co ->" . var_export($channel->stats(), true) . PHP_EOL;
        if ($result->success === true) {
            $res = var_export($result->results, true);
            echo "Documento {$result->id} procesado: {$res}\n";
            echo "Creado en {$result->createdAt} y terminado en {$result->completedAt}\n";
        } else {
            echo "Error en {$result->id}: {$result->errorMessage}\n";
        }
        $queue = $channel->stats()['queue_num'];
    }
});
$elapsed = (microtime(true) - $start);
echo 'CO ELAPSED TIME-> ' . $elapsed . PHP_EOL . PHP_EOL;