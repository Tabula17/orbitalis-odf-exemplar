<?php

namespace Tabula17\Orbitalis\Odf\Co\Resolvers;

use Co\System;
use PhpXmlRpc\Client;
use PhpXmlRpc\Exception;
use PhpXmlRpc\PhpXmlRpc;
use PhpXmlRpc\Request;
use PhpXmlRpc\Value;
use Swoole\Coroutine;
use Tabula17\Satelles\Odf\ExporterInterface;

/**
 * Class UnoserverStreamResolver
 *
 * A class that utilizes the UnoServer to process and convert files to a specified format.
 * It implements the ExporterInterface and provides functionality to interact with a remote UnoServer
 * using XML-RPC requests.
 *  Refer to UnoServer documentation for more details.
 */
class UnoServerStreamResolver implements ExporterInterface
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
        $overwrite = array_key_exists('overwrite', $parameters) && $parameters['overwrite'];
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
        $content = yield System::readFile($file);
        // Crear la solicitud
        $request = new Request('convert', [
            new Value(null, 'null'), //inpath string/null
            new Value($content, 'base64'),// indata string/null
            new Value($out, 'string'), // outpath string/null
            new Value($fileFormat, 'string'), // convert_to string/null
            new Value(null, 'null'), // filtername string/null
            new Value([], 'array'), // filter_options array
            new Value(true, 'boolean'), // update_index
            new Value(null, 'null'), // infiltername string/null
        ]);

        $response = null;
        // Enviar la solicitud de forma asíncrona
        yield Coroutine::create(static function () use ($rpcClient, $request) {
            $response = $rpcClient->send($request);
            if ($response->faultCode()) {
                throw new Exception("Error: {$response->faultString()}");
            }
        });
        if ($overwrite) {
            unlink($file);
        }
        return ['file' => $file, 'parameters' => ['transformed' => $out]];
    }
}