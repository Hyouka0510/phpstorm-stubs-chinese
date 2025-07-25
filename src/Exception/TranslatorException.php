<?php

declare(strict_types=1);

namespace IdePhpdocChinese\PhpstormStubsChinese\Exception;

use Exception;

/**
 * Base exception for the translator package
 */
class TranslatorException extends Exception
{
    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}