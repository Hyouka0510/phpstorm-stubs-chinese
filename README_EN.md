# PhpStorm Stubs Chinese

PhpStorm Stubs Chinese is a build tool for generating PHP stub files with Chinese documentation comments.

The project builds the Chinese PHP manual as `php-chunked-xhtml`, extracts documentation fragments for classes, methods, functions, constants, and predefined variables, then inserts those fragments into the matching declarations from JetBrains `phpstorm-stubs`. The generated library is written to `resources/library/` and can be consumed by [php-chinese-manual-plugin](https://github.com/Hyouka0510/php-chinese-manual-plugin) or other downstream build pipelines.

## Features

- Extract reusable annotation fragments from the Chinese PHP manual XHTML.
- Attach Chinese comments to classes, methods, functions, constants, and superglobals in `phpstorm-stubs`.
- Handle php.net manual filename conventions such as namespaces, magic methods, underscores, and case normalization.
- Provide both a CLI entry point and a programmatic API.
- Include PHPUnit, PHPStan, and PHPCS quality checks.

## Requirements

To run this project:

- PHP `8.1` or later
- Composer
- PHP extensions: `dom`, `json`, `libxml`

To generate `resources/php-chunked-xhtml/` from the PHP documentation sources:

- Git
- PHP extensions: `xmlreader`, `sqlite3`
- GitHub access for cloning `php/phd`, `php/doc-base`, `php/doc-en`, `php/doc-zh`, and `JetBrains/phpstorm-stubs`

For development and quality checks:

- PHP extension: `zip`
- `phpunit/phpunit`
- `phpstan/phpstan`
- `squizlabs/php_codesniffer`

Development dependencies are installed by `composer install`.

## Installation

```bash
git clone https://github.com/Hyouka0510/phpstorm-stubs-chinese.git
cd phpstorm-stubs-chinese
composer install
```

For CI or production-style builds:

```bash
composer install --no-dev --prefer-dist
```

## Preparing Resources

Large resource directories are not committed to the repository. Prepare these before running a build:

- `resources/php-chunked-xhtml/`: Chinese PHP manual XHTML.
- `resources/phpstorm-stubs/`: JetBrains `phpstorm-stubs` source tree.

The GitHub Actions workflow in `.github/workflows/main.yml` prepares them with:

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

CI runs unit tests, static analysis, and coding standard checks before building resources and uploading the artifact.

## Build

After resources are ready, run:

```bash
composer build
```

This is equivalent to:

```bash
bin/phpdoc-translator
```

Default directories:

- `resources/php-chunked-xhtml/`: source Chinese manual XHTML.
- `resources/annotation/`: intermediate extracted annotation fragments.
- `resources/phpstorm-stubs/`: source stubs to annotate.
- `resources/library/`: generated Chinese stub library.

## CLI Usage

```bash
# Run the full process: parse manual and attach comments
bin/phpdoc-translator

# Use custom resource directories
bin/phpdoc-translator --raw-dir resources/php-chunked-xhtml --stubs-dir resources/phpstorm-stubs

# Parse the manual only
bin/phpdoc-translator --parse-only

# Attach existing annotation fragments only
bin/phpdoc-translator --attach-only

# Show help
bin/phpdoc-translator --help
```

## Programmatic Usage

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

You can also run each stage separately:

```php
$translator->parseHtml();
$translator->attachComments();
```

## Project Structure

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
│   ├── php-chunked-xhtml/    # generated or downloaded during build, not committed
│   ├── annotation/           # intermediate build output, not committed
│   ├── phpstorm-stubs/       # cloned during build, not committed
│   └── library/              # final build output, not committed
├── composer.json
├── phpstan.neon
├── phpunit.xml
└── README.md
```

## Development Commands

```bash
# Unit tests
composer test

# Static analysis
composer analyse

# Format code
composer format

# Full build
composer build
```

Current tests cover:

- Mapping PHP stub declarations to php.net manual filenames.
- Attaching Chinese comments to classes, methods, functions, constants, and superglobals.
- XHTML fragment parsing, link conversion, and Chinese encoding handling.

## Build Output

`resources/library/` is a generated copy of phpstorm-stubs with Chinese documentation fragments inserted into matching declarations. It is large, ignored by default, and usually consumed as a GitHub Actions artifact or by a downstream plugin build.

## Acknowledgements

- [PHP Manual Chinese](https://www.php.net/manual/zh/)
- [php/doc-zh](https://github.com/php/doc-zh)
- [php/phd](https://github.com/php/phd)
- [JetBrains/phpstorm-stubs](https://github.com/JetBrains/phpstorm-stubs)
- [php-chinese-manual-plugin](https://github.com/Hyouka0510/php-chinese-manual-plugin)

## License

This project is licensed under the MIT License. See `LICENSE` for details.
