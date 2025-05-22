<?php

namespace Tabula17\Orbitalis\Odf\Co\Resolvers;

use PhpXmlRpc\Exception;
use PhpXmlRpc\PhpXmlRpc;
use Swoole\Coroutine;
use PhpXmlRpc\Client;
use PhpXmlRpc\Request;
use PhpXmlRpc\Value;
use Tabula17\Satelles\Odf\ExporterInterface;

/**
 * Class UnoserverExporter
 *
 * This class is responsible for exporting documents by interacting with a server
 * using XML-RPC requests to convert files into specified output formats such as PDF.
 * It uses the Swoole Coroutine for asynchronous processing.
 * Refer to UnoServer documentation for more details.
 */
class UnoServerExporter implements ExporterInterface
{


    /**
     * @param string $exporterName
     */
    public function __construct(
        public string  $exporterName,
        public string  $serverHost,
        public string  $serverPort,
        public ?string $outputDir = null,
        public string  $fileFormat = 'pdf'
    )
    {
    }


    public function processFile(string $file, ?array $parameters = []): \Generator
    {
        yield Coroutine::sleep(0.001);
        $fileParts = pathinfo($file);
        $fileFormat = array_key_exists('fileFormat', $parameters) ? $parameters['fileFormat'] : $this->fileFormat;
        $fileName = $fileParts['filename'] . '.' . $fileFormat;
        $outputDir = array_key_exists('outputDir', $parameters) ? $parameters['outputDir'] : $this->outputDir;
        $out = ($outputDir ?? $fileParts['dirname']) . DIRECTORY_SEPARATOR . $fileName;
        $serverHost = array_key_exists('serverHost', $parameters) ? $parameters['serverHost'] : $this->serverHost;
        $serverPort = array_key_exists('serverPort', $parameters) ? $parameters['serverPort'] : $this->serverPort;
        $overwrite = array_key_exists('overwrite', $parameters) ? $parameters['overwrite'] : false;
        echo "Exporting file {$file} to {$out} using {$this->exporterName}...\n";
        // Configurar el cliente XML-RPC
        PhpXmlRpc::$xmlrpc_null_extension = true;
        $rpcClient = new Client('/', $serverHost, $serverPort);
        /**
         * Parámetros de la función convert en unoserver:
         * unoserver.convert(
         *  inpath=None,
         *  indata=None,
         *  outpath=None,
         *  convert_to=None,
         *  filtername=None,
         *  filter_options=[],
         *  update_index=True,
         *  infiltername=None,
         * ):
         */
        // Crear la solicitud
        $request = new Request('convert', [
            new Value($file, 'string'), //inpath string/null
            new Value(null, 'null'),// indata string/null
            new Value($out, 'string'), // outpath string/null
            new Value($fileFormat, 'string'), // convert_to string/null
            new Value(null, 'null'), // filtername string/null
            new Value([], 'array'), // filter_options array
            new Value(true, 'boolean'), // update_index
            new Value(null, 'null'), // infiltername string/null
        ]);

        // Enviar la solicitud de forma asíncrona
        yield Coroutine::create(static function () use ($file, $rpcClient, $request, $overwrite) {
            $response = $rpcClient->send($request);
            if ($response->faultCode()) {
                throw new Exception("Error: {$response->faultString()}");
            }
            if ($overwrite) {
                unlink($file);
            }
        });
        return ['file' => $file, 'parameters' => ['transformed' => $out]];
    }
}