<?php

declare(strict_types=1);

namespace IdePhpdocChinese\PhpstormStubsChinese\Tests\Attached;

use IdePhpdocChinese\PhpstormStubsChinese\Attached\CommentAttached;
use PHPUnit\Framework\TestCase;

final class CommentAttachedTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/phpstorm-stubs-chinese-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/annotation', 0777, true);
        mkdir($this->root . '/stubs/date', 0777, true);
        mkdir($this->root . '/stubs/standard', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testAttachesClassMethodFunctionConstantAndVariableComments(): void
    {
        file_put_contents($this->root . '/annotation/class.datetime.html', '日期时间类');
        file_put_contents($this->root . '/annotation/datetime.construct.html', '构造日期对象');
        file_put_contents($this->root . '/annotation/function.str-replace.html', '替换字符串');
        file_put_contents($this->root . '/annotation/constant.DATE_ATOM.html', 'Atom 日期格式');
        file_put_contents($this->root . '/annotation/reserved.variables.cookies.html', 'HTTP Cookie 变量');

        file_put_contents(
            $this->root . '/stubs/date/date.php',
            implode("\n", [
                '<?php',
                '/**',
                ' * Old class comment',
                ' */',
                'class DateTime',
                '{',
                '    /**',
                '     * Old constructor comment',
                '     */',
                '    public function __construct() {}',
                '}',
                "define('DATE_ATOM', 'Y-m-d');",
                '$_COOKIE = [];',
                '',
            ])
        );

        file_put_contents(
            $this->root . '/stubs/standard/functions.php',
            "<?php\nfunction str_replace(\$search, \$replace, \$subject) {}\n"
        );

        $attached = new CommentAttached(
            $this->root . '/annotation',
            $this->root . '/stubs',
            $this->root . '/output'
        );
        $attached->attachAll();

        $dateOutput     = file_get_contents($this->root . '/output/date/date.php');
        $functionOutput = file_get_contents($this->root . '/output/standard/functions.php');

        self::assertIsString($dateOutput);
        self::assertIsString($functionOutput);
        self::assertStringContainsString('日期时间类', $dateOutput);
        self::assertStringContainsString('构造日期对象', $dateOutput);
        self::assertStringContainsString('Atom 日期格式', $dateOutput);
        self::assertStringContainsString('HTTP Cookie 变量', $dateOutput);
        self::assertStringContainsString('替换字符串', $functionOutput);
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
