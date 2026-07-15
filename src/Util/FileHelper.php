<?php

declare(strict_types=1);

namespace IdePhpdocChinese\PhpstormStubsChinese\Util;

use IdePhpdocChinese\PhpstormStubsChinese\Exception\FileHelperException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * File Helper Utility
 */
final class FileHelper
{
    /**
     * Get all PHP files from a directory recursively
     * @return array<string>
     * @throws FileHelperException
     */
    public static function getPhpFiles(string $directory): array
    {
        self::ensureReadableDirectory($directory);

        $files = [];

        try {
            $normalizedDirectory = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR;
            $iterator            = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                if (!$file instanceof SplFileInfo || !$file->isFile()) {
                    continue;
                }

                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $relativePath = str_replace($normalizedDirectory, '', $file->getPathname());
                $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
                $files[]      = '/' . $relativePath;
            }
        } catch (\Exception $e) {
            throw new FileHelperException("Unable to scan directory: $directory. Error: " . $e->getMessage());
        }

        return $files;
    }

    /**
     * Ensure directory exists
     * @throws FileHelperException
     */
    public static function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new FileHelperException("Unable to create directory: $directory");
            }
        }
    }

    /**
     * Ensure directory exists and can be read.
     * @throws FileHelperException
     */
    public static function ensureReadableDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            throw new FileHelperException("Directory does not exist: $directory");
        }

        if (!is_readable($directory)) {
            throw new FileHelperException("Directory is not readable: $directory");
        }
    }

    /**
     * Remove a generated directory after rejecting dangerous paths.
     * @throws FileHelperException
     */
    public static function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        self::assertSafeDirectoryForRemoval($directory);

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $file) {
                if (!$file instanceof SplFileInfo) {
                    continue;
                }

                if ($file->isDir()) {
                    if (!rmdir($file->getPathname())) {
                        throw new FileHelperException("Unable to remove directory: " . $file->getPathname());
                    }
                    continue;
                }

                if (!unlink($file->getPathname())) {
                    throw new FileHelperException("Unable to delete file: " . $file->getPathname());
                }
            }
        } catch (FileHelperException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new FileHelperException("Unable to remove directory: $directory. Error: " . $e->getMessage());
        }

        if (!rmdir($directory)) {
            throw new FileHelperException("Unable to remove directory: $directory");
        }
    }

    /**
     * @throws FileHelperException
     */
    private static function assertSafeDirectoryForRemoval(string $directory): void
    {
        $realPath = realpath($directory);
        if ($realPath === false) {
            throw new FileHelperException("Unable to resolve directory: $directory");
        }

        $normalized = rtrim(str_replace('\\', '/', $realPath), '/');
        if ($normalized === '' || $normalized === '/') {
            throw new FileHelperException("Refusing to remove unsafe directory: $directory");
        }

        $projectRoot = getcwd();
        $projectRoot = $projectRoot !== false ? realpath($projectRoot) : false;

        if ($projectRoot !== false && $realPath === $projectRoot) {
            throw new FileHelperException("Refusing to remove project root: $directory");
        }
    }

    /**
     * Get file extension
     */
    public static function getExtension(string $filename): string
    {
        return pathinfo($filename, PATHINFO_EXTENSION);
    }

    /**
     * Get filename without extension
     */
    public static function getFilenameWithoutExtension(string $filename): string
    {
        return pathinfo($filename, PATHINFO_FILENAME);
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

        if (file_put_contents($filename, $content, LOCK_EX) === false) {
            throw new FileHelperException("Unable to write file: $filename");
        }
    }

    /**
     * Get file size in bytes
     * @throws FileHelperException
     */
    public static function getFileSize(string $filename): int
    {
        if (!file_exists($filename)) {
            throw new FileHelperException("File does not exist: $filename");
        }

        $size = filesize($filename);
        if ($size === false) {
            throw new FileHelperException("Unable to get file size: $filename");
        }

        return $size;
    }

    /**
     * Check if file exists and is a regular file
     */
    public static function isFile(string $filename): bool
    {
        return is_file($filename);
    }

    /**
     * Delete file safely
     * @throws FileHelperException
     */
    public static function deleteFile(string $filename): void
    {
        if (!file_exists($filename)) {
            return;
        }

        if (!unlink($filename)) {
            throw new FileHelperException("Unable to delete file: $filename");
        }
    }

    /**
     * Copy file with error handling
     * @throws FileHelperException
     */
    public static function copyFile(string $source, string $destination): void
    {
        if (!file_exists($source)) {
            throw new FileHelperException("Source file does not exist: $source");
        }

        $directory = dirname($destination);
        self::ensureDirectory($directory);

        if (!copy($source, $destination)) {
            throw new FileHelperException("Unable to copy file from $source to $destination");
        }
    }
}
