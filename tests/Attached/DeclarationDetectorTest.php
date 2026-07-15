<?php

declare(strict_types=1);

namespace IdePhpdocChinese\PhpstormStubsChinese\Tests\Attached;

use IdePhpdocChinese\PhpstormStubsChinese\Attached\DeclarationDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DeclarationDetectorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function declarationProvider(): iterable
    {
        yield 'global function underscore' => [
            'function str_replace($search, $replace, $subject) {}',
            'function.str-replace',
        ];

        yield 'global constant define' => [
            "define('DATE_ATOM', 'Y-m-d');",
            'constant.DATE_ATOM',
        ];

        yield 'superglobal variable' => [
            '$_COOKIE = [];',
            'reserved.variables.cookies',
        ];
    }

    #[DataProvider('declarationProvider')]
    public function testDetectsTopLevelDocumentationKeys(string $line, string $expectedKey): void
    {
        $detector = new DeclarationDetector();

        self::assertSame($expectedKey, $detector->detect($line));
    }

    public function testNormalizesNamespacedClassAndMagicMethod(): void
    {
        $detector = new DeclarationDetector();

        self::assertNull($detector->detect('namespace MongoDB\Driver;'));
        self::assertSame('class.mongodb-driver-manager', $detector->detect('final class Manager'));
        self::assertSame('mongodb-driver-manager.construct', $detector->detect('    public function __construct() {}'));
    }

    public function testNormalizesClassCaseToManualFileName(): void
    {
        $detector = new DeclarationDetector();

        self::assertSame('class.datetime', $detector->detect('class DateTime implements DateTimeInterface'));
        self::assertSame('datetime.set-date', $detector->detect('    public function set_Date() {}'));
    }
}
