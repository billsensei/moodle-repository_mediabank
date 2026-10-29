<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace contenttype_mediabank;

/**
 * Decides whether an SVG file may be stored, by reading it as XML and checking every element and attribute against lists of
 * what is allowed.
 *
 * An SVG is a picture that can also carry scripts. The protection against them is how the file is shown (as an image) and sent
 * (as a download with a sandbox policy). But a copy of the file in a course is served by Moodle itself, so the file is also
 * checked here, and refused unless it is plainly a picture. The check refuses and never edits: nothing is removed from a file.
 *
 * Refused: any element that is not on the list of drawing elements (so no scripts, animation, embedded pages or foreign
 * content, whatever namespace prefix they are written with); attributes that run code (every on... attribute); links that are
 * not a point inside the file, a plain web or mail address, or a small embedded picture; style rules that load anything;
 * processing instructions; entity definitions; and XML that is not well formed. Values are checked after the XML parser has
 * decoded character references, so writing "javascript:" with numeric references does not hide it.
 *
 * @package    contenttype_mediabank
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class svg_validator {
    /** @var int Largest SVG read, in bytes. Reading builds a tree in memory, which is many times the size of the file. */
    public const MAX_BYTES = 2097152;

    /** @var string The SVG namespace. */
    protected const SVG_NS = 'http://www.w3.org/2000/svg';

    /** @var string[] Namespaces that editors add. Their elements are not drawn, and are checked like any other. */
    protected const EDITOR_NAMESPACES = [
        'http://www.w3.org/1999/xlink',
        'http://www.w3.org/XML/1998/namespace',
        'http://www.w3.org/2000/xmlns/',
        'http://www.inkscape.org/namespaces/inkscape',
        'http://sodipodi.sourceforge.net/DTD/sodipodi-0.dtd',
        'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
        'http://purl.org/dc/elements/1.1/',
        'http://purl.org/dc/terms/',
        'http://creativecommons.org/ns#',
        'http://web.resource.org/cc/',
        'http://inkscape.sourceforge.net/DTD/sodipodi-0.dtd',
        'http://www.bohemiancoding.com/sketch/ns',
    ];

    /** @var string[] Elements in the SVG namespace that only draw or describe. No script, animation, link-out or embedding. */
    protected const ELEMENTS = [
        'svg', 'g', 'defs', 'symbol', 'use', 'switch', 'title', 'desc', 'metadata', 'style', 'a', 'view',
        'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'image',
        'text', 'tspan', 'textpath', 'tref',
        'lineargradient', 'radialgradient', 'stop', 'pattern', 'clippath', 'mask', 'marker',
        'filter', 'feblend', 'fecolormatrix', 'fecomponenttransfer', 'fecomposite', 'feconvolvematrix',
        'fediffuselighting', 'fedisplacementmap', 'fedistantlight', 'fedropshadow', 'feflood', 'fefunca',
        'fefuncb', 'fefuncg', 'fefuncr', 'fegaussianblur', 'feimage', 'femerge', 'femergenode', 'femorphology',
        'feoffset', 'fepointlight', 'fespecularlighting', 'fespotlight', 'fetile', 'feturbulence',
    ];

    /** @var string[] Character encodings accepted: those in which "<" and the letters are the same bytes as in ASCII. */
    protected const ENCODINGS = [
        '', 'utf-8', 'utf8', 'ascii', 'us-ascii', 'iso-8859-1', 'iso-8859-15', 'latin1', 'windows-1252', 'cp1252',
    ];

    /** @var string[] Link attributes, in any namespace. */
    protected const LINKS = ['href', 'src'];

    /**
     * What is wrong with an SVG, or null if it is acceptable.
     *
     * @param string $svg The file content.
     * @return string|null A short reason in English, for logs and tests. Not shown to users.
     */
    public static function find_problem(string $svg): ?string {
        if (strlen($svg) > self::MAX_BYTES) {
            return 'too large';
        }
        if (trim($svg) === '') {
            return 'empty';
        }
        if (!self::is_ascii_compatible_encoding($svg)) {
            return 'encoding not allowed';
        }
        // An entity definition can make a small file expand to a huge one, or read another file. Refuse before parsing.
        if (preg_match('/<!ENTITY/i', $svg)) {
            return 'entity definition';
        }
        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument();
        // No network, and entities are not replaced. Nothing external is read.
        $loaded = $document->loadXML($svg, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded || !$document->documentElement) {
            return 'not well formed';
        }
        if (!in_array(strtolower((string) $document->xmlEncoding), self::ENCODINGS, true)) {
            return 'encoding not allowed';
        }
        if ($document->documentElement->namespaceURI !== self::SVG_NS || $document->documentElement->localName !== 'svg') {
            return 'not an svg element at the top';
        }
        return self::check_node($document);
    }

    /**
     * Whether the file is in an encoding where a search of its bytes finds what the XML parser will find.
     *
     * A file in UTF-16 or UTF-32, or in a stranger encoding named in its XML declaration, has other bytes for the same text.
     * Only UTF-8 (with or without a byte order mark), ASCII and the common single-byte Western encodings, or no declaration,
     * are accepted.
     *
     * @param string $svg The file content.
     * @return bool
     */
    protected static function is_ascii_compatible_encoding(string $svg): bool {
        // Byte order marks of UTF-16 and UTF-32.
        if (preg_match('/^(\xFF\xFE|\xFE\xFF|\x00\x00\xFE\xFF)/', $svg) || str_contains(substr($svg, 0, 4), "\x00")) {
            return false;
        }
        if (preg_match('/^(?:\xEF\xBB\xBF)?\s*<\?xml[^>]*?encoding\s*=\s*["\']([^"\']+)["\']/i', $svg, $matches)) {
            return in_array(strtolower($matches[1]), self::ENCODINGS, true);
        }
        return true;
    }

    /**
     * Check a node and everything under it.
     *
     * @param \DOMNode $node Where to start.
     * @return string|null The first problem found.
     */
    protected static function check_node(\DOMNode $node): ?string {
        foreach ($node->childNodes as $child) {
            switch ($child->nodeType) {
                case XML_ELEMENT_NODE:
                    $problem = self::check_element($child);
                    break;
                case XML_PI_NODE:
                    $problem = 'processing instruction';
                    break;
                case XML_ENTITY_REF_NODE:
                    $problem = 'entity reference';
                    break;
                case XML_DOCUMENT_TYPE_NODE:
                    $problem = $child->internalSubset ? 'internal subset' : null;
                    break;
                default:
                    $problem = null;
            }
            if ($problem !== null) {
                return $problem;
            }
        }
        return null;
    }

    /**
     * Check one element: its name, its attributes, a style element's rules, and then its children.
     *
     * @param \DOMElement $element The element.
     * @return string|null The first problem found.
     */
    protected static function check_element(\DOMElement $element): ?string {
        $namespace = $element->namespaceURI;
        if ($namespace === self::SVG_NS) {
            if (!in_array(strtolower($element->localName), self::ELEMENTS, true)) {
                return 'element not allowed: ' . $element->localName;
            }
        } else if (!in_array((string) $namespace, self::EDITOR_NAMESPACES, true)) {
            return 'element in a namespace not allowed: ' . $element->nodeName;
        }
        foreach ($element->attributes as $attribute) {
            $problem = self::check_attribute($element, $attribute);
            if ($problem !== null) {
                return $problem;
            }
        }
        if ($namespace === self::SVG_NS && strtolower($element->localName) === 'style') {
            if (self::css_loads_something($element->textContent)) {
                return 'style rule that loads something';
            }
        }
        return self::check_node($element);
    }

    /**
     * Check one attribute.
     *
     * @param \DOMElement $element The element it is on.
     * @param \DOMAttr $attribute The attribute.
     * @return string|null The problem, if any.
     */
    protected static function check_attribute(\DOMElement $element, \DOMAttr $attribute): ?string {
        $name = strtolower($attribute->localName);
        $value = $attribute->value;
        if (str_starts_with($name, 'on')) {
            return 'event attribute: ' . $attribute->nodeName;
        }
        $known = array_merge([self::SVG_NS], self::EDITOR_NAMESPACES);
        if ($attribute->namespaceURI !== null && !in_array($attribute->namespaceURI, $known, true)) {
            return 'attribute in a namespace not allowed: ' . $attribute->nodeName;
        }
        // A function written with an escape, such as fill="ur\6c(...)", which a search for "url(" would not find.
        if (str_contains($value, '\\') && str_contains($value, '(')) {
            return 'escape in a value with a function call in ' . $name;
        }
        // A paint or filter reference to another file, such as fill="url(http://...)".
        if (stripos($value, 'url(') !== false && self::css_loads_something($value)) {
            return 'reference to something outside the file in ' . $name;
        }
        if (in_array($name, self::LINKS, true)) {
            return self::link_is_acceptable($value, strtolower($element->localName)) ? null : 'link not allowed: ' . $name;
        }
        if ($name === 'style' && self::css_loads_something($value)) {
            return 'style attribute that loads something';
        }
        // A value that starts like a script address is refused wherever it is.
        if (preg_match('/^[\s\x00-\x20]*(java|vb)script:/i', $value)) {
            return 'script address in ' . $name;
        }
        return null;
    }

    /**
     * Whether a link target is one that cannot run anything or fetch anything.
     *
     * Allowed: a point inside the file ("#id"); a web or mail address, but only on an anchor, where it matters only if clicked;
     * and, on an image, a small embedded png, jpeg, gif or webp.
     *
     * @param string $value The attribute value, after the XML parser has decoded it.
     * @param string $element Lower-case element name.
     * @return bool
     */
    protected static function link_is_acceptable(string $value, string $element): bool {
        // Browsers ignore white space and control characters inside a scheme, so do the same before looking at it.
        $clean = preg_replace('/[\x00-\x20]+/', '', $value);
        if ($clean === '' || $clean[0] === '#') {
            return true;
        }
        if ($element === 'a' && preg_match('/^(https?:\/\/|mailto:)/i', $clean)) {
            return true;
        }
        return $element === 'image' && preg_match('/^data:image\/(png|jpe?g|gif|webp)[;,]/i', $clean) === 1;
    }

    /**
     * Whether CSS pulls in anything from outside the file, or uses a form that can run code or hide what it does.
     *
     * The text is read as it is, with comments left in: stripping them with a pattern can be fooled by a comment marker inside a
     * quoted string, and a file that mentions an address in a comment is refused along with the rest. A backslash is refused
     * outright, because CSS escapes (such as ur\6c for "url") let a word be written so that a search does not find it.
     *
     * @param string $css Style rules or the value of a style attribute.
     * @return bool
     */
    protected static function css_loads_something(string $css): bool {
        if (str_contains($css, '\\')) {
            return true;
        }
        $loaders = '@import|@font-face|@namespace|expression\s*\(|(java|vb)script\s*:|behaviou?r\s*:|-moz-binding'
            . '|image-set\s*\(|\bsrc\s*\(|\belement\s*\(|\bimage\s*\(|cross-fade\s*\(|\bpaint\s*\(';
        if (preg_match('/' . $loaders . '/i', $css)) {
            return true;
        }
        // Take out every url() of a form that is allowed: a point inside the file, or a small embedded picture. If any url(
        // is left, it is something else, or written in a way that is not understood (unclosed, odd quoting, spaces in the
        // address), and the file is refused. Allowing known forms, and nothing else, leaves no gap to find.
        $allowed = '/url\(\s*(["\']?)(?:#[A-Za-z0-9_.:-]+|data:image\/(?:png|jpe?g|gif|webp);base64,[A-Za-z0-9+\/=]+)\1\s*\)/i';
        return preg_match('/url\s*\(/i', preg_replace($allowed, '', $css)) === 1;
    }
}
