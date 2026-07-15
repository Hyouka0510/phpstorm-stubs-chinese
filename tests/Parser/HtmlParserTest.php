<?php

declare(strict_types=1);

namespace IdePhpdocChinese\PhpstormStubsChinese\Tests\Parser;

use IdePhpdocChinese\PhpstormStubsChinese\Parser\HtmlParser;
use PHPUnit\Framework\TestCase;

final class HtmlParserTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/phpstorm-stubs-chinese-parser-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/input', 0777, true);
        mkdir($this->root . '/output', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testParsesFunctionDocumentationFragment(): void
    {
        $html = '<div id="function.str-replace"><p class="para">替换 '
            . '<a href="function.count.html">count()</a></p><pre>$a = 1;</pre></div>';

        file_put_contents(
            $this->root . '/input/function.str-replace.html',
            $html
        );

        $parser = new HtmlParser($this->root . '/input', $this->root . '/output');
        $parser->parseFile('function.str-replace.html');

        $output = file_get_contents($this->root . '/output/function.str-replace.html');

        self::assertIsString($output);
        self::assertStringContainsString('替换', $output);
        self::assertStringContainsString('{@link count()}', $output);
        self::assertStringContainsString("\u{00A0}", $output);
    }

    public function testParsesConstantsFromDocumentationPage(): void
    {
        $html = '<div id="constant.DATE_ATOM"><strong><code>DATE_ATOM</code></strong></div>'
            . '<p><span class="simpara">Atom 格式</span></p>';

        file_put_contents(
            $this->root . '/input/date.constants.html',
            $html
        );

        $parser = new HtmlParser($this->root . '/input', $this->root . '/output');
        $parser->parseConstants('date.constants.html');

        $output = file_get_contents($this->root . '/output/constant.DATE_ATOM.html');

        self::assertIsString($output);
        self::assertStringContainsString('Atom 格式', $output);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }

            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($directory);
    }
}
