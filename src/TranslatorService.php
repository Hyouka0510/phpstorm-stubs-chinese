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
final class TranslatorService
{
    public function __construct(
        private string $rawHtmlDir,
        private string $annotationDir,
        private string $stubsDir,
        private string $outputDir
    ) {
        $this->rawHtmlDir   = self::normalizeDirectory($rawHtmlDir);
        $this->annotationDir = self::normalizeDirectory($annotationDir);
        $this->stubsDir     = self::normalizeDirectory($stubsDir);
        $this->outputDir    = self::normalizeDirectory($outputDir);
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
        FileHelper::ensureReadableDirectory($this->rawHtmlDir);
        FileHelper::removeDirectory($this->annotationDir);
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
        FileHelper::ensureReadableDirectory($this->annotationDir);
        FileHelper::ensureReadableDirectory($this->stubsDir);
        FileHelper::removeDirectory($this->outputDir);

        $attached = new CommentAttached(
            $this->annotationDir,
            $this->stubsDir,
            $this->outputDir
        );
        $attached->attachAll();
    }

    /**
     * Get raw HTML directory
     */
    public function getRawHtmlDir(): string
    {
        return rtrim($this->rawHtmlDir, DIRECTORY_SEPARATOR);
    }

    /**
     * Get annotation directory
     */
    public function getAnnotationDir(): string
    {
        return rtrim($this->annotationDir, DIRECTORY_SEPARATOR);
    }

    /**
     * Get stubs directory
     */
    public function getStubsDir(): string
    {
        return rtrim($this->stubsDir, DIRECTORY_SEPARATOR);
    }

    /**
     * Get output directory
     */
    public function getOutputDir(): string
    {
        return rtrim($this->outputDir, DIRECTORY_SEPARATOR);
    }

    private static function normalizeDirectory(string $directory): string
    {
        return rtrim($directory, '/\\') . DIRECTORY_SEPARATOR;
    }
}
