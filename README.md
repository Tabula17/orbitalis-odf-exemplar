# XVII: orbitalis-odf-exemplar
<p>
	<img src="https://img.shields.io/github/license/Tabula17/orbitalis-odf-exemplar?style=default&logo=opensourceinitiative&logoColor=white&color=2141ec" alt="license">
	<img src="https://img.shields.io/github/last-commit/Tabula17/orbitalis-odf-exemplar?style=default&logo=git&logoColor=white&color=2141ec" alt="last-commit">
	<img src="https://img.shields.io/github/languages/top/Tabula17/orbitalis-odf-exemplar?style=default&color=2141ec" alt="repo-top-language">
	<img src="https://img.shields.io/github/languages/count/Tabula17/orbitalis-odf-exemplar?style=default&color=2141ec" alt="repo-language-count">
</p>

Procesador asíncrono de documentos ODF (OpenDocument Format) en PHP, optimizado para alto rendimiento y operaciones concurrentes usando Swoole.

Adaptación de la librería [`satelles-odf-relatio`](https://github.com/Tabula17/satelles-odf-relatio) para un procesamiento más eficiente y moderno.

## Características principales

- **Procesamiento concurrente de documentos ODF**
- **Operaciones de archivos y ZIP asíncronas**
- **Motor de plantillas avanzado y seguro**
- **Integración con resolvers para exportación y postproceso del reporte generado**
- **Ejemplo para generación de PDF vía Unoserver**

## Requisitos

- PHP >= 8.4
- Extensiones: `zip`, `fileinfo`, `swoole` (>=5.0), `dom`, `simplexml`
- Composer
- [Swoole](https://www.swoole.com/) habilitado

## Instalación

1. Clona el repositorio y accede al directorio del proyecto.
2. Instala las dependencias:

   ```bash
   composer install
   ```

3. Asegúrate de tener las extensiones requeridas habilitadas en tu entorno PHP.

## Ejemplo de uso

Consulta el archivo `examples/draft.php` para un ejemplo completo. Fragmento básico:

```php
use Swoole\Runtime;
use Tabula17\Orbitalis\Odf\Co\Core\AsyncODFProcessor;
use Tabula17\Orbitalis\Odf\Co\Components\Template\AsyncTemplateEngine;
use Tabula17\Orbitalis\Odf\Co\Components\IO\AsyncZipManager;
use Tabula17\Orbitalis\Odf\Co\Components\IO\AsyncFileContainer;

Runtime::enableCoroutine();

$container = new AsyncFileContainer();
$templateEngine = new AsyncTemplateEngine(/* ... */);
$zipManager = new AsyncZipManager();

$processor = new AsyncODFProcessor($container, $templateEngine, $zipManager);

// Agrega trabajos y procesadores según tu lógica
// $processor->addJob(...);
// $processor->addResolver(...);

$channel = $processor->processConcurrent(/* canal o jobs */);

// Procesa resultados concurrentemente
```

## Estructura del proyecto

```
src/ — Código fuente principal (componentes, core, resolvers, funciones)
   ├── Components/
   │   ├── IO/
   │   │   ├── AsyncFileContainer.php (Gestor de archivos)
   │   │   └── AsyncZipManager.php (Gestor de archivos ZIP)
   │   ├── Job.php (Clase base para trabajos)
   │   └── Template/
   │        └── AsyncTemplateEngine.php (Motor de plantillas)
   ├── Core/
   │   └── AsyncODFProcessor.php (Clase principal)
   └── Resolvers/
       ├── UnoServerExporter.php (Exportador de PDF con Unoserver desde ruta del reporte)
       └── UnoServerStreamResolver.php (Exportador de PDF con Unoserver desde stream del archivo generado)

examples/ — Ejemplos de uso y pruebas
   │── run.php (Ejemplo básico)
   ├── media/ (Imágenes y datos de ejemplo)
   │   └── Data.php (Generador de datos aleatorios)
   ├── Saves/ 
   │   └── (Reportes generados)
   ├── templates/ (Plantillas utilizadas)
   │   ├── Report.odt (Plantilla de ejemplo)
   │   └── Report_Complex.odt (Plantilla compleja de ejemplo)
   └── tmp/ 
       └── (Archivos temporales generados)

```

## Dependencias principales

- `xvii/satelles-odf-relatio`: Procesador de plantillas ODF.

## Sugerencias de uso

- Para generación de códigos de barras y QR, instala los paquetes sugeridos en `composer.json`.
- Para envío de reportes por email o impresión directa, revisa los paquetes sugeridos.

## Licencia

MIT