# PhpStorm Stubs Chinese

[![License](https://img.shields.io/github/license/Hyouka0510/phpstorm-stubs-chinese?style=flat-square)](LICENSE)
[![Build](https://github.com/Hyouka0510/phpstorm-stubs-chinese/actions/workflows/main.yml/badge.svg)](https://github.com/Hyouka0510/phpstorm-stubs-chinese/actions/workflows/main.yml)

PhpStorm Stubs Chinese 是一个中文 PHP stub 生成器。它从 PHP 官方中文文档构建分块 XHTML，提取适合 IDE Quick Documentation 展示的类、方法、函数、常量和预定义变量说明，再把这些片段插入 JetBrains `phpstorm-stubs` 的原始 PHPDoc 中。

最终产物是 `resources/library/`：一份保持原 stub 目录与声明结构、但包含中文文档的 PHP 源码库。该目录供 [PHP Chinese Manual](https://github.com/Hyouka0510/php-chinese-manual-plugin) 插件打包使用；它不是 Composer 运行时库，也不应由 PHP 应用加载执行。

## 数据流

```text
php/doc-zh + php/doc-en + php/doc-base + php/phd
                     |
                     | configure --with-lang=zh + PhD XHTML renderer
                     v
resources/php-chunked-xhtml/        PHP 中文手册分块页面
                     |
                     | HtmlParser
                     v
resources/annotation/               每个声明一个可嵌入 HTML 片段
                     |
                     | CommentAttached + DeclarationDetector
                     |                         ^
                     |                         |
                     +------ resources/phpstorm-stubs/
                     v
resources/library/                  带中文 PHPDoc 的完整 stubs 输出
                     |
                     v
php-chinese-manual-plugin / GitHub Actions artifact
```

完整构建不是机器翻译流程。中文文本来自 PHP 官方 `doc-zh`，英文 `doc-en` 用于 PHP 文档工具生成完整手册所需的回退/基础内容；本项目负责格式转换、声明匹配与合并。

## 实现解读

### 1. 命令入口与编排

`bin/phpdoc-translator` 是 Composer 注册的可执行文件，默认创建并使用以下路径：

| 参数 | 默认目录 | 作用 |
| --- | --- | --- |
| `--raw-dir` | `resources/php-chunked-xhtml` | PhD 生成的中文手册 XHTML 输入 |
| `--annotation-dir` | `resources/annotation` | 提取后的 HTML 注释片段 |
| `--stubs-dir` | `resources/phpstorm-stubs` | JetBrains 原始 stubs 输入 |
| `--output-dir` | `resources/library` | 合并后的最终输出 |

`TranslatorService` 负责串联两个阶段：

1. `parseHtml()`：校验原始 XHTML 目录，清空并重建 annotation 目录，然后调用 `HtmlParser::parseAll()`。
2. `attachComments()`：校验 annotation 和 stubs 目录，清空 output 目录，然后调用 `CommentAttached::attachAll()`。

这意味着每次完整构建都会删除既有的 `resources/annotation/` 与 `resources/library/` 后重新生成。自定义路径时不要把 annotation/output 指向项目根目录或包含需保留文件的目录；`FileHelper` 会拒绝删除文件系统根和当前项目根，但合法的其他目标目录仍会被递归清空。

### 2. 从 PHP 手册提取注释

`src/Parser/HtmlParser.php` 使用 `DOMDocument` 和 `DOMXPath` 解析 HTML，而不是依赖正则表达式拆 HTML。`parseAll()` 会处理：

- `function.*.html`：全局函数。
- `class.*.html`：类、接口等类型页。
- `reserved.*.html`：预定义变量等保留主题。
- 以已发现类名为前缀的页面：类方法，例如 `datetime.format.html`。
- 文件名倒数第二段为 `constants` 的页面：拆出其中 id 以 `constant.` 开头的全局常量说明。

普通页面会提取与文件名同名的 `<div id="...">`。常量页会为每个合适的常量单独生成 `constant.<NAME>.html`；包含 `::` 或命名空间反斜线的常量会跳过，因为它们不是当前全局 `define()` 映射的目标。

为了适配 PhpStorm PHPDoc 渲染器，解析阶段还会：

- 将函数链接或 `Class::method` 链接转换为 `{@link ...}`。
- 将其他相对链接改写为 `https://php.net/manual/zh/...`，并把 `.html` 后缀换成 `.php`。
- 给方法名、类型、参数、提示框和代码块补充适合 IDE 的内联样式。
- 将 `<pre>` 转为带边框的 `<blockquote>`，把换行转为 `<br>`、空格转为不换行空格，同时保留语法高亮子节点。
- 将 `<code>`、`<abbr>` 转为 PhpStorm 更稳定支持的元素。
- 把文档片段中的 `/*`/`*/` 改写，避免嵌入 PHPDoc 后提前结束注释。
- 清理 CR/LF，并调整 PHP 代码默认颜色以适配深色主题。

### 3. 声明与手册文件名匹配

`src/Attached/DeclarationDetector.php` 逐行识别 stub 声明，并生成 php.net 手册键：

| Stub 声明示例 | 注释键/文件 |
| --- | --- |
| `class DateTime` | `class.datetime.html` |
| `public function __construct()`（位于 `DateTime`） | `datetime.construct.html` |
| `function str_replace(...)` | `function.str-replace.html` |
| `define('DATE_ATOM', ...)` | `constant.DATE_ATOM.html` |
| `$_COOKIE = ...` | `reserved.variables.cookies.html` |
| `namespace MongoDB\Driver; final class Manager` | `class.mongodb-driver-manager.html` |

标准化规则包括：

- 类/函数名统一转小写。
- 命名空间反斜线和下划线转换为连字符。
- 魔术方法去除开头的 `__`。
- 去除 JetBrains stub 使用的 `PS_UNRESERVE_PREFIX_` 前缀。
- 对 `$_COOKIE`、`$_ENV`、`$_FILES`、`$_GET`、`$_POST`、`$_REQUEST`、`$_SERVER`、`$_SESSION` 等预定义变量使用 PHP 手册对应别名。

检测器维护当前命名空间和类上下文。当前实现按 stub 的常见排版判断方法：函数声明前存在缩进且已经识别过类声明时，将其作为方法；因此自定义 stubs 若使用非标准的顶层缩进或单行复杂声明，可能无法正确匹配。

### 4. 合并中文与原始 PHPDoc

`src/Attached/CommentAttached.php` 递归扫描 stubs 下的 `.php` 文件，并保持相对目录写到输出目录。它收集声明前连续的 `/*`、`*`、`*/` 和 `#` 行，然后在遇到可识别声明时查找同名 annotation：

- 匹配成功且原声明已有 PHPDoc：把中文 HTML 插入 `/**` 后，保留其余 JetBrains 标签和类型信息。
- 匹配成功但没有 PHPDoc：新建一个 PHPDoc 块。
- 没有对应 annotation：原注释和声明原样输出。
- 原 PHPDoc 中的 `//php.net/manual/en` 链接会改为中文手册路径。
- 中文片段中的普通 `$` 会转义为 `\$`，不换行空格会转成 `&nbsp;`，避免 PhpStorm 展示或 PHPDoc 解析异常。

生成器不会修改 `resources/phpstorm-stubs/`；所有结果写入单独的 `resources/library/`。

## 环境要求

运行生成器：

- PHP `8.1` 或更高版本
- Composer
- PHP 扩展：`dom`、`json`、`libxml`

从官方源码生成中文 XHTML 还需要：

- Git 与可访问 GitHub 的网络
- PHP 扩展：`xmlreader`、`sqlite3`
- PHP 文档工具链：`php/phd`、`php/doc-base`、`php/doc-en`、`php/doc-zh`

运行开发质量检查还需要 `ext-zip`。PHPUnit、PHPStan 和 PHPCS 会由 Composer 的开发依赖安装。

## 安装依赖

```bash
git clone https://github.com/Hyouka0510/phpstorm-stubs-chinese.git
cd phpstorm-stubs-chinese
composer install
```

仅构建、不运行测试时可以安装生产依赖：

```bash
composer install --no-dev --prefer-dist --no-interaction --no-progress
```

## 本地完整构建

以下步骤与 `.github/workflows/main.yml` 的 `build` job 一致：

```bash
mkdir -p resources php-doc

git clone --depth 1 https://github.com/php/phd php-doc/phd
git clone --depth 1 https://github.com/php/doc-base php-doc/doc-base
git clone --depth 1 https://github.com/php/doc-en php-doc/en
git clone --depth 1 https://github.com/php/doc-zh php-doc/zh

cd php-doc
php doc-base/configure.php --with-lang=zh
php phd/render.php \
  --docbook doc-base/.manual.xml \
  --package PHP \
  --format xhtml
cp -r output/php-chunked-xhtml ../resources/
cd ..

git clone --depth 1 \
  https://github.com/JetBrains/phpstorm-stubs \
  resources/phpstorm-stubs

composer build
```

成功后最终库位于 `resources/library/`，中间提取结果位于 `resources/annotation/`。

这四个大型资源目录均被 `.gitignore` 忽略，不属于仓库源码：

```text
resources/php-chunked-xhtml/
resources/annotation/
resources/phpstorm-stubs/
resources/library/
```

当前工作区中即使存在这些目录，也只是本地或 CI 生成资源。

## CLI

```bash
# 完整流程：清空并生成 annotation，再清空并生成 library
bin/phpdoc-translator

# 仅从 XHTML 提取 annotation
bin/phpdoc-translator --parse-only

# 使用已有 annotation，仅合并 stubs
bin/phpdoc-translator --attach-only

# 自定义全部目录
bin/phpdoc-translator \
  --raw-dir /path/to/php-chunked-xhtml \
  --annotation-dir /path/to/annotation \
  --stubs-dir /path/to/phpstorm-stubs \
  --output-dir /path/to/library

bin/phpdoc-translator --help
bin/phpdoc-translator --version
```

`--parse-only` 和 `--attach-only` 应二选一；若同时传入，当前命令实现会优先执行 `--parse-only`。

## PHP API

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use IdePhpdocChinese\PhpstormStubsChinese\TranslatorService;

$translator = new TranslatorService(
    'resources/php-chunked-xhtml',
    'resources/annotation',
    'resources/phpstorm-stubs',
    'resources/library'
);

// 完整的“提取 + 合并”流程
$translator->translate();

// 或分别调用：
// $translator->parseHtml();
// $translator->attachComments();
```

## 项目结构

```text
phpstorm-stubs-chinese/
├── .github/workflows/main.yml       # 质量检查、资源构建、artifact 上传
├── bin/phpdoc-translator            # CLI 入口与参数解析
├── src/
│   ├── Attached/
│   │   ├── CommentAttached.php      # 遍历 stubs 并合并 PHPDoc
│   │   └── DeclarationDetector.php  # 声明到 php.net 文档键的映射
│   ├── Exception/                   # 分层异常类型
│   ├── Parser/HtmlParser.php        # XHTML DOM 提取与 IDE HTML 适配
│   ├── Util/FileHelper.php          # 目录校验、扫描、清理和文件操作
│   └── TranslatorService.php        # 两阶段流程编排
├── tests/
│   ├── Attached/                    # 声明映射及注释合并测试
│   └── Parser/                      # HTML/常量提取测试
├── composer.json
├── phpstan.neon
└── phpunit.xml
```

## 测试与代码质量

```bash
# PHPUnit
composer test

# PHPStan（composer 脚本使用 max level；phpstan.neon 当前配置 level 8）
composer analyse

# 检查 PSR-12（与 CI 一致）
vendor/bin/phpcs src tests --standard=PSR12

# 自动修复 src 下可修复的编码规范问题
composer format

# 校验 composer.json/composer.lock
composer validate --strict
```

现有测试重点覆盖：

- 函数、类、方法、全局常量和超全局变量的手册键映射。
- 命名空间、大小写、魔术方法及下划线标准化。
- 在保留原 PHPDoc 的同时插入中文内容。
- 函数链接转换、代码块空白/高亮保留及常量页拆分。

## GitHub Actions 构建流程

工作流在以下情况触发：

- 推送到 `main`。
- 面向 `main` 的 Pull Request。
- 每日 UTC `00:00` 定时执行。
- 手动 `workflow_dispatch`。

流水线分为两个 job：

### `quality`

使用 PHP 8.1 和开发依赖，依次运行：

1. `composer validate --strict`
2. `composer test`
3. `composer analyse`
4. `vendor/bin/phpcs src tests --standard=PSR12`

### `build`

仅在 `quality` 成功后运行：

1. 使用 PHP 8.1，安装不含开发包的 Composer 依赖。
2. 克隆 PHP 文档构建工具及中英文文档源码。
3. 通过 `configure.php --with-lang=zh` 和 PhD 生成 `php-chunked-xhtml`。
4. 克隆最新的 JetBrains `phpstorm-stubs`。
5. 执行 `composer build`，生成 `resources/library/`。
6. 使用 `actions/upload-artifact` 上传名为 `phpstorm-stubs-chinese` 的 artifact；没有产物时工作流失败。

该 artifact 的根内容就是生成后的 library，供插件仓库的发布工作流下载。因为上游仓库均使用浅克隆且没有固定 commit，每次定时构建会跟随 PHP 文档和 JetBrains stubs 的最新默认分支；如需可复现发布，应记录构建运行号或固定各上游 revision。

## 已知边界

- 只会为存在中文手册页面且能映射到声明键的 API 插入中文说明；其他声明保留原始英文 PHPDoc。
- 声明检测基于 phpstorm-stubs 的逐行格式，不是完整 PHP AST 解析器。
- 类内方法识别依赖当前类上下文和行缩进。
- 全局常量只识别 `define('NAME', ...)` 形式。
- HTML 输出针对 PhpStorm PHPDoc 渲染能力做过改写，不等同于 PHP 官网页面的完整视觉效果。
- 构建直接追踪多个上游项目，覆盖范围和结果会随上游内容变化。

## 许可证与上游

本项目代码使用 [MIT License](LICENSE)。生成产物包含来自下列上游项目的内容，分发时还应遵循各上游许可证：

- [PHP 中文手册](https://www.php.net/manual/zh/)
- [php/doc-zh](https://github.com/php/doc-zh)
- [php/doc-en](https://github.com/php/doc-en)
- [php/doc-base](https://github.com/php/doc-base)
- [php/phd](https://github.com/php/phd)
- [JetBrains/phpstorm-stubs](https://github.com/JetBrains/phpstorm-stubs)
