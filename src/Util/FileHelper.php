<?php

declare(strict_types=1);

namespace IdePhpdocChinese\PhpstormStubsChinese\Util;

use IdePhpdocChinese\PhpstormStubsChinese\Exception\FileHelperException;

/**
 * File Helper Utility
 */
class FileHelper
{
    /**
     * Get all PHP files from a directory recursively
     * @throws FileHelperException
     */
    public static function getPhpFiles(string $directory, string $parent = ''): array
    {
        $files = [];

        $handle = opendir($directory);
        if (!$handle) {
            throw new FileHelperException("Unable to open directory: $directory");
        }

        while (($file = readdir($handle)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $fullPath = $directory . DIRECTORY_SEPARATOR . $file;
            $relativePath = $parent . '/' . $file;

            if (is_dir($fullPath)) {
                $files = array_merge($files, self::getPhpFiles($fullPath, $relativePath));
            } else {
                $files[] = $relativePath;
            }
        }

        closedir($handle);
        return $files;
    }

    /**
     * Ensure directory exists
     * @throws FileHelperException
     */
    public static function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                throw new FileHelperException("Unable to create directory: $directory");
            }
        }
    }

    /**
     * Get file extension
     */
    public static function getExtension(string $filename): string
    {
        $pathInfo = pathinfo($filename);
        return $pathInfo['extension'] ?? '';
    }

    /**
     * Get filename without extension
     */
    public static function getFilenameWithoutExtension(string $filename): string
    {
        $pathInfo = pathinfo($filename);
        return $pathInfo['filename'];
    }

    /**
     * Check if file is readable
     */
    public static function isReadable(string $filename): bool
    {
        return is_readable($filename);
    }

    /**
     * Check if file is writable
     */
    public static function isWritable(string $filename): bool
    {
        return is_writable($filename);
    }

    /**
     * Safely read file contents
     * @throws FileHelperException
     */
    public static function readFile(string $filename): string
    {
        if (!self::isReadable($filename)) {
            throw new FileHelperException("File is not readable: $filename");
        }

        $content = file_get_contents($filename);
        if ($content === false) {
            throw new FileHelperException("Unable to read file: $filename");
        }

        return $content;
    }

    /**
     * Safely write file contents
     * @throws FileHelperException
     */
    public static function writeFile(string $filename, string $content): void
    {
        $directory = dirname($filename);
        self::ensureDirectory($directory);

        if (file_put_contents($filename, $content) === false) {
            throw new FileHelperException("Unable to write file: $filename");
        }
    }
}
