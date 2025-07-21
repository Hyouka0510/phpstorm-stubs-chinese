# PhpstormStubs Chinese

Download the HTML [Chinese manual](https://www.php.net/download-docs.php) from the official PHP website, extract and categorize the content for core elements such as classes, functions, and constants, and save them as separate annotation
files.

Integrate the annotation content into the corresponding class, function, and constant declarations in the phpstorm-stubs project.

Generate and output the modified phpstorm-stubs project to a specified folder for use in building the [php-chinese-manual-plugin](https://github.com/Hyouka0510/php-chinese-manual-plugin).

## Features

- **HTML Parser**: Extracts PHP documentation from HTML files and converts them to structured data
- **Comment Attached**: Attaches Chinese documentation comments to phpstorm-stubs
- **PSR-4 Autoloading**: Follows modern PHP standards and best practices
- **Command Line Interface**: Easy-to-use console commands
- **Error Handling**: Comprehensive exception handling with meaningful error messages
- **PHP 7.4+ Compatible**: Supports PHP 7.4 and later versions

## Installation

Install the package via Composer:

```bash
composer require phpdoc/translator
```

Or add it to your `composer.json`:

```json
{
  "require": {
    "phpdoc/translator": "^1.0"
  }
}
```

## Usage

### Command Line Interface

The package provides a console command for easy usage:

```bash
# Run complete translation process
vendor/bin/phpdoc-translator

# Run with custom directories
vendor/bin/phpdoc-translator --raw-dir /resource/php-chunked-xhtml --stubs-dir /resource/phpstorm-stubs

# Run parser only
vendor/bin/phpdoc-translator --parse-only

# Run attached only
vendor/bin/phpdoc-translator --attach-only

# Show help
vendor/bin/phpdoc-translator --help
```

### Programmatic Usage

```php
<?php

require_once 'vendor/autoload.php';

use IdePhpdocChinese\PhpstormStubsChinese\TranslatorService;

// Initialize the translator service
$translator = new TranslatorService(
    'resource/php-chunked-xhtml',  // Raw HTML directory
    'resource/annotation',         // Temporary annotation directory
    'resource/phpstorm-stubs'      // PhpStorm stubs directory
    'resource/library'             // Build library directory
);

// Run complete translation
$translator->translate();

// Or run steps individually
$translator->parseHtml();        // Parse HTML files
$translator->attachComments();   // Attach comments to stubs
```

### Using Individual Components

```php
<?php

use IdePhpdocChinese\PhpstormStubsChinese\Parser\HtmlParser;
use IdePhpdocChinese\PhpstormStubsChinese\Attached\CommentAttached;

// Parse HTML documentation
$parser = new HtmlParser('input/php-chunked-xhtml', 'output/annotation');
$parser->parseAll();

// Attach comments to PHP stubs
$attached = new CommentAttached('input/annotation', 'output/stubs');
$attached->attachAll();
```

## Directory Structure

```
project/
├── src/
│   ├── Attached/
│   │   └── CommentAttached.php
│   ├── Exception/
│   │   └── TranslatorException.php
│   ├── Parser/
│   │   └── HtmlParser.php
│   ├── Util/
│   │   └── FileHelper.php
│   └── TranslatorService.php
├── bin/
│   └── phpdoc-translator
├── tests/
├── resource/
│   ├── php-chunked-xhtml/    # Raw HTML documentation
│   ├── annotation/           # Temporary annotation files
│   ├── phpstorm-stubs/       # PhpStorm stub files
│   └── library/              # Build library directory
├── composer.json
└── README.md
```

## Configuration

The package uses the following default directories:

- **Raw HTML Directory**: `resource/php-chunked-xhtml/`
- **Temporary Directory**: `resource/annotation/`
- **PhpStorm Stubs Directory**: `resource/phpstorm-stubs/`
- **Build Library Directory**: `resource/library/`

You can customize these directories using command line options or by configuring the `TranslatorService` programmatically.

## Requirements

- PHP 7.4 or higher
- ext-dom extension
- ext-json extension
- Composer for dependency management

## Development

### Running build

```bash
composer build
```

### Running Tests

```bash
composer test
```

## Changelog

### v1.0.0

- Initial release

## Acknowledgements

- [PHP Manual](https://www.php.net/manual/zh/)
- [Phpstorm-studs](https://github.com/JetBrains/phpstorm-stubs)
- [fw6669998/php-doc](https://github.com/fw6669998/php-doc)

## License

This project is licensed under the MIT License. See the LICENSE file for details.

## Support

For issues and questions, please use the GitHub issue tracker.