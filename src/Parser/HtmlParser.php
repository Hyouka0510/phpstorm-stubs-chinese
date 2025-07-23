<?php

declare(strict_types=1);

namespace IdePhpdocChinese\PhpstormStubsChinese\Parser;

use DOMDocument;
use DOMNode;
use DOMXPath;
use IdePhpdocChinese\PhpstormStubsChinese\Exception\ParserException;

/**
 * HTML Parser for PHP Documentation
 */
final class HtmlParser
{
    private const SITE_URL = 'https://php.net/manual/zh/';
    private const LINE_BREAK = "\r\n";

    public function __construct(
        private readonly string $inputDir,
        private readonly string $outputDir
    ) {
    }

    /**
     * Parse all HTML files in the input directory
     * @throws ParserException
     */
    public function parseAll(): void
    {
        if (!is_dir($this->outputDir) && !mkdir($this->outputDir, 0755, true) && !is_dir($this->outputDir)) {
            throw new ParserException("Unable to create output directory: {$this->outputDir}");
        }

        $classes = [
            ...$this->getClasses(),
            'function' => true,
            'class'    => true,
            'reserved' => true,
        ];

        try {
            $iterator = new \DirectoryIterator($this->inputDir);
            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isDot() || !$fileInfo->isFile()) {
                    continue;
                }

                $filename = $fileInfo->getFilename();
                if (!str_ends_with($filename, '.html')) {
                    continue;
                }

                $tokens = explode('.', $filename);
                $prefix = $tokens[0];

                if (isset($classes[$prefix])) {
                    $this->parseFile($filename);
                }

                if (isset($tokens[count($tokens) - 2]) && $tokens[count($tokens) - 2] === 'constants') {
                    $this->parseConstants($filename);
                }
            }
        } catch (\Exception $e) {
            throw new ParserException("Unable to process directory: {$this->inputDir}. Error: " . $e->getMessage());
        }
    }

    /**
     * Parse a single HTML file
     * @throws ParserException
     */
    public function parseFile(string $filename): void
    {
        if ($filename != 'allowdynamicproperties.construct.html') {
            //return;
        }
        $content = $this->loadContent($filename);
        $name    = pathinfo($filename, PATHINFO_FILENAME);

        $dom     = new DOMDocument();
        $success = @$dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        if (!$success) {
            throw new ParserException("Failed to parse HTML content in file: $filename");
        }

        $xpath   = new DOMXPath($dom);
        $element = $xpath->query("//div[@id='$name']")->item(0);

        if (!$element) {
            throw new ParserException("Element with id '$name' not found in file: $filename");
        }

        // Process the element
        $this->modifyUrls($element);
        $this->handleStyle($element);

        $html = $dom->saveHTML($element);
        if ($html === false) {
            throw new ParserException("Failed to save HTML for file: $filename");
        }

        // Process and save the HTML
        $html       = $this->modifyString($html);
        $outputPath = $this->outputDir . DIRECTORY_SEPARATOR . $filename;

        if (file_put_contents($outputPath, $html) === false) {
            throw new ParserException("Failed to write file: $outputPath");
        }
    }

    /**
     * Parse constants from HTML file
     * @throws ParserException
     */
    public function parseConstants(string $filename): void
    {
        $content = $this->loadContent($filename);
        $dom     = new DOMDocument();

        $success = @$dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        if (!$success) {
            throw new ParserException("Failed to parse HTML content in file: $filename");
        }

        $xpath  = new DOMXPath($dom);
        $prefix = "constant.";
        $query  = "//*[@id[starts-with(., '" . $prefix . "')]]";
        $nodes  = $xpath->query($query);

        if ($nodes === false) {
            throw new ParserException("XPath query failed for constants in file: $filename");
        }

        foreach ($nodes as $node) {
            $codeNode = $xpath->query(".//strong/code", $node)->item(0);
            if (!$codeNode) {
                continue;
            }

            $constantName = $codeNode->textContent;
            $outFile      = 'constant.' . $constantName . '.html';

            // Skip constants with :: or \ in name
            if (str_contains($outFile, '::') || str_contains($outFile, '\\')) {
                continue;
            }

            $descriptionNode = $this->getNextElementSibling($node);
            if (!$descriptionNode) {
                continue;
            }

            $simparaNode = $xpath->query(".//*[@class='simpara']", $descriptionNode)->item(0);
            if (!$simparaNode || $simparaNode->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $this->modifyUrls($simparaNode);
            $html = $dom->saveHTML($simparaNode);

            if (!$html || trim($html) === '') {
                continue;
            }

            $html       = $this->modifyString(trim($html));
            $outputPath = $this->outputDir . DIRECTORY_SEPARATOR . $outFile;

            if (file_put_contents($outputPath, $html) === false) {
                throw new ParserException("Failed to write file: $outFile");
            }
        }
    }

    /**
     * Load content from file
     * @throws ParserException
     */
    private function loadContent(string $filename): string
    {
        $path    = $this->inputDir . DIRECTORY_SEPARATOR . $filename;
        $content = file_get_contents($path);

        if ($content === false) {
            throw new ParserException("Unable to read file: $path");
        }

        return $content;
    }

    /**
     * Get available classes from directory
     * @return array<string, bool>
     * @throws ParserException
     */
    private function getClasses(): array
    {
        $classes = [];

        try {
            $iterator = new \DirectoryIterator($this->inputDir);
            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isDot() || !$fileInfo->isFile()) {
                    continue;
                }

                $filename = $fileInfo->getFilename();
                if (str_starts_with($filename, 'class.') && str_ends_with($filename, '.html')) {
                    $className           = substr($filename, 6, -5); // Remove 'class.' and '.html'
                    $classes[$className] = true;
                }
            }
        } catch (\Exception $e) {
            throw new ParserException("Unable to scan directory for classes: {$this->inputDir}. Error: " . $e->getMessage());
        }

        return $classes;
    }

    /**
     * Get next element sibling
     */
    private function getNextElementSibling(DOMNode $node): ?DOMNode
    {
        $sibling = $node->nextSibling;
        while ($sibling && $sibling->nodeType !== XML_ELEMENT_NODE) {
            $sibling = $sibling->nextSibling;
        }
        return $sibling;
    }

    /**
     * Modify URLs in DOM element
     */
    private function modifyUrls(DOMNode $element): void
    {
        $xpath = new DOMXPath($element->ownerDocument);
        $links = $xpath->query(".//a", $element);

        if ($links === false) {
            return;
        }

        foreach ($links as $link) {
            $href = $link->getAttribute('href');

            if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
                continue;
            }

            $isKnown = str_contains($href, 'function.') || str_contains($link->textContent, '::');

            if ($isKnown) {
                $textNode = $element->ownerDocument->createTextNode('{@link ' . $link->textContent . '}');
                $link->parentNode?->replaceChild($textNode, $link);
            } else {
                $href = str_replace('.html', '.php', $href);
                $link->setAttribute('href', self::SITE_URL . $href);
            }
        }
    }

    /**
     * Handle styling for DOM element
     */
    private function handleStyle(DOMNode $element): void
    {
        $xpath = new DOMXPath($element->ownerDocument);

        // Apply styles to different elements
        $styleMap = [
            '.methodname'      => 'color:#CC7832',
            '.function strong' => 'color:#CC7832',
            '.type'            => 'color:#EAB766',
            '.parameter'       => 'color:#3A95FF',
            '.note'            => 'border:1px gray solid',
            '.phpcode'         => 'border-color:gray;background:#1E1F22;',
            '.screen'          => 'border-color:gray;background:#1E1F22;',
        ];

        foreach ($styleMap as $selector => $style) {
            $this->modifyAttribute($xpath, $element, $selector, $style, 'style');
        }

        $this->modifyTags($xpath, $element);
    }

    /**
     * Modify attributes for elements matching selector
     */
    private function modifyAttribute(DOMXPath $xpath, DOMNode $context, string $selector, string $value, string $attribute): void
    {
        $className       = ltrim($selector, '.');
        $xpathExpression = ".//*[contains(concat(' ', normalize-space(@class), ' '), ' $className ')]";
        $elements        = $xpath->query($xpathExpression, $context);

        if ($elements === false) {
            return;
        }

        foreach ($elements as $element) {
            $element->setAttribute($attribute, $value);
        }
    }

    /**
     * Modify output elements
     */
    private function modifyTags(DOMXPath $xpath, DOMNode $context): void
    {
        $this->modifyPreElements($xpath, $context);
        $this->modifyCodeElements($xpath, $context);
        $this->modifyAbbrElements($xpath, $context);
    }

    /**
     * Modify pre elements
     */
    private function modifyPreElements(DOMXPath $xpath, DOMNode $context): void
    {
        $preElements = $xpath->query('//pre', $context);
        if ($preElements === false || $preElements->length === 0) {
            return;
        }

        foreach ($preElements as $preElement) {
            $parentNode = $preElement->parentNode;
            if (!$parentNode) {
                continue;
            }

            $newElement = $context->ownerDocument->createElement('blockquote');
            $newElement->setAttribute('style', 'border:1px gray solid;');

            $preContent = $preElement->textContent;
            if (!empty($preContent)) {
                $fragment   = $context->ownerDocument->createDocumentFragment();
                $newContent = str_replace(["\n", " "], ["<br>", "&nbsp;"], $preContent);
                $newContent = "<div>$newContent</div>";
                $tempDom    = new DOMDocument();
                @$tempDom->loadHTML($newContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

                $divElement = $tempDom->getElementsByTagName('div')->item(0);
                if ($divElement) {
                    foreach ($divElement->childNodes as $node) {
                        $importedNode = $context->ownerDocument->importNode($node, true);
                        $fragment->appendChild($importedNode);
                    }
                }
                $newElement->appendChild($fragment);
            }

            $parentNode->replaceChild($newElement, $preElement);
        }
    }

    /**
     * Modify code elements
     */
    private function modifyCodeElements(DOMXPath $xpath, DOMNode $context): void
    {
        $codeElements = $xpath->query('//code', $context);
        if ($codeElements === false || $codeElements->length === 0) {
            return;
        }

        foreach ($codeElements as $codeElement) {
            $parentElement = $codeElement->parentNode;
            if (!$parentElement) {
                continue;
            }

            $isPhpCode = false;
            if ($parentElement->hasAttribute('class')) {
                $parentClass = $parentElement->getAttribute('class');
                $isPhpCode   = str_contains($parentClass, 'phpcode');
            }

            $newElement = $isPhpCode
                ? $context->ownerDocument->createElement('blockquote')
                : $context->ownerDocument->createElement('span');

            if ($isPhpCode) {
                $newElement->setAttribute('style', 'border:1px gray solid;white-space:pre-wrap');
            }

            // Copy attributes
            if ($codeElement->hasAttributes()) {
                foreach ($codeElement->attributes as $attribute) {
                    $newElement->setAttribute($attribute->name, $attribute->value);
                }
            }

            // Move child nodes
            while ($codeElement->hasChildNodes()) {
                $newElement->appendChild($codeElement->firstChild);
            }

            $parentElement->replaceChild($newElement, $codeElement);
        }
    }

    /**
     * Modify abbr elements
     */
    private function modifyAbbrElements(DOMXPath $xpath, DOMNode $context): void
    {
        $abbrElements = $xpath->query('//abbr', $context);
        if ($abbrElements === false || $abbrElements->length === 0) {
            return;
        }
        foreach ($abbrElements as $abbrElement) {
            $parentNode = $abbrElement->parentNode;
            if (!$parentNode) {
                continue;
            }
            $newElement  = $context->ownerDocument->createElement('span');
            $abbrContent = $abbrElement->textContent;
            if (!empty($abbrContent)) {
                $fragment   = $context->ownerDocument->createDocumentFragment();
                $newContent = "<div>$abbrContent</div>";
                $tempDom    = new DOMDocument();
                @$tempDom->loadHTML($newContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                $divElement = $tempDom->getElementsByTagName('div')->item(0);
                if ($divElement) {
                    foreach ($divElement->childNodes as $node) {
                        $importedNode = $context->ownerDocument->importNode($node, true);
                        $fragment->appendChild($importedNode);
                    }
                }
                $newElement->appendChild($fragment);
            }
            $parentNode->replaceChild($newElement, $abbrElement);
        }
    }

    /**
     * Modify string content
     */
    private function modifyString(string $html): string
    {
        $replacements = [
            // Prevent comment issues
            '/*'      => '//',
            '*/'      => '',
            // Reset code colors for dark theme
            '#0000BB' => '#9876AA',
            // Clean line breaks
            "\r"      => '',
            "\n"      => '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $html);
    }
}