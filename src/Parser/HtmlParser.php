<?php

declare(strict_types=1);

namespace IdePhpdocChinese\PhpstormStubsChinese\Parser;

use DOMDocument;
use DOMXPath;
use IdePhpdocChinese\PhpstormStubsChinese\Exception\ParserException;

/**
 * HTML Parser for PHP Documentation
 */
class HtmlParser
{
    private const SITE_URL = 'https://php.net/manual/zh/';
    private const LINE_BREAK = "\r\n";

    private string $inputDir;
    private string $outputDir;

    public function __construct(string $inputDir, string $outputDir)
    {
        $this->inputDir  = rtrim($inputDir, '/\\') . DIRECTORY_SEPARATOR;
        $this->outputDir = rtrim($outputDir, '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * Parse all HTML files in the input directory
     * @throws ParserException
     */
    public function parseAll(): void
    {
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }

        $classes             = $this->getClasses();
        $classes['function'] = true;
        $classes['class']    = true;
        $classes['reserved'] = true;

        $handle = opendir($this->inputDir);
        if (!$handle) {
            throw new ParserException("Unable to open directory: {$this->inputDir}");
        }

        while (($file = readdir($handle)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            if (substr($file, -5) !== '.html') {
                continue;
            }
            $tokens = explode('.', $file);
            $prefix = $tokens[0];

            if (isset($classes[$prefix])) {
                $this->parseFile($file);
            }
            if (isset($tokens[count($tokens) - 2]) && $tokens[count($tokens) - 2] === 'constants') {
                $this->parseConstants($file);
            }
        }
        closedir($handle);
    }

    /**
     * Parse a single HTML file
     * @throws ParserException
     */
    public function parseFile(string $filename): void
    {
        $content = $this->loadContent($filename);
        $name    = substr($filename, 0, strlen($filename) - 5);

        $dom = new DOMDocument();
        @$dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath   = new DOMXPath($dom);
        $element = $xpath->query("//div[@id='$name']")->item(0);
        if (!$element) {
            throw new ParserException("Element with id '$name' not found in file: $filename");
        }
        // 替换url
        $this->modifyUrls($element);
        // 设置符合PS的样式
        $this->handleStyle($element);
        $html = $dom->saveHTML($element);
        // 压缩html代码
        $html = $this->modifyString($html);
        file_put_contents($this->outputDir . $filename, $html);
    }

    /**
     * Parse constants from HTML file
     */
    public function parseConstants(string $filename): void
    {
        $content = $this->loadContent($filename);
        $dom     = new DOMDocument();
        @$dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $xpath  = new DOMXPath($dom);
        $prefix = "constant.";
        $query  = "//*[@id[starts-with(., '" . $prefix . "')]]";
        $nodes  = $xpath->query($query);
        foreach ($nodes as $node) {
            $constantName = $xpath->query(".//strong/code", $node)->item(0)->textContent;
            $outFile      = 'constant.' . $constantName . '.html';
            if (strpos($outFile, '::') !== false) {
                continue;
            }
            if (strpos($outFile, '\\') !== false) {
                continue;
            }
            $descriptionNode = $node->nextSibling;
            while ($descriptionNode && $descriptionNode->nodeType !== XML_ELEMENT_NODE) {
                $descriptionNode = $descriptionNode->nextSibling;
            }
            if (!$descriptionNode) {
                continue;
            }
            $simparaNode = $xpath->query(".//*[@class='simpara']", $descriptionNode)->item(0);
            if ($simparaNode->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }
            // 替换url
            $this->modifyUrls($simparaNode);
            $html = $dom->saveHTML($simparaNode);
            if (!$html || trim($html) === '') {
                continue;
            }
            // 压缩html代码
            $html = $this->modifyString(trim($html));
            if (!file_put_contents($this->outputDir . $outFile, $html)) {
                throw new ParserException("Failed to write file: $outFile");
            }
        }
    }

    /**
     * Load content from file
     */
    private function loadContent(string $filename): string
    {
        $path    = $this->inputDir . $filename;
        $content = file_get_contents($path);

        if ($content === false) {
            throw new ParserException("Unable to read file: $path");
        }

        return $content;
    }

    /**
     * Get available classes from directory
     */
    private function getClasses(): array
    {
        $classes = [];
        $handle  = opendir($this->inputDir);

        if (!$handle) {
            throw new ParserException("Unable to open directory: {$this->inputDir}");
        }

        while (($file = readdir($handle)) !== false) {
            if (strpos($file, 'class.') === 0) {
                $className           = substr($file, 6, strlen($file) - 11);
                $classes[$className] = true;
            }
        }

        closedir($handle);
        return $classes;
    }

    /**
     * Modify URLs in DOM element
     */
    private function modifyUrls(\DOMNode $element): void
    {
        $xpath = new DOMXPath($element->ownerDocument);
        $links = $xpath->query(".//a", $element);

        foreach ($links as $link) {
            $href = $link->getAttribute('href');

            if (strpos($href, 'http://') === 0 || strpos($href, 'https://') === 0) {
                continue;
            }

            $known = false;
            if (strpos($href, 'function.') !== false || strpos($link->textContent, '::') !== false) {
                $known = true;
            }

            if ($known) {
                $link->parentNode->replaceChild($element->ownerDocument->createTextNode('{@link ' . $link->textContent . '}'), $link);
            } else {
                $href = str_replace('.html', '.php', $href);
                $link->setAttribute('href', self::SITE_URL . $href);
            }
        }
    }

    /**
     * Handle styling for DOM element
     */
    private function handleStyle(\DOMNode $element): void
    {
        $xpath = new DOMXPath($element->ownerDocument);
        // Method colors
        $this->modifyAttribute($xpath, $element, '.methodname', 'color:#CC7832', 'style');
        $this->modifyAttribute($xpath, $element, '.function strong', 'color:#CC7832', 'style');
        // Type colors
        $this->modifyAttribute($xpath, $element, '.type', 'color:#EAB766', 'style');
        // Parameter colors
        $this->modifyAttribute($xpath, $element, '.parameter', 'color:#3A95FF', 'style');
        // Notes
        $this->modifyAttribute($xpath, $element, '.note', 'border:1px gray solid', 'style');
        // PHP code
        $this->modifyAttribute($xpath, $element, '.phpcode', 'border-color:gray;background:#1E1F22;', 'style');
        // screen
        $this->modifyAttribute($xpath, $element, '.screen', 'border-color:gray;background:#1E1F22;', 'style');
        // Handle pre and code tags
        $this->modifyTags($xpath, $element);
    }

    /**
     * Modify attributes for elements matching selector
     */
    private function modifyAttribute(DOMXPath $xpath, \DOMNode $context, string $selector, string $value, string $attribute): void
    {
        $xpathExpression = ".//*[contains(concat(' ', normalize-space(@class), ' '), ' " . ltrim($selector, '.') . " ')]";
        $elements        = $xpath->query($xpathExpression, $context);
        foreach ($elements as $element) {
            $element->setAttribute($attribute, $value);
        }
    }

    /**
     * Modify output elements
     */
    private function modifyTags(DOMXPath $xpath, \DOMNode $context): void
    {
        $preElements = $xpath->query('//pre', $context);
        if (!empty($preElements)) {
            foreach ($preElements as $preElement) {
                $parentNode = $preElement->parentNode;
                $newElement = $context->ownerDocument->createElement('blockquote');
                $newElement->setAttribute('style', 'border:1px gray solid;');
                $preContent = $preElement->textContent;
                if (!empty($preContent)) {
                    $fragment   = $context->ownerDocument->createDocumentFragment();
                    $newContent = str_replace("\n", "<br>", $preContent);
                    $newContent = str_replace(" ", "&nbsp;", $newContent);
                    $newContent = "<div>" . $newContent . "</div>";
                    $tempDom    = new DOMDocument();
                    @$tempDom->loadHTML($newContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                    foreach ($tempDom->getElementsByTagName('div')->item(0)->childNodes as $node) {
                        $importedNode = $context->ownerDocument->importNode($node, true);
                        $fragment->appendChild($importedNode);
                    }
                    $newElement->appendChild($fragment);
                }
                $parentNode->replaceChild($newElement, $preElement);
            }
        }
        $codeElements = $xpath->query('//code', $context);
        if (!empty($codeElements)) {
            foreach ($codeElements as $codeElement) {
                $parentElement      = $codeElement->parentNode;
                $isPhpOrExampleCode = false;
                // 检查父级元素是否存在且有 class 属性
                if ($parentElement && $parentElement->hasAttribute('class')) {
                    $parentClass = $parentElement->getAttribute('class');
                    // 使用 preg_match 检查 class 属性是否包含 'phpcode' 或 'examplescode'
                    // \b 是单词边界，确保匹配的是整个单词
                    if (preg_match('/\bphpcode\b/', $parentClass)) {
                        $isPhpOrExampleCode = true;
                    }
                }
                $newElement = null;
                if ($isPhpOrExampleCode) {
                    $newElement = $context->ownerDocument->createElement('blockquote');
                    // 新增样式 "border:1px gray solid"
                    $newElement->setAttribute('style', 'border:1px gray solid;white-space:pre-wrap');
                } else {
                    $newElement = $context->ownerDocument->createElement('span');
                }
                if ($codeElement->hasAttributes()) {
                    foreach ($codeElement->attributes as $attribute) {
                        $newElement->setAttribute($attribute->name, $attribute->value);
                    }
                }
                while ($codeElement->hasChildNodes()) {
                    $newElement->appendChild($codeElement->firstChild);
                }
                if ($codeElement->parentNode) {
                    $codeElement->parentNode->replaceChild($newElement, $codeElement);
                }
            }
        }
    }

    /**
     * Modify string content
     */
    private function modifyString(string $html): string
    {
        // Prevent comment issues
        $html = str_replace('/*', '//', $html);
        $html = str_replace('*/', '', $html);

        // Reset code colors for dark theme
        $html = str_replace('#0000BB', '#9876AA', $html);

        // Clean line breaks
        $html = str_replace("\r", '', $html);
        return str_replace("\n", '', $html);
    }
}