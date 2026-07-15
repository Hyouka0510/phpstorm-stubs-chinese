<?php

declare(strict_types=1);

namespace IdePhpdocChinese\PhpstormStubsChinese\Parser;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use IdePhpdocChinese\PhpstormStubsChinese\Exception\ParserException;

/**
 * HTML Parser for PHP Documentation
 */
final class HtmlParser
{
    private const SITE_URL = 'https://php.net/manual/zh/';

    public function __construct(private readonly string $inputDir, private readonly string $outputDir)
    {
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
        $content = $this->loadContent($filename);
        $name    = pathinfo($filename, PATHINFO_FILENAME);

        $dom = $this->loadHtmlDocument($content, $filename);

        $xpath   = new DOMXPath($dom);
        $element = $this->queryOne($xpath, "//div[@id='$name']");

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
        $dom = $this->loadHtmlDocument($content, $filename);

        $xpath  = new DOMXPath($dom);
        $prefix = "constant.";
        $query  = "//*[@id[starts-with(., '" . $prefix . "')]]";
        $nodes  = $this->query($xpath, $query);

        foreach ($nodes as $node) {
            $codeNode = $this->queryOne($xpath, ".//strong/code", $node);
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

            $simparaNode = $this->queryOne($xpath, ".//*[@class='simpara']", $descriptionNode);
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
     * @throws ParserException
     */
    private function loadHtmlDocument(string $content, string $filename): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');

        $previous = libxml_use_internal_errors(true);
        $success  = $dom->loadHTML(
            '<?xml encoding="UTF-8">' . $content,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        $errors   = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$success) {
            $message = $errors !== [] ? trim($errors[0]->message) : 'unknown parser error';
            throw new ParserException("Failed to parse HTML content in file: $filename. Error: $message");
        }

        return $dom;
    }

    /**
     * @return DOMNodeList<DOMNode>
     * @throws ParserException
     */
    private function query(DOMXPath $xpath, string $expression, ?DOMNode $context = null): DOMNodeList
    {
        $nodes = $context === null ? $xpath->query($expression) : $xpath->query($expression, $context);

        if ($nodes === false) {
            throw new ParserException("XPath query failed: $expression");
        }

        return $nodes;
    }

    /**
     * @throws ParserException
     */
    private function queryOne(DOMXPath $xpath, string $expression, ?DOMNode $context = null): ?DOMNode
    {
        return $this->query($xpath, $expression, $context)->item(0);
    }

    /**
     * @throws ParserException
     */
    private function ownerDocument(DOMNode $node): DOMDocument
    {
        if (!$node->ownerDocument instanceof DOMDocument) {
            throw new ParserException('DOM node is not attached to a document.');
        }

        return $node->ownerDocument;
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
            throw new ParserException(
                "Unable to scan directory for classes: {$this->inputDir}. Error: " . $e->getMessage()
            );
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
        $document = $this->ownerDocument($element);
        $xpath    = new DOMXPath($document);
        $links    = $this->query($xpath, ".//a", $element);

        foreach ($links as $link) {
            if (!$link instanceof DOMElement) {
                continue;
            }

            $href = $link->getAttribute('href');

            if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
                continue;
            }

            $isKnown = str_contains($href, 'function.') || str_contains($link->textContent, '::');

            if ($isKnown) {
                $textNode = $document->createTextNode('{@link ' . $link->textContent . '}');
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
        $xpath = new DOMXPath($this->ownerDocument($element));

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
    private function modifyAttribute(
        DOMXPath $xpath,
        DOMNode $context,
        string $selector,
        string $value,
        string $attribute
    ): void {
        $elements = $this->query($xpath, $this->selectorToXPath($selector), $context);

        foreach ($elements as $element) {
            if (!$element instanceof DOMElement) {
                continue;
            }

            $element->setAttribute($attribute, $value);
        }
    }

    private function selectorToXPath(string $selector): string
    {
        if (preg_match('/^\.([A-Za-z0-9_-]+)\s+([A-Za-z0-9_-]+)$/', $selector, $matches)) {
            return ".//*[contains(concat(' ', normalize-space(@class), ' '), ' {$matches[1]} ')]//{$matches[2]}";
        }

        $className = ltrim($selector, '.');
        return ".//*[contains(concat(' ', normalize-space(@class), ' '), ' $className ')]";
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
        $preElements = $this->query($xpath, './/pre', $context);
        if ($preElements->length === 0) {
            return;
        }

        $document = $this->ownerDocument($context);
        foreach ($preElements as $preElement) {
            $parentNode = $preElement->parentNode;
            if (!$parentNode) {
                continue;
            }

            $newElement = $document->createElement('blockquote');
            $newElement->setAttribute('style', 'border:1px gray solid;');

            $preContent = $preElement->textContent;
            if (!empty($preContent)) {
                $fragment   = $document->createDocumentFragment();
                $newContent = htmlspecialchars($preContent, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $newContent = str_replace(["\r\n", "\n", " "], ["<br>", "<br>", "&nbsp;"], $newContent);
                $newContent = sprintf(
                    '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div>%s</div></body></html>',
                    $newContent
                );
                $tempDom = $this->loadHtmlDocument($newContent, 'generated pre fragment');

                $divElement = $tempDom->getElementsByTagName('div')->item(0);
                if ($divElement) {
                    foreach ($divElement->childNodes as $node) {
                        $importedNode = $document->importNode($node, true);
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
        $codeElements = $this->query($xpath, './/code', $context);
        if ($codeElements->length === 0) {
            return;
        }

        $document = $this->ownerDocument($context);
        foreach ($codeElements as $codeElement) {
            if (!$codeElement instanceof DOMElement) {
                continue;
            }

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
                ? $document->createElement('blockquote')
                : $document->createElement('span');

            if ($isPhpCode) {
                $newElement->setAttribute('style', 'border:1px gray solid;white-space:pre-wrap');
            }

            // Copy attributes
            $attributes = $codeElement->attributes;
            if ($attributes !== null) {
                foreach ($attributes as $attribute) {
                    if (!$attribute instanceof DOMNode) {
                        continue;
                    }

                    $newElement->setAttribute($attribute->nodeName, $attribute->nodeValue ?? '');
                }
            }

            // Move child nodes
            while ($codeElement->hasChildNodes()) {
                $child = $codeElement->firstChild;
                if (!$child instanceof DOMNode) {
                    break;
                }

                $newElement->appendChild($child);
            }

            $parentElement->replaceChild($newElement, $codeElement);
        }
    }

    /**
     * Modify abbr elements
     */
    private function modifyAbbrElements(DOMXPath $xpath, DOMNode $context): void
    {
        $abbrElements = $this->query($xpath, './/abbr', $context);
        if ($abbrElements->length === 0) {
            return;
        }

        $document = $this->ownerDocument($context);
        foreach ($abbrElements as $abbrElement) {
            $parentNode = $abbrElement->parentNode;
            if (!$parentNode) {
                continue;
            }
            $newElement  = $document->createElement('span');
            $abbrContent = $abbrElement->textContent;
            if (!empty($abbrContent)) {
                $fragment   = $document->createDocumentFragment();
                $newContent = sprintf(
                    '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div>%s</div></body></html>',
                    htmlspecialchars($abbrContent, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8')
                );
                $tempDom    = $this->loadHtmlDocument($newContent, 'generated abbr fragment');
                $divElement = $tempDom->getElementsByTagName('div')->item(0);
                if ($divElement) {
                    foreach ($divElement->childNodes as $node) {
                        $importedNode = $document->importNode($node, true);
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
