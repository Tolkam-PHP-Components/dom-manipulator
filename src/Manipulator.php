<?php declare(strict_types=1);

namespace Tolkam\DOM\Manipulator;

use DOMCdataSection;
use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Based on wa72/htmlpagedom package
 *
 * @see    https://github.com/wasinger/htmlpagedom
 * @author Christoph Singer
 *
 */
class Manipulator extends Crawler
{
    /**
     * Creates instance from a HTML string, DOMNode, DOMNodeList
     *
     * @param DOMNodeList|DOMNode|DOMNode[]|string|null|static $content
     *
     * @return static
     */
    public static function create($content): self
    {
        return $content instanceof static
            ? $content
            : new static($content);
    }
    
    /**
     * Creates a new element
     *
     * @param string     $name
     * @param mixed|null $children
     * @param array      $attributes
     *
     * @return DOMElement
     */
    public function createElement(
        string $name,
        $children = '',
        array $attributes = []
    ): DOMElement {
        $element = $this->getDOMDocument()->createElement($name);
        static::create($element)->append($children);
        
        foreach ($attributes as $k => $value) {
            $element->setAttribute($k, $value);
        }
        
        return $element;
    }
    
    /**
     * Creates new comment
     *
     * @param string $comment
     *
     * @return DOMComment
     */
    public function createComment(string $comment): DOMComment
    {
        return $this->getDOMDocument()->createComment($comment);
    }
    
    /**
     * Creates CDATA section
     *
     * @param string $contents
     *
     * @return DOMCdataSection
     */
    public function createCDATA(string $contents): DOMCdataSection
    {
        return $this->getDOMDocument()->createCDATASection($contents);
    }
    
    /**
     * Adds the specified class(es) to each element in the set of matched elements.
     *
     * @param $name
     *
     * @return $this
     */
    public function addClass($name): self
    {
        foreach ($this as $node) {
            if ($node instanceof DOMElement) {
                /** @var DOMElement $node */
                $classes = preg_split('/\s+/s', $node->getAttribute('class'));
                $found = false;
                $count = count($classes);
                for ($i = 0; $i < $count; $i++) {
                    if ($classes[$i] == $name) {
                        $found = true;
                    }
                }
                if (!$found) {
                    $classes[] = $name;
                    $node->setAttribute('class', trim(join(' ', $classes)));
                }
            }
        }
        
        return $this;
    }
    
    /**
     * Inserts content, specified by the parameter,
     * after each element in the set of matched elements
     *
     * @param $content
     *
     * @return $this
     */
    public function after($content): self
    {
        $content = self::create($content);
        
        $newNodes = [];
        foreach ($this as $i => $node) {
            /** @var DOMNode $node */
            $refNode = $node->nextSibling;
            foreach ($content as $newNode) {
                /** @var DOMNode $newNode */
                $newNode = static::importNewNode($newNode, $node, !!$i);
                if ($refNode === null) {
                    $node->parentNode->appendChild($newNode);
                }
                else {
                    $node->parentNode->insertBefore($newNode, $refNode);
                }
                $newNodes[] = $newNode;
            }
        }
        
        $content->clear();
        $content->add($newNodes);
        
        return $this;
    }
    
    /**
     * Inserts HTML content as child nodes of each element after existing children
     *
     * @param $content
     *
     * @return $this
     */
    public function append($content): self
    {
        $content = self::create($content);
        
        $newNodes = [];
        foreach ($this as $i => $node) {
            /** @var DOMNode $node */
            foreach ($content as $newNode) {
                /** @var DOMNode $newNode */
                $newNode = static::importNewNode($newNode, $node, !!$i);
                $node->appendChild($newNode);
                $newNodes[] = $newNode;
            }
        }
        
        $content->clear();
        $content->add($newNodes);
        
        return $this;
    }
    
