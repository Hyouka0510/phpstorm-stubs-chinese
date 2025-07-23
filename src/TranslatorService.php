<?php

declare(strict_types=1);

namespace IdePhpdocChinese\PhpstormStubsChinese;

use Exception;
use FilesystemIterator;
use IdePhpdocChinese\PhpstormStubsChinese\Attached\CommentAttached;
use IdePhpdocChinese\PhpstormStubsChinese\Exception\AttachedException;
use IdePhpdocChinese\PhpstormStubsChinese\Exception\FileHelperException;
use IdePhpdocChinese\PhpstormStubsChinese\Exception\ParserException;
use IdePhpdocChinese\PhpstormStubsChinese\Parser\HtmlParser;
use IdePhpdocChinese\PhpstormStubsChinese\Util\FileHelper;

/**
 * Main translator service
 */
final class TranslatorService
{
    public function __construct(
        private readonly string $rawHtmlDir,
        private readonly string $annotationDir,
        private readonly string $stubsDir,
        private readonly string $outputDir
    ) {
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
        $this->removeDirectory($this->getNormalizedAnnotationDir());
        FileHelper::ensureDirectory($this->getNormalizedAnnotationDir());
        $parser = new HtmlParser($this->getNormalizedRawHtmlDir(), $this->getNormalizedAnnotationDir());
        $parser->parseAll();
    }

    /**
     * Attach comments to PHP stub files
     * @throws Exception|FileHelperException|AttachedException
     */
    public function attachComments(): void
    {
        $this->removeDirectory($this->getNormalizedOutputDir());
        $attached = new CommentAttached(
            $this->getNormalizedAnnotationDir(),
            $this->getNormalizedStubsDir(),
            $this->getNormalizedOutputDir()
        );
        $attached->attachAll();
    }

    /**
     * Get raw HTML directory
     */
    public function getRawHtmlDir(): string
    {
        return $this->rawHtmlDir;
    }

    /**
     * Get annotation directory
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

    /**
     * Get normalized raw HTML directory path
     */
    private function getNormalizedRawHtmlDir(): string
    {
        return rtrim($this->rawHtmlDir, '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * Get normalized annotation directory path
     */
    private function getNormalizedAnnotationDir(): string
    {
        return rtrim($this->annotationDir, '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * Get normalized stubs directory path
     */
    private function getNormalizedStubsDir(): string
    {
        return rtrim($this->stubsDir, '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * Get normalized output directory path
     */
    private function getNormalizedOutputDir(): string
    {
        return rtrim($this->outputDir, '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * Remove directory and all its contents recursively
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($dir);
    }
}
