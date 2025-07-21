<?php

declare(strict_types=1);

namespace IdePhpdocChinese\PhpstormStubsChinese;

use Exception;
use IdePhpdocChinese\PhpstormStubsChinese\Attached\CommentAttached;
use IdePhpdocChinese\PhpstormStubsChinese\Exception\AttachedException;
use IdePhpdocChinese\PhpstormStubsChinese\Exception\FileHelperException;
use IdePhpdocChinese\PhpstormStubsChinese\Exception\ParserException;
use IdePhpdocChinese\PhpstormStubsChinese\Parser\HtmlParser;
use IdePhpdocChinese\PhpstormStubsChinese\Util\FileHelper;

/**
 * Main translator service
 */
class TranslatorService
{
    private string $rawHtmlDir;
    private string $annotationDir;
    private string $stubsDir;
    private string $outputDir;

    public function __construct(string $rawHtmlDir, string $annotationDir, string $stubsDir, string $outputDir)
    {
        $this->rawHtmlDir    = rtrim($rawHtmlDir, '/\\') . DIRECTORY_SEPARATOR;
        $this->annotationDir = rtrim($annotationDir, '/\\') . DIRECTORY_SEPARATOR;
        $this->stubsDir      = rtrim($stubsDir, '/\\') . DIRECTORY_SEPARATOR;
        $this->outputDir     = rtrim($outputDir, '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * Run the complete translation process
     * @throws Exception|FileHelperException|ParserException|AttachedException
     */
    public function translate(): void
    {
        // Step 1: Parse HTML files
        $this->parseHtml();
        // Step 2: Attach comments to stub files
        $this->attachComments();
    }

    /**
     * Parse HTML files to extract documentation
     * @throws Exception|FileHelperException|ParserException
     */
    public function parseHtml(): void
    {
        $this->removeDirectory($this->annotationDir);
        FileHelper::ensureDirectory($this->annotationDir);
        $parser = new HtmlParser($this->rawHtmlDir, $this->annotationDir);
        $parser->parseAll();
    }

    /**
     * Attach comments to PHP stub files
     * @throws Exception|FileHelperException|AttachedException
     */
    public function attachComments(): void
    {
        $this->removeDirectory($this->outputDir);
        $attached = new CommentAttached($this->annotationDir, $this->stubsDir, $this->outputDir);
        $attached->attachAll();
    }


    /**
     * Set raw HTML directory
     */
    public function setRawHtmlDir(string $rawHtmlDir): self
    {
        $this->rawHtmlDir = rtrim($rawHtmlDir, '/\\') . DIRECTORY_SEPARATOR;
        return $this;
    }

    /**
     * Set temporary directory
     */
    public function setAnnotationDir(string $annotationDir): self
    {
        $this->annotationDir = rtrim($annotationDir, '/\\') . DIRECTORY_SEPARATOR;
        return $this;
    }

    /**
     * Set stubs directory
     */
    public function setStubsDir(string $stubsDir): self
    {
        $this->stubsDir = rtrim($stubsDir, '/\\') . DIRECTORY_SEPARATOR;
        return $this;
    }

    /**
     * Set output directory
     */
    public function setOutputDir(string $outputDir): self
    {
        $this->outputDir = rtrim($outputDir, '/\\') . DIRECTORY_SEPARATOR;
        return $this;
    }

    /**
     * Get raw HTML directory
     */
    public function getRawHtmlDir(): string
    {
        return $this->rawHtmlDir;
    }

    /**
     * Get temporary directory
     */
    public function getAnnotationDir(): string
    {
        return $this->annotationDir;
    }

    /**
     * Get stubs directory
     */
    public function getStubsDir(): string
    {
        return $this->stubsDir;
    }

    /**
     * Get output directory
     */
    public function getOutputDir(): string
    {
        return $this->outputDir;
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
