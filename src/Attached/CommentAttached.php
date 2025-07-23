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
        $lines     = explode(self::LINE_BREAK, $content);
        $newLines  = [];
        $comment   = '';
        $className = '';

        foreach ($lines as $line) {
            $trimmedLine = str_replace(' ', '', $line);
            // Handle comments
            if ($this->isComment($trimmedLine)) {
                $comment .= $line . self::LINE_BREAK;
                continue;
            }
            // Process different types of declarations
            $newComment = $this->processDeclarations($line, $comment, $className);

            if ($newComment !== null) {
                $newLines[] = rtrim($newComment, self::LINE_BREAK);
                $comment    = '';
            }

            // Add any remaining comment
            if (!empty($comment)) {
                $newLines[] = rtrim($comment, self::LINE_BREAK);
                $comment    = '';
            }

            $newLines[] = $line;
        }

        return implode(self::LINE_BREAK, $newLines);
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
     * Check if a line is a comment
     */
    private function isComment(string $line): bool
    {
        return match (true) {
            str_starts_with($line, '/*'),
            str_starts_with($line, '*'),
            str_starts_with($line, '*/'),
            str_starts_with($line, '#') => true,
            default => false
        };
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

        if (!str_starts_with($line, $prefix)) {
            return null;
        }

        $line  = str_replace($prefix, '', $line);
        $parts = explode("'", $line);
        return $parts[0] ?? null;
    }

    /**
     * Detect variable declaration
     */
    private function detectVariable(string $line): ?string
    {
        $line = str_replace(' ', '', $line);

        if (!str_starts_with($line, '$')) {
            return null;
        }

        $line  = str_replace(['$', '_'], '', $line);
        $parts = explode('=', $line);
        return $parts[0] ?? null;
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
        return str_starts_with($function, self::PS_UNRESERVE_PREFIX)
            ? substr($function, strlen(self::PS_UNRESERVE_PREFIX))
            : $function;
    }

    /**
     * Check if this is a method declaration (inside a class)
     */
    private function isMethodDeclaration(string $line, string $className): bool
    {
        return str_starts_with($line, ' ') && !empty($className);
    }

    /**
     * Get comment for a specific token
     */
    private function getComment(string $token, string $oldComment): string
    {
        // Don't replace underscores for constants
        if (!str_starts_with($token, 'constant.')) {
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
        // Process comment Unicode and $
        $comment = $this->processCommentUnicode($comment);
        // Process old comment
        $processedOldComment = $this->processOldCommentUrls($oldComment);
        // Build new comment
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
        //echo $comment;die;
        //&#36;
        return str_replace(["\u{00A0}", "$"], ["&nbsp;", '\$'], $comment);
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
     * @throws AttachedException
     */
    private function buildNewComment(string $comment, string $oldComment): string
    {
        $pattern = '/(\/\*\*)\s*(\n|\r\n|\r)/';
        if (preg_match($pattern, $oldComment, $matches, PREG_OFFSET_CAPTURE)) {
            $matchStart              = $matches[0][1] ?? 0;
            $matchLength             = strlen($matches[0][0] ?? '');
            $insertPosition          = $matchStart + $matchLength;
            $contentToAddWithNewline = " * " . rtrim($comment) . self::LINE_BREAK . " * " . self::LINE_BREAK;
            return substr_replace($oldComment, $contentToAddWithNewline, $insertPosition, 0);
        } else {
            return "/**" . self::LINE_BREAK . " * " . rtrim($comment) . self::LINE_BREAK . " */" . self::LINE_BREAK;
        }
    }
}