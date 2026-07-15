<?php

declare(strict_types=1);

namespace IdePhpdocChinese\PhpstormStubsChinese\Attached;

use IdePhpdocChinese\PhpstormStubsChinese\Exception\AttachedException;
use IdePhpdocChinese\PhpstormStubsChinese\Exception\FileHelperException;
use IdePhpdocChinese\PhpstormStubsChinese\Util\FileHelper;

/**
 * Comment Attached for PHP Documentation
 *
 * This class handles attaching Chinese documentation comments to PHP stub files
 */
final class CommentAttached
{
    private const LINE_BREAK = "\n";
    private const MANUAL_URL_PATTERN = '/(\/\/php\.net\/manual\/en)/';
    private const MANUAL_URL_REPLACEMENT = '//php.net/manual/zh';
    private const EXTENSION_PHP = 'php';
    private const INVALID_DIRECTORIES = ['\\', '/'];

    public function __construct(
        private string $documentDir,
        private string $stubsDir,
        private string $outputDir
    ) {
        $this->documentDir = $this->normalizePath($documentDir);
        $this->stubsDir    = $this->normalizePath($stubsDir);
        $this->outputDir   = $this->normalizePath($outputDir);
    }

    /**
     * Attach comments to all PHP files in the stubs directory
     *
     * @throws FileHelperException|AttachedException
     */
    public function attachAll(): void
    {
        $files = FileHelper::getPhpFiles($this->stubsDir);
        foreach ($files as $file) {
            if ($this->isValidPhpFile($file)) {
                $this->attachToFile($file);
            }
        }
    }

    /**
     * Attach comments to a single PHP file
     *
     * @param string $filename The filename relative to stubs directory
     * @throws AttachedException
     */
    public function attachToFile(string $filename): void
    {
        $filePath = $this->stubsDir . $filename;
        $this->validateFileExists($filePath);

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new AttachedException("Unable to read file: {$filePath}");
        }

        $newContent = $this->processFileContent($content);
        $this->saveProcessedFile($filename, $newContent);
    }

    /**
     * Process the entire file content and return new content with attached comments
     */
    private function processFileContent(string $content): string
    {
        $lines    = explode(self::LINE_BREAK, $content);
        $newLines = [];
        $comment  = '';
        $detector = new DeclarationDetector();

        foreach ($lines as $line) {
            if ($this->isCommentLine($line)) {
                $comment .= $line . self::LINE_BREAK;
                continue;
            }

            $documentationKey = $detector->detect($line);
            $newComment       = $documentationKey !== null ? $this->getComment($documentationKey, $comment) : null;

            if ($newComment !== null) {
                $newLines[] = rtrim($newComment, self::LINE_BREAK);
                $comment    = '';
            }

            if (!empty($comment)) {
                $newLines[] = rtrim($comment, self::LINE_BREAK);
                $comment    = '';
            }

            $newLines[] = $line;
        }

        return implode(self::LINE_BREAK, $newLines);
    }

    /**
     * Normalize directory path with proper separator
     */
    private function normalizePath(string $path): string
    {
        return rtrim($path, '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * Check if file is a valid PHP file
     */
    private function isValidPhpFile(string $file): bool
    {
        $fileInfo  = pathinfo($file);
        $extension = $fileInfo['extension'] ?? '';
        $dirname   = $fileInfo['dirname'] ?? '';
        return $extension === self::EXTENSION_PHP && !in_array($dirname, self::INVALID_DIRECTORIES, true);
    }

    /**
     * Validate that file exists
     *
     * @throws AttachedException
     */
    private function validateFileExists(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new AttachedException("File not found: {$filePath}");
        }
    }

    /**
     * Save processed file to output directory
     *
     * @throws AttachedException
     */
    private function saveProcessedFile(string $filename, string $content): void
    {
        $saveFilePath = $this->outputDir . $filename;
        $this->ensureDirectoryExists($saveFilePath);

        if (file_put_contents($saveFilePath, $content) === false) {
            throw new AttachedException("Failed to write file: {$saveFilePath}");
        }
    }

    /**
     * Ensure directory exists for file path
     *
     * @throws AttachedException
     */
    private function ensureDirectoryExists(string $filePath): void
    {
        $dir = pathinfo($filePath, PATHINFO_DIRNAME);

        if (!file_exists($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new AttachedException("Failed to create directory: {$dir}");
        }
    }

    /**
     * Check if a line belongs to a PHPDoc or stub comment block.
     */
    private function isCommentLine(string $line): bool
    {
        $line = ltrim($line);

        return match (true) {
            str_starts_with($line, '/*'),
            str_starts_with($line, '*'),
            str_starts_with($line, '*/'),
            str_starts_with($line, '#') => true,
            default => false
        };
    }

    /**
     * Get comment for a specific manual token.
     */
    private function getComment(string $token, string $oldComment): string
    {
        $file = $this->documentDir . $token . '.html';

        if (!file_exists($file)) {
            return $oldComment;
        }

        $comment = file_get_contents($file);
        if ($comment === false) {
            return $oldComment;
        }
        $comment = $this->processCommentUnicode($comment);

        $processedOldComment = $this->processOldCommentUrls($oldComment);

        return $this->buildNewComment($comment, $processedOldComment);
    }

    /**
     * Process comment Unicode and $
     */
    private function processCommentUnicode(string $comment): string
    {
        if (empty($comment)) {
            return '';
        }

        return str_replace(["\u{00A0}", '$'], ["&nbsp;", '\$'], $comment);
    }

    /**
     * Process existing comment by updating URLs
     */
    private function processOldCommentUrls(string $oldComment): string
    {
        if (empty($oldComment)) {
            return '';
        }
        // Replace English manual links with Chinese
        return preg_replace(self::MANUAL_URL_PATTERN, self::MANUAL_URL_REPLACEMENT, $oldComment) ?? $oldComment;
    }

    /**
     * Build new comment block
     */
    private function buildNewComment(string $comment, string $oldComment): string
    {
        $pattern = '/(\/\*\*)\s*(\n|\r\n|\r)/';
        if (preg_match($pattern, $oldComment, $matches, PREG_OFFSET_CAPTURE)) {
            $matchStart              = $matches[0][1];
            $matchLength             = strlen($matches[0][0]);
            $insertPosition          = $matchStart + $matchLength;
            $contentToAddWithNewline = " * " . rtrim($comment) . self::LINE_BREAK . " * " . self::LINE_BREAK;
            return substr_replace($oldComment, $contentToAddWithNewline, $insertPosition, 0);
        }

        return "/**" . self::LINE_BREAK . " * " . rtrim($comment) . self::LINE_BREAK . " */" . self::LINE_BREAK;
    }
}