    /**
     * Inserts every element in the set of matched elements to the end of the target
     *
     * @param $element
     *
     * @return $this
     */
    public function appendTo($element): self
    {
        $e = self::create($element);
        
        $newNodes = [];
        foreach ($e as $i => $node) {
            /** @var DOMNode $node */
            foreach ($this as $newNode) {
                /** @var DOMNode $newNode */
                if ($node !== $newNode) {
                    $newNode = static::importNewNode($newNode, $node, !!$i);
                    $node->appendChild($newNode);
                }
                $newNodes[] = $newNode;
            }
        }
        
        return self::create($newNodes);
    }
    
    /**
     * Sets an attribute on each element
     *
     * @param $name
     * @param $value
     *
     * @return $this
     */
    public function setAttribute($name, $value): self
    {
        foreach ($this as $node) {
            if ($node instanceof DOMElement) {
                /** @var DOMElement $node */
                $node->setAttribute($name, $value);
            }
        }
        
        return $this;
    }
    
    /**
     * Returns the attribute value of the first node of the list
     *
     * @param string $name
     *
     * @return string|null
     */
    public function getAttribute(string $name): ?string
    {
        return parent::attr($name);
    }
    
    /**
     * Inserts content, specified by the parameter,
     * before each element in the set of matched elements
     *
     * @param $content
     *
     * @return $this
     */
    public function before($content): self
    {
        $content = self::create($content);
        
        $newNodes = [];
        foreach ($this as $i => $node) {
            /** @var DOMNode $node */
            foreach ($content as $newNode) {
                /** @var DOMNode $newNode */
                if ($node !== $newNode) {
                    $newNode = static::importNewNode($newNode, $node, !!$i);
                    $node->parentNode->insertBefore($newNode, $node);
                    $newNodes[] = $newNode;
                }
            }
        }
        
        $content->clear();
        $content->add($newNodes);
        
        return $this;
    }
    
    /**
     * Creates a deep copy of the set of matched elements
     *
     * @return Manipulator
     */
    public function makeClone(): Manipulator
    {
        return clone $this;
    }
    
    /**
     * Gets CSS style property of the first element
     *
     * @param string $key
     *
     * @return string|null
     */
    public function getStyle(string $key): ?string
    {
        $styles = Util::cssStringToArray($this->getAttribute('style'));
        
        return (isset($styles[$key]) ? $styles[$key] : null);
    }
    
    /**
     * Sets CSS style property for all elements in the list
     *
     * @param string $key
     * @param string $value
     *
     * @return $this
     */
    public function setStyle(string $key, string $value): self
    {
        foreach ($this as $node) {
            if ($node instanceof DOMElement) {
                /** @var DOMElement $node */
                $styles = Util::cssStringToArray($node->getAttribute('style'));
                if ($value != '') {
                    $styles[$key] = $value;
                }
                elseif (isset($styles[$key])) {
                    unset($styles[$key]);
                }
                $node->setAttribute('style', Util::cssArrayToString($styles));
            }
        }
        
        return $this;
    }
    
    /**
     * Removes all child nodes and text from all nodes in set
     *
     * @return $this
     */
    public function makeEmpty(): self
    {
        foreach ($this as $node) {
            $node->nodeValue = '';
        }
        
        return $this;
    }
    
    /**
     * Determines whether any of the matched elements are assigned the given class
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasClass(string $name): bool
    {
        foreach ($this as $node) {
            if ($node instanceof DOMElement && $class = $node->getAttribute('class')) {
                $classes = preg_split('/\s+/s', $class);
                if (in_array($name, $classes)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Sets the HTML contents of each element
     *
     * @param mixed $content
     *
     * @return $this
     */
    public function setInnerHtml($content): self
    {
        $content = self::create($content);
        
        foreach ($this as $node) {
            $node->nodeValue = '';
            foreach ($content as $newNode) {
                /** @var DOMNode $node */
                /** @var DOMNode $newNode */
                $newNode = static::importNewNode($newNode, $node);
                $node->appendChild($newNode);
            }
        }
        
        return $this;
    }
    
