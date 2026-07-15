# PhpStorm Stubs Chinese

PhpStorm Stubs Chinese 是一个用于生成中文 PHP stub 注释库的构建工具。

项目从 PHP 官方文档源码构建中文 `php-chunked-xhtml` 手册，解析其中的类、方法、函数、常量和预定义变量说明，再把对应的中文文档片段附加到 JetBrains `phpstorm-stubs` 的 PHP 声明注释中。最终产物输出到 `resources/library/`，供 [php-chinese-manual-plugin](https://github.com/Hyouka0510/php-chinese-manual-plugin) 等插件或下游构建使用。

## 功能

- 从中文 PHP 手册 XHTML 中提取可复用的注释片段。
- 将中文注释匹配并插入到 `phpstorm-stubs` 的类、方法、函数、常量和超全局变量声明前。
- 支持命名空间类名、魔术方法、下划线函数名、大小写差异等 php.net 手册文件名规则。
- 提供 CLI 入口和可编程 API。
- 提供 PHPUnit、PHPStan 和 PHPCS 质量检查。

## 环境要求

运行本项目自身代码：

- PHP `8.1` 或更高版本
- Composer
- PHP 扩展：`dom`、`json`、`libxml`

完整生成 `resources/php-chunked-xhtml/` 时还需要：

- Git
- PHP 扩展：`xmlreader`、`sqlite3`
- 可访问 GitHub，用于克隆 `php/phd`、`php/doc-base`、`php/doc-en`、`php/doc-zh` 和 `JetBrains/phpstorm-stubs`

开发和质量检查还需要：

- PHP 扩展：`zip`
- `phpunit/phpunit`
- `phpstan/phpstan`
- `squizlabs/php_codesniffer`

这些开发依赖由 `composer install` 安装。

## 安装

```bash
git clone https://github.com/Hyouka0510/phpstorm-stubs-chinese.git
cd phpstorm-stubs-chinese
composer install
```

如果只在 CI 或生产构建中运行生成流程，可使用：

```bash
composer install --no-dev --prefer-dist
```

## 资源准备

本仓库不提交大型资源目录，以下目录需要在构建前准备：

- `resources/php-chunked-xhtml/`：PHP 中文手册 XHTML。
- `resources/phpstorm-stubs/`：JetBrains `phpstorm-stubs` 源码。

CI 工作流 `.github/workflows/main.yml` 会自动准备这些资源：

```bash
mkdir -p resources php-doc

git clone https://github.com/php/phd php-doc/phd
git clone https://github.com/php/doc-base php-doc/doc-base
git clone https://github.com/php/doc-en php-doc/en
git clone https://github.com/php/doc-zh php-doc/zh

cd php-doc
php doc-base/configure.php --with-lang=zh
php phd/render.php --docbook doc-base/.manual.xml --package PHP --format xhtml
cp -r output/php-chunked-xhtml ../resources/
cd ..

git clone https://github.com/JetBrains/phpstorm-stubs resources/phpstorm-stubs
```

CI 会先执行单元测试、静态分析和编码规范检查，全部通过后才会进入资源构建和 artifact 上传阶段。

## 构建

资源准备完成后运行：

```bash
composer build
```

等价于：

```bash
bin/phpdoc-translator
```

默认输入和输出目录：

- `resources/php-chunked-xhtml/`：原始中文手册 XHTML。
- `resources/annotation/`：解析出来的中间注释片段。
- `resources/phpstorm-stubs/`：待附加中文注释的 stub 源码。
- `resources/library/`：最终生成的中文 stub 库。

## CLI 使用

```bash
# 运行完整流程：解析手册并附加注释
bin/phpdoc-translator

# 自定义资源目录
bin/phpdoc-translator --raw-dir resources/php-chunked-xhtml --stubs-dir resources/phpstorm-stubs

# 只解析中文手册到 resources/annotation
bin/phpdoc-translator --parse-only

# 只把现有 resources/annotation 附加到 stubs
bin/phpdoc-translator --attach-only

# 查看帮助
bin/phpdoc-translator --help
```

## 编程方式使用

```php
<?php

require_once 'vendor/autoload.php';

use IdePhpdocChinese\PhpstormStubsChinese\TranslatorService;

$translator = new TranslatorService(
    'resources/php-chunked-xhtml',
    'resources/annotation',
    'resources/phpstorm-stubs',
    'resources/library'
);

$translator->translate();
```

也可以单独运行两个阶段：

```php
$translator->parseHtml();
$translator->attachComments();
```

## 项目结构

```text
project/
├── bin/
│   └── phpdoc-translator
├── src/
│   ├── Attached/
│   │   ├── CommentAttached.php
│   │   └── DeclarationDetector.php
│   ├── Exception/
│   ├── Parser/
│   │   └── HtmlParser.php
│   ├── Util/
│   │   └── FileHelper.php
│   └── TranslatorService.php
├── tests/
├── resources/
│   ├── php-chunked-xhtml/    # 构建时生成或下载，不提交
│   ├── annotation/           # 构建中间产物，不提交
│   ├── phpstorm-stubs/       # 构建时克隆，不提交
│   └── library/              # 最终构建产物，不提交
├── composer.json
├── phpstan.neon
├── phpunit.xml
└── README.md
```

## 开发命令

```bash
# 单元测试
composer test

# 静态分析
composer analyse

# 代码格式修复
composer format

# 完整构建
composer build
```

当前测试覆盖重点：

- PHP stub 声明到 php.net 手册文件名的映射规则。
- 中文注释附加到类、方法、函数、常量和超全局变量。
- XHTML 片段解析、链接转换和中文编码处理。

## 产物说明

`resources/library/` 是生成后的 phpstorm-stubs 副本，其中匹配到中文手册的声明会在原有注释中插入中文文档片段。该目录体积较大，默认由 `.gitignore` 忽略，通常通过 GitHub Actions artifact 或下游插件构建流程消费。

## 鸣谢

- [PHP 中文手册](https://www.php.net/manual/zh/)
- [php/doc-zh](https://github.com/php/doc-zh)
- [php/phd](https://github.com/php/phd)
- [JetBrains/phpstorm-stubs](https://github.com/JetBrains/phpstorm-stubs)
- [php-chinese-manual-plugin](https://github.com/Hyouka0510/php-chinese-manual-plugin)

## 许可证

本项目采用 MIT 许可证，详见 `LICENSE`。
