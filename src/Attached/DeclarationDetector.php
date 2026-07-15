<?php

declare(strict_types=1);

namespace IdePhpdocChinese\PhpstormStubsChinese\Attached;

/**
 * Detects PHP declarations in stub files and maps them to php.net manual IDs.
 */
final class DeclarationDetector
{
    private const PS_UNRESERVE_PREFIX = 'PS_UNRESERVE_PREFIX_';

    private const VARIABLE_ALIASES = [
        'cookie' => 'cookies',
        'cookies' => 'cookies',
        'env' => 'environment',
        'environment' => 'environment',
        'files' => 'files',
        'get' => 'get',
        'globals' => 'globals',
        'post' => 'post',
        'request' => 'request',
        'server' => 'server',
        'session' => 'session',
        'argv' => 'argv',
        'argc' => 'argc',
        'httpresponseheader' => 'httpresponseheader',
        'phperrormsg' => 'phperrormsg',
    ];

    private string $namespace = '';
    private string $className = '';

    public function detect(string $line): ?string
    {
        if ($this->detectNamespace($line)) {
            return null;
        }

        $className = $this->detectClassLike($line);
        if ($className !== null) {
            $this->className = $this->qualify($className);
            return 'class.' . self::manualName($this->className);
        }

        $functionName = $this->detectFunction($line);
        if ($functionName !== null) {
            $functionKey = self::manualName($functionName);
            if ($this->isMethodLine($line)) {
                return self::manualName($this->className) . '.' . $functionKey;
            }

            return 'function.' . $functionKey;
        }

        $constantName = $this->detectGlobalConstant($line);
        if ($constantName !== null) {
            return 'constant.' . $constantName;
        }

        $variableName = $this->detectVariable($line);
        if ($variableName !== null) {
            return 'reserved.variables.' . self::manualVariableName($variableName);
        }

        return null;
    }

    private function detectNamespace(string $line): bool
    {
        if (!preg_match('/^\s*namespace\s+([^;{]+)[;{]/', $line, $matches)) {
            return false;
        }

        $this->namespace = trim($matches[1]);
        $this->className = '';
        return true;
    }

    private function detectClassLike(string $line): ?string
    {
        $pattern = '/^\s*(?:(?:abstract|final|readonly)\s+)*'
            . '(?:class|interface|trait|enum)\s+([A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)\b/u';
        if (!preg_match($pattern, $line, $matches)) {
            return null;
        }

        return $matches[1];
    }

    private function detectFunction(string $line): ?string
    {
        $pattern = '/^\s*(?:(?:abstract|final|public|protected|private|static)\s+)*'
            . 'function\s*&?\s*([A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)\s*\(/u';
        if (!preg_match($pattern, $line, $matches)) {
            return null;
        }

        return $matches[1];
    }

    private function detectGlobalConstant(string $line): ?string
    {
        if (!preg_match('/^\s*define\(\s*[\'"]([^\'"]+)[\'"]/', $line, $matches)) {
            return null;
        }

        return $matches[1];
    }

    private function detectVariable(string $line): ?string
    {
        if (!preg_match('/^\s*\$([A-Za-z_][A-Za-z0-9_]*)\s*=/', $line, $matches)) {
            return null;
        }

        return $matches[1];
    }

    private function qualify(string $name): string
    {
        if ($this->namespace === '') {
            return $name;
        }

        return $this->namespace . '\\' . $name;
    }

    private function isMethodLine(string $line): bool
    {
        return $this->className !== '' && preg_match('/^\s+/', $line) === 1;
    }

    private static function manualName(string $name): string
    {
        $name = ltrim($name, '\\');

        if (str_starts_with($name, self::PS_UNRESERVE_PREFIX)) {
            $name = substr($name, strlen(self::PS_UNRESERVE_PREFIX));
        }

        $name = preg_replace('/^__/', '', $name) ?? $name;
        $name = str_replace(['\\', '_'], '-', $name);

        return strtolower($name);
    }

    private static function manualVariableName(string $name): string
    {
        $name = strtolower(ltrim($name, '_'));
        $name = str_replace('_', '', $name);

        return self::VARIABLE_ALIASES[$name] ?? $name;
    }
}