    /**
     * Alias for Crawler::html() for naming consistency with setInnerHtml()
     *
     * @return string
     */
    public function getInnerHtml(): string
    {
        return parent::html();
    }
    
    /**
     * Inserts every element in the set of matched elements after the target
     *
     * @param $element
     *
     * @return $this
     */
    public function insertAfter($element): self
    {
        $e = self::create($element);
        
        $newNodes = [];
        foreach ($e as $i => $node) {
            /** @var DOMNode $node */
            $refNode = $node->nextSibling;
            foreach ($this as $newNode) {
                /** @var DOMNode $newNode */
                $newNode = static::importNewNode($newNode, $node, !!$i);
                if ($refNode === null) {
                    $node->parentNode->appendChild($newNode);
                }
                else {
                    $node->parentNode->insertBefore($newNode, $refNode);
                }
                $newNodes[] = $newNode;
            }
        }
        
        return self::create($newNodes);
    }
    
    /**
     * Inserts every element in the set of matched elements before the target
     *
     * @param $element
     *
     * @return $this
     */
    public function insertBefore($element): self
    {
        $e = self::create($element);
        
        $newNodes = [];
        foreach ($e as $i => $node) {
            /** @var DOMNode $node */
            foreach ($this as $newNode) {
                /** @var DOMNode $newNode */
                $newNode = static::importNewNode($newNode, $node, !!$i);
                if ($newNode !== $node) {
                    $node->parentNode->insertBefore($newNode, $node);
                }
                $newNodes[] = $newNode;
            }
        }
        
        return self::create($newNodes);
    }
    
    /**
     * Insert content, specified by the parameter,
     * to the beginning of each element in the set of matched elements
     *
     * @param $content
     *
     * @return $this
     */
    public function prepend($content): self
    {
        $content = self::create($content);
        
        $newNodes = [];
        foreach ($this as $i => $node) {
            $refNode = $node->firstChild;
            /** @var DOMNode $node */
            foreach ($content as $newNode) {
                /** @var DOMNode $newNode */
                $newNode = static::importNewNode($newNode, $node, !!$i);
                if ($refNode === null) {
                    $node->appendChild($newNode);
                }
                else {
                    if ($refNode !== $newNode) {
                        $node->insertBefore($newNode, $refNode);
                    }
                }
                $newNodes[] = $newNode;
            }
        }
        $content->clear();
        $content->add($newNodes);
        
        return $this;
    }
    
    /**
     * Inserts every element in the set of matched elements to the beginning of the target
     *
     * @param $element
     *
     * @return $this
     */
    public function prependTo($element): self
    {
        $e = self::create($element);
        
        $newNodes = [];
        foreach ($e as $i => $node) {
            $refNode = $node->firstChild;
            /** @var DOMNode $node */
            foreach ($this as $newNode) {
                /** @var DOMNode $newNode */
                $newNode = static::importNewNode($newNode, $node, !!$i);
                if ($newNode !== $node) {
                    if ($refNode === null) {
                        $node->appendChild($newNode);
                    }
                    else {
                        $node->insertBefore($newNode, $refNode);
                    }
                }
                $newNodes[] = $newNode;
            }
        }
        
        return self::create($newNodes);
    }
    
    /**
     * Removes the set of matched elements from the DOM
     *
     * @return void
     */
    public function remove(): void
    {
        foreach ($this as $node) {
            /**
             * @var DOMNode $node
             */
            if ($node->parentNode instanceof DOMElement) {
                $node->parentNode->removeChild($node);
            }
        }
        $this->clear();
    }
    
    /**
     * Removes an attribute from each element in the set of matched elements
     *
     * @param $name
     *
     * @return $this
     */
    public function removeAttribute($name): self
    {
        foreach ($this as $node) {
            if ($node instanceof DOMElement) {
                /** @var DOMElement $node */
                if ($node->hasAttribute($name)) {
                    $node->removeAttribute($name);
                }
            }
        }
        
        return $this;
    }
    
