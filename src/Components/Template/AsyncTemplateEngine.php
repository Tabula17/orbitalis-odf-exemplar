<?php

namespace Tabula17\Orbitalis\Odf\Co\Components\Template;


use Exception;
use Generator;
use RuntimeException;
use Swoole\Coroutine;
use Tabula17\Satelles\Odf\Exception\StrictValueConstraintException;
use Tabula17\Satelles\Odf\OdfContainerInterface;
use Tabula17\Satelles\Odf\DataRendererInterface;
use Tabula17\Satelles\Odf\Template\TemplateProcessor;
use Tabula17\Satelles\Odf\XmlMemberPath;
use Tabula17\Satelles\Xml\XmlPart;

/**
 * Maneja el procesamiento asíncrono de plantillas XML utilizando un motor de plantillas
 * y un contenedor de datos ODF.
 */
class AsyncTemplateEngine
{
    private TemplateProcessor $templateProcessor;
    private OdfContainerInterface $container;

    public function __construct(DataRendererInterface $renderer, OdfContainerInterface $container)
    {
        $this->container = $container;
        $this->templateProcessor = new TemplateProcessor($renderer, $container);
    }

    /**
     * Procesa las plantillas XML de forma asíncrona
     *
     * @param string $tempDir Directorio temporal con los archivos ODT
     * @param array $data Datos para inyectar en la plantilla
     * @return Generator Devuelve true cuando finaliza
     * @throws RuntimeException Si ocurre algún error
     */
    public function renderAsync(array $data, string $tempDir): Generator
    {
        // Primera pausa para cooperación
        yield Coroutine::sleep(0.001);

        try {
            //$this->templateProcessor->setData($data);
            $this->templateProcessor->renderer->functions->workingDir = $tempDir;
            // Procesamiento en paralelo con espera controlada
            $this->templateProcessor->processTemplate($this->container->getPart(XmlMemberPath::CONTENT), $data);
            $this->templateProcessor->processTemplate($this->container->getPart(XmlMemberPath::STYLES), $data);

            return true;
        } catch (\Throwable $e) {
            throw new RuntimeException("Template rendering failed: " . $e->getMessage().PHP_EOL.$e->getTraceAsString());
        }
    }

    /**
     * Procesa un archivo XML individual
     * @throws StrictValueConstraintException
     * @throws Exception
     */
    private function processXmlAsync(string $xmlPath, array $data): Generator
    {
        // Leer contenido del archivo (no bloqueante)
        $content = yield Coroutine::readFile($xmlPath);
        // Crear objeto XML
        $xml = new XmlPart($content);
        // Procesar plantilla (operación CPU-bound)
        $this->templateProcessor->processTemplate($xml, $data);

        // Escribir cambios (no bloqueante)
        yield Coroutine::writeFile($xmlPath, $xml->asXml());

        return true;
    }
}