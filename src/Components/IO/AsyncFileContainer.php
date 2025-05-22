<?php

namespace Tabula17\Orbitalis\Odf\Co\Components\IO;

use Co\System;
use Generator;
use RuntimeException;
use Swoole\Coroutine;
use Tabula17\Satelles\Odf\OdfContainerInterface;
use Tabula17\Satelles\Odf\XmlMemberPath;
use Tabula17\Satelles\Xml\XmlPart;

class AsyncFileContainer implements OdfContainerInterface
{

    private array $parts = [];
    private string $dirPath;
    private string $fileName;

    public function getPicturesFolder(): string
    {
        $this->validateDir();
        return XmlMemberPath::PICTURES->value;

    }

    public function loadFile(string $file): Generator
    {
        yield Coroutine::sleep(0.001);
        $this->dirPath = dirname($file);
        $this->fileName = basename($file);
        $this->validateFile();
        //yield $parts = yield Coroutine::readFile($file);
        foreach (XmlMemberPath::cases() as $member) {
            if ($member->name() === 'pictures') {
                continue;
            }
            yield $this->loadPart($member);
        }
        return $this->parts;
    }

    /**
     * @inheritDoc
     */
    public function registerFileInManifest(string $fileName, $mime): void
    {
        if ($this->getPart(XmlMemberPath::MANIFEST) !== null) {
            $this->getPart(XmlMemberPath::MANIFEST)->addChild(
                'manifest:file-entry manifest:full-path="' . XmlMemberPath::PICTURES->value . $fileName . '" manifest:media-type="' . $mime . '"'
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function addImage(string $imgPath, ?string $name = null): void
    {
        $content = System::readFile($imgPath);
        if ($content === false) {
            throw new RuntimeException("Failed to read image file: {$imgPath}");
        }
        $fileName = $name ?? basename($imgPath);
        $fiePath = $this->dirPath . DIRECTORY_SEPARATOR . $this->fileName . DIRECTORY_SEPARATOR . $this->getPicturesFolder() . $fileName;
        System::writeFile($fiePath, $content);
    }

    /**
     * @inheritDoc
     */
    public function addImages(array $imgPaths, ?array $name = null): void
    {
        foreach ($imgPaths as $key => $imgPath) {
            $fileName = $name[$key] ?? basename($imgPath);
            $this->addImage($imgPath, $fileName);
        }
    }

    public function saveFile(): Generator
    {
        yield Coroutine::sleep(0.001);
        $this->validateDir();
        foreach ($this->parts as $name => $part) {
            yield Coroutine::writeFile($this->dirPath . DIRECTORY_SEPARATOR . $this->fileName . DIRECTORY_SEPARATOR . XmlMemberPath::fromName($name), $part->asXml());
        }
        return true;
    }

    /**
     * Loads a part from the specified XML member path and returns it as a generator.
     *
     * @param XmlMemberPath $part The XML member path representing the part to load.
     * @return Generator Yields the loaded XmlPart object.
     * @throws RuntimeException If an error occurs while loading the part.
     */
    public function loadPart(XmlMemberPath $part): Generator
    {
        $this->validateDir();
        try {
            $content = yield Coroutine::readFile($this->dirPath . DIRECTORY_SEPARATOR . $this->fileName . DIRECTORY_SEPARATOR . $part->value);
            // Crear objeto XML
            $xmlPart = new XmlPart($content);
            $this->parts[$part->name()] = $xmlPart;
            // Devolver el objeto XML
            yield $xmlPart;
        } catch (\Throwable $e) {
            throw new RuntimeException("Error loading part: " . $e->getMessage(), 0, $e);
        }
    }

    public function getPart(XmlMemberPath $part): ?XmlPart
    {
        $this->validatePart($part);
        return $this->parts[$part->name()] ?? null;
    }

    private function validateFile(): void
    {
        if (!file_exists($this->dirPath . DIRECTORY_SEPARATOR . $this->fileName)) {
            throw new RuntimeException("File not found: " . $this->dirPath . DIRECTORY_SEPARATOR . $this->fileName);
        }
        if (!is_readable($this->dirPath . DIRECTORY_SEPARATOR . $this->fileName)) {
            throw new RuntimeException("File not readable: " . $this->dirPath . DIRECTORY_SEPARATOR . $this->fileName);
        }
        if (!is_writable($this->dirPath . DIRECTORY_SEPARATOR . $this->fileName)) {
            throw new RuntimeException("File not writable: " . $this->dirPath . DIRECTORY_SEPARATOR . $this->fileName);
        }
    }

    private function validateDir(): void
    {
        if (!is_dir($this->dirPath)) {
            throw new RuntimeException("Directory not found: " . $this->dirPath);
        }
        if (!is_readable($this->dirPath)) {
            throw new RuntimeException("Directory not readable: " . $this->dirPath);
        }
        if (!is_writable($this->dirPath)) {
            throw new RuntimeException("Directory not writable: " . $this->dirPath);
        }
    }

    private function validatePart(XmlMemberPath $part): void
    {
        if (!isset($this->parts[$part->name()])) {
            throw new RuntimeException("Part not found: " . $part->name());
        }
    }
}