    /**
     * Removes a class from each element in the list
     *
     * @param $name
     *
     * @return $this
     */
    public function removeClass($name): self
    {
        foreach ($this as $node) {
            if ($node instanceof DOMElement) {
                /** @var DOMElement $node */
                $classes = preg_split('/\s+/s', $node->getAttribute('class'));
                $count = count($classes);
                for ($i = 0; $i < $count; $i++) {
                    if ($classes[$i] == $name) {
                        unset($classes[$i]);
                    }
                }
                $node->setAttribute('class', trim(join(' ', $classes)));
            }
        }
        
        return $this;
    }
    
    /**
     * Replaces each target element with the set of matched elements
     *
     * @param $element
     *
     * @return $this
     */
    public function replaceAll($element): self
    {
        $e = self::create($element);
        
        $newNodes = [];
        foreach ($e as $i => $node) {
            /** @var DOMNode $node */
            $parent = $node->parentNode;
            $refNode = $node->nextSibling;
            foreach ($this as $j => $newNode) {
                /** @var DOMNode $newNode */
                $newNode = static::importNewNode($newNode, $node, !!$i);
                if ($j == 0) {
                    $parent->replaceChild($newNode, $node);
                }
                else {
                    $parent->insertBefore($newNode, $refNode);
                }
                $newNodes[] = $newNode;
            }
        }
        
        return self::create($newNodes);
    }
    
    /**
     * Replaces each element in the set of matched elements
     * with the provided new content and return the set of elements that was removed
     *
     * @param $content
     *
     * @return $this
     */
    public function replaceWith($content): self
    {
        $content = self::create($content);
        
        $newNodes = [];
        foreach ($this as $i => $node) {
            /** @var DOMNode $node */
            $parent = $node->parentNode;
            $refNode = $node->nextSibling;
            foreach ($content as $j => $newNode) {
                /** @var DOMNode $newNode */
                $newNode = static::importNewNode($newNode, $node, !!$i);
                if ($j == 0) {
                    $parent->replaceChild($newNode, $node);
                }
                else {
                    $parent->insertBefore($newNode, $refNode);
                }
                $newNodes[] = $newNode;
            }
        }
        
        $content->clear();
        $content->add($newNodes);
        
        return $this;
    }
    
    /**
     * Gets the combined text contents of each element
     * in the set of matched elements, including their descendants
     *
     * @return string
     */
    public function getCombinedText(): string
    {
        $text = '';
        foreach ($this as $node) {
            /** @var DOMNode $node */
            $text .= $node->nodeValue;
        }
        
        return $text;
    }
    
    /**
     * Sets the text contents of the matched elements
     *
     * @param string $text
     *
     * @return $this
     */
    public function setText(string $text): self
    {
        $text = htmlspecialchars($text);
        
        foreach ($this as $node) {
            /** @var DOMNode $node */
            $node->nodeValue = $text;
        }
        
        return $this;
    }
    
    /**
     * Adds or removes one or more classes from each element
     * in the set of matched elements, depending the class’s presence
     *
     * @param string $classname
     *
     * @return $this
     */
    public function toggleClass(string $classname): self
    {
        $classes = explode(' ', $classname);
        
        foreach ($this as $i => $node) {
            $c = self::create($node);
            /** @var DOMNode $node */
            foreach ($classes as $class) {
                if ($c->hasClass($class)) {
                    $c->removeClass($class);
                }
                else {
                    $c->addClass($class);
                }
            }
        }
        
        return $this;
    }
    
    /**
     * Removes the parents of the set of matched elements from the DOM,
     * leaving the matched elements in their place
     *
     * @return $this
     */
    public function unwrap(): self
    {
        $parents = [];
        foreach ($this as $i => $node) {
            $parents[] = $node->parentNode;
        }
        
        self::create($parents)->unwrapInner();
        
        return $this;
    }
    
