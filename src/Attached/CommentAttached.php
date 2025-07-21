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
    private const COMMENT_PREFIXES = ['/*', '*', '*/', '#'];
    private const PS_UNRESERVE_PREFIX = 'PS_UNRESERVE_PREFIX_';
    private const MANUAL_URL_PATTERN = '/(\/\/php\.net\/manual\/en)/';
    private const MANUAL_URL_REPLACEMENT = '//php.net/manual/zh';
    private const EXTENSION_PHP = 'php';
    private const INVALID_DIRECTORIES = ['\\', '/'];

    private string $documentDir;
    private string $stubsDir;
    private string $outputDir;

    public function __construct(string $documentDir, string $stubsDir, string $outputDir)
    {
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
        $handle = $this->openFileForReading($filePath);
        try {
            $newContent = $this->processFile($handle);
            $this->saveProcessedFile($filename, $newContent);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Process the entire file and return new content with attached comments
     *
     * @param resource $handle File handle
     * @return string Processed file content
     */
    private function processFile($handle): string
    {
        $newContent = '';
        $comment    = '';
        $className  = '';
        while (($line = fgets($handle)) !== false) {
            $trimmedLine = str_replace(' ', '', $line);
            // Handle comments
            if ($this->isComment($trimmedLine)) {
                $comment .= $line;
                continue;
            }
            // Process different types of declarations
            $newComment = $this->processDeclarations($line, $comment, $className);

            if ($newComment !== null) {
                $newContent .= $newComment;
                $comment    = '';
            }

            // Add any remaining comment
            if (!empty($comment)) {
                $newContent .= $comment;
                $comment    = '';
            }

            $newContent .= $line;
        }

        return $newContent;
    }

    /**
     * Process different types of declarations (class, function, constant, variable)
     *
     * @param string $line Current line
     * @param string $comment Current comment block
     * @param string &$className Current class name (passed by reference)
     * @return string|null New comment or null if no declaration found
     */
    private function processDeclarations(string $line, string $comment, string &$className): ?string
    {
        // Handle class declarations
        if ($detectedClass = $this->detectClass($line)) {
            $className = $detectedClass;
            return $this->getComment("class.{$className}", $comment);
        }

        // Handle function declarations
        if ($function = $this->detectFunction($line)) {
            $function    = $this->normalizeFunction($function);
            $isMethod    = $this->isMethodDeclaration($line, $className);
            $functionKey = $isMethod ? "{$className}.{$function}" : "function.{$function}";
            return $this->getComment($functionKey, $comment);
        }

        // Handle constant declarations
        if ($constant = $this->detectConstant($line)) {
            return $this->getComment("constant.{$constant}", $comment);
        }

        // Handle variable declarations
        if ($variable = $this->detectVariable($line)) {
            return $this->getComment("reserved.variables.{$variable}", $comment);
        }

        return null;
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
        $dirname = $fileInfo['dirname'] ?? '';
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
     * Open file for reading
     *
     * @param string $filePath
     * @return resource
     * @throws AttachedException
     */
    private function openFileForReading(string $filePath)
    {
        $handle = fopen($filePath, 'r');

        if (!$handle) {
            throw new AttachedException("Unable to open file: {$filePath}");
        }

        return $handle;
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
     * Check if a line is a comment
     */
    private function isComment(string $line): bool
    {
        foreach (self::COMMENT_PREFIXES as $prefix) {
            if (strpos($line, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect class declaration
     */
    private function detectClass(string $line): ?string
    {
        return $this->detectElement($line, 'class');
    }

    /**
     * Detect function declaration
     */
    private function detectFunction(string $line): ?string
    {
        return $this->detectElement($line, 'function');
    }

    /**
     * Detect constant declaration
     */
    private function detectConstant(string $line): ?string
    {
        $line   = str_replace(' ', '', $line);
        $prefix = "define('";

        if (strpos($line, $prefix) === 0) {
            $line  = str_replace($prefix, '', $line);
            $parts = explode("'", $line);
            return $parts[0] ?? null;
        }

        return null;
    }

    /**
     * Detect variable declaration
     */
    private function detectVariable(string $line): ?string
    {
        $line = str_replace(' ', '', $line);

        if (strpos($line, '$') === 0) {
            $line  = str_replace(['$', '_'], '', $line);
            $parts = explode('=', $line);
            return $parts[0] ?? null;
        }

        return null;
    }

    /**
     * Detect elements (class, function, etc.)
     */
    private function detectElement(string $line, string $type): ?string
    {
        $tokens = explode(' ', $line);

        for ($i = 0, $count = count($tokens); $i < $count; $i++) {
            if ($tokens[$i] === $type && isset($tokens[$i + 1])) {
                $name = trim($tokens[$i + 1]);
                // Remove function parameters if present
                if (($pos = strpos($name, '(')) !== false) {
                    $name = substr($name, 0, $pos);
                }
                return $name;
            }
        }

        return null;
    }

    /**
     * Normalize function name by removing PS_UNRESERVE_PREFIX
     */
    private function normalizeFunction(string $function): string
    {
        if (strpos($function, self::PS_UNRESERVE_PREFIX) === 0) {
            return substr($function, strlen(self::PS_UNRESERVE_PREFIX));
        }
        return $function;
    }

    /**
     * Check if this is a method declaration (inside a class)
     */
    private function isMethodDeclaration(string $line, string $className): bool
    {
        return strpos($line, ' ') === 0 && !empty($className);
    }

    /**
     * Get comment for a specific token
     */
    private function getComment(string $token, string $oldComment): string
    {
        // Don't replace underscores for constants
        if (strpos($token, 'constant.') !== 0) {
            $token = str_replace('_', '-', $token);
        }

        $file = $this->documentDir . $token . '.html';

        if (!file_exists($file)) {
            return $oldComment;
        }

        $comment = file_get_contents($file);
        if ($comment === false) {
            return $oldComment;
        }

        // Process old comment
        $processedOldComment = $this->processOldComment($oldComment);

        // Build new comment
        return $this->buildNewComment($comment, $processedOldComment);
    }

    /**
     * Process existing comment by removing markers and updating URLs
     */
    private function processOldComment(string $oldComment): string
    {
        if (empty($oldComment)) {
            return '';
        }

        // Remove /** and */
        $oldComment = preg_replace('/(\/\*|\*\/)/', '', $oldComment);

        // Replace English manual links with Chinese
        return preg_replace(self::MANUAL_URL_PATTERN, self::MANUAL_URL_REPLACEMENT, $oldComment);
    }

    /**
     * Build new comment block
     */
    private function buildNewComment(string $comment, string $oldComment): string
    {
        return '/**' . self::LINE_BREAK . '* ' . $comment . self::LINE_BREAK . $oldComment . '*/' . self::LINE_BREAK;
    }
}