    /**
     * Remove the matched elements, but promote the children to take their place
     *
     * @return void
     */
    public function unwrapInner(): void
    {
        foreach ($this as $i => $node) {
            if (!$node->parentNode instanceof DOMElement) {
                throw new InvalidArgumentException(
                    'DOMElement does not have a parent DOMElement node'
                );
            }
            
            /** @var DOMNode[] $children */
            $children = iterator_to_array($node->childNodes);
            foreach ($children as $child) {
                $node->parentNode->insertBefore($child, $node);
            }
            
            $node->parentNode->removeChild($node);
        }
    }
    
    /**
     * Wraps an HTML structure around each element in the set of matched elements
     *
     * The HTML structure must contain only one root node, e.g.:
     * Works: <div><div></div></div>
     * Does not work: <div></div><div></div>
     *
     * @param string|static|DOMNode $wrappingElement
     *
     * @return $this
     */
    public function wrap($wrappingElement): self
    {
        $content = self::create($wrappingElement);
        
        $newNodes = [];
        foreach ($this as $i => $node) {
            /** @var DOMNode $node */
            $newNode = $content->getNode(0);
            /** @var DOMNode $newNode */
            if ($newNode->ownerDocument !== $node->ownerDocument) {
                $newNode = $node->ownerDocument->importNode($newNode, true);
            }
            else {
                if ($i > 0) {
                    $newNode = $newNode->cloneNode(true);
                }
            }
            $oldNode = $node->parentNode->replaceChild($newNode, $node);
            while ($newNode->hasChildNodes()) {
                $elementFound = false;
                foreach ($newNode->childNodes as $child) {
                    if ($child instanceof DOMElement) {
                        $newNode = $child;
                        $elementFound = true;
                        break;
                    }
                }
                if (!$elementFound) {
                    break;
                }
            }
            $newNode->appendChild($oldNode);
            $newNodes[] = $newNode;
        }
        
        $content->clear();
        $content->add($newNodes);
        
        return $this;
    }
    
    /**
     * Wraps an HTML structure around all elements in the set of matched elements.
     *
     * @param string|static|DOMNode|DOMNodeList $content
     *
     * @return $this
     */
    public function wrapAll($content): self
    {
        $content = self::create($content);
        
        $parent = $this->getNode(0)->parentNode;
        foreach ($this as $i => $node) {
            /** @var DOMNode $node */
            if ($node->parentNode !== $parent) {
                throw new LogicException(
                    'Nodes to be wrapped with wrapAll() must all have the same parent'
                );
            }
        }
        
        $newNode = $content->getNode(0);
        /** @var DOMNode $newNode */
        $newNode = static::importNewNode($newNode, $parent);
        
        $newNode = $parent->insertBefore($newNode, $this->getNode(0));
        
        $content->clear();
        $content->add($newNode);
        
        while ($newNode->hasChildNodes()) {
            $elementFound = false;
            foreach ($newNode->childNodes as $child) {
                if ($child instanceof DOMElement) {
                    $newNode = $child;
                    $elementFound = true;
                    break;
                }
            }
            if (!$elementFound) {
                break;
            }
        }
        foreach ($this as $i => $node) {
            /** @var DOMNode $node */
            $newNode->appendChild($node);
        }
        
        return $this;
    }
    
    /**
     * Wraps an HTML structure around the content of each element
     * in the set of matched elements
     *
     * @param string|static|DOMNode|DOMNodeList $content
     *
     * @return $this
     */
    public function wrapInner($content): self
    {
        foreach ($this as $i => $node) {
            /** @var DOMNode $node */
            self::create($node->childNodes)->wrapAll($content);
        }
        
        return $this;
    }
    
    /**
     * Checks whether the first node contains a complete html document
     * (as opposed to a document fragment)
     *
     * @return boolean
     */
    public function isHtmlDocument(): bool
    {
        $node = $this->getNode(0);
        
        if ($node instanceof DOMElement
            && $node->ownerDocument instanceof DOMDocument
            && $node->ownerDocument->documentElement === $node
            && $node->nodeName == 'html'
        ) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Gets ownerDocument of the first element
     *
     * @return DOMDocument|null
     */
    public function getDOMDocument(): ?DOMDocument
    {
        $node = $this->getNode(0);
        
        if ($node instanceof DOMElement && $node->ownerDocument instanceof DOMDocument) {
            return $node->ownerDocument;
        }
        
        throw new RuntimeException('Unable to get DOMDocument');
    }
    
    /**
     * Adds HTML/XML content to the HtmlPageCrawler object
     * (but not to the DOM of an already attached node)
     *
     * Overrides Crawler::addContent() because HTML fragments
     * are always added as complete documents there
     *
     *
     * @param string      $content A string to parse as HTML/XML
     * @param null|string $type    The content type of the string
     *
     * @return void
     */
    public function addContent(string $content, string $type = null): void
    {
        if (empty($type)) {
            $type = 'text/html;charset=UTF-8';
        }
        if (substr($type, 0, 9) == 'text/html' && !preg_match('/<html\b[^>]*>/i', $content)) {
            // string contains no <html> Tag => no complete document but an HTML fragment!
            $this->addHtmlFragment($content);
        }
        else {
            parent::addContent($content, $type);
        }
    }
    
    /**
     * Adds html fragment
     *
     * @param        $content
     * @param string $charset
     *
     * @return void
     */
    public function addHtmlFragment($content, $charset = 'UTF-8'): void
    {
        $d = new DOMDocument('1.0', $charset);
        $d->preserveWhiteSpace = false;
        $root = $d->appendChild($d->createElement('__tmp__'));
        $bodyNode = Util::getBodyNodeFromHtmlFragment($content, $charset);
        
        foreach ($bodyNode->childNodes as $child) {
            $inode = $root->appendChild($d->importNode($child, true));
            if ($inode) {
                $this->addNode($inode);
            }
        }
    }
    
    /**
     * Adds a node to the current list of nodes
     *
     * Uses the appropriate specialized add*() method based
     * on the type of the argument
     *
     * @param null|DOMNodeList|array|DOMNode|static $node
     *
     * @return void
     */
    public function add($node): void
    {
        if ($node instanceof Crawler) {
            foreach ($node as $childNode) {
                $this->addNode($childNode);
            }
        }
        else {
            parent::add($node);
        }
    }
    
    /**
     * Gets the HTML code fragment of all elements and their contents
     *
     * If the first node contains a complete HTML document return only
     * the full code of this document
     *
     * @return string
     */
    public function mergeToString(): string
    {
        $rootTag = '__tmp__';
        
        if ($this->isHtmlDocument()) {
            return $this->getDOMDocument()->saveHTML();
        }
        else {
            $doc = new DOMDocument('1.0', 'UTF-8');
            $root = $doc->appendChild($doc->createElement($rootTag));
            foreach ($this as $node) {
                $root->appendChild($doc->importNode($node, true));
            }
            $html = trim($doc->saveHTML());
            
            $pattern = '~^<' . $rootTag . '[^>]*>|</' . $rootTag . '>$~';
            
            return preg_replace($pattern, '', $html);
        }
    }
    
    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->outerHtml();
    }
    
    public function __clone()
    {
        $newNodes = [];
        
        foreach ($this as $node) {
            /** @var DOMNode $node */
            $newNodes[] = $node->cloneNode(true);
        }
        
        $this->clear();
        $this->add($newNodes);
    }
    
    /**
     * @param DOMNode $newNode
     * @param DOMNode $referenceNode
     * @param bool    $clone
     *
     * @return DOMNode
     */
    protected static function importNewNode(
        DOMNode $newNode,
        DOMNode $referenceNode,
        bool $clone = false
    ): DOMNode {
        if ($newNode->ownerDocument !== $referenceNode->ownerDocument) {
            $referenceNode->ownerDocument->preserveWhiteSpace = false;
            $newNode = $referenceNode->ownerDocument->importNode($newNode, true);
        }
        
        if ($clone) {
            $newNode = $newNode->cloneNode(true);
        }
        
        return $newNode;
    }
}
