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
 * Tests for the SVG allow-list check. These need no database, so every shape of file is tried in one run.
 *
 * @package    contenttype_mediabank
 * @category   test
 * @copyright  2026 SenseiMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(svg_validator::class)]
final class svg_validator_test extends \basic_testcase {
    /** @var string The namespace declarations of a plain SVG. */
    protected const NS = 'xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"';

    /**
     * Files that editors and designers write, which must be accepted.
     */
    public function test_ordinary_files_are_accepted(): void {
        $ns = self::NS;
        $files = [
            'plain' => "<svg $ns width=\"1\" height=\"1\"/>",
            'xml header and comment' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<!-- Created with Inkscape -->\n"
                . "<svg $ns><g/></svg>",
            'doctype without definitions' => "<?xml version=\"1.0\"?>\n<!DOCTYPE svg PUBLIC \"-//W3C//DTD SVG 1.1//EN\" "
                . "\"http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd\">\n<svg $ns/>",
            'byte order mark' => "\xEF\xBB\xBF<svg $ns/>",
            'style element' => "<svg $ns><style>.a{fill:red;stroke:url(#g)}</style><rect class=\"a\"/></svg>",
            'style with comment and fragment url' => "<svg $ns><style><![CDATA[ /* url(http://x) */ "
                . ".a{fill:url(#g)} ]]></style></svg>",
            'text that looks like handlers' => "<svg $ns><text>Turn on and off, once more, onload = 5</text></svg>",
            'gradients, filters, clip, mask, marker' => "<svg $ns><defs><linearGradient id=\"g\">"
                . "<stop offset=\"0\"/></linearGradient>"
                . "<filter id=\"f\"><feGaussianBlur stdDeviation=\"2\"/></filter><clipPath id=\"c\"><rect/></clipPath>"
                . "<mask id=\"m\"><rect/></mask><marker id=\"k\"><path d=\"M0 0\"/></marker></defs>"
                . "<rect fill=\"url(#g)\" filter=\"url(#f)\" clip-path=\"url(#c)\"/></svg>",
            'use of a fragment' => "<svg $ns><defs><g id=\"a\"/></defs><use xlink:href=\"#a\"/><use href=\"#a\"/></svg>",
            'embedded png' => "<svg $ns><image href=\"data:image/png;base64,iVBORw0KGgo=\"/></svg>",
            'web link on an anchor' => "<svg $ns><a xlink:href=\"https://example.com/page\"><text>x</text></a>"
                . "<a href=\"mailto:a@b.c\"/></svg>",
            'older inkscape and creative commons namespaces' => "<svg $ns xmlns:cc=\"http://web.resource.org/cc/\" "
                . "xmlns:sodipodi=\"http://inkscape.sourceforge.net/DTD/sodipodi-0.dtd\" sodipodi:version=\"0.32\">"
                . "<metadata><cc:Work/></metadata></svg>",
            'inkscape and metadata' => "<svg $ns xmlns:inkscape=\"http://www.inkscape.org/namespaces/inkscape\" "
                . "xmlns:sodipodi=\"http://sodipodi.sourceforge.net/DTD/sodipodi-0.dtd\" "
                . "xmlns:rdf=\"http://www.w3.org/1999/02/22-rdf-syntax-ns#\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\" "
                . "xmlns:cc=\"http://creativecommons.org/ns#\" inkscape:version=\"1.3\" sodipodi:docname=\"a.svg\">"
                . "<sodipodi:namedview id=\"nv\" inkscape:zoom=\"1\"/><metadata><rdf:RDF><cc:Work rdf:about=\"\">"
                . "<dc:format>image/svg+xml</dc:format></cc:Work></rdf:RDF></metadata><g inkscape:groupmode=\"layer\"/></svg>",
        ];
        foreach ($files as $label => $svg) {
            $this->assertNull(svg_validator::find_problem($svg), $label);
        }
    }

    /**
     * Files that run code, load something or are not a plain picture, written in the ways that get past a text search.
     */
    public function test_files_that_must_be_refused(): void {
        $ns = self::NS;
        $files = [
            'script' => "<svg $ns><script>alert(1)</script></svg>",
            'script with a prefix' => "<svg $ns><s:script xmlns:s=\"http://www.w3.org/2000/svg\">alert(1)</s:script></svg>",
            'script in the xhtml namespace' => "<svg $ns><h:script xmlns:h=\"http://www.w3.org/1999/xhtml\">"
                . "alert(1)</h:script></svg>",
            'script, odd case' => "<svg $ns><Script>alert(1)</Script></svg>",
            'html page inside' => "<svg $ns><foreignObject><div xmlns=\"http://www.w3.org/1999/xhtml\"/></foreignObject></svg>",
            'iframe in xhtml' => "<svg $ns><i:iframe xmlns:i=\"http://www.w3.org/1999/xhtml\" src=\"x\"/></svg>",
            'animation sets a link' => "<svg $ns><a href=\"#\"><set attributeName=\"href\" to=\"javascript:alert(1)\"/></a></svg>",
            'animate' => "<svg $ns><animate attributeName=\"x\" from=\"0\" to=\"1\"/></svg>",
            'event on the root' => "<svg $ns onload=\"alert(1)\"/>",
            'event, upper case' => "<svg $ns><rect ONCLICK=\"alert(1)\"/></svg>",
            'event with a namespace' => "<svg $ns xmlns:x=\"http://www.w3.org/2000/svg\"><rect x:onclick=\"alert(1)\"/></svg>",
            'javascript link' => "<svg $ns><a xlink:href=\"javascript:alert(1)\"/></svg>",
            'javascript link by character references' => "<svg $ns><a xlink:href=\"&#106;avascript:alert(1)\"/></svg>",
            'javascript link with a tab inside' => "<svg $ns><a xlink:href=\"jav&#x09;ascript:alert(1)\"/></svg>",
            'javascript link after a space' => "<svg $ns><a href=\"  javascript:alert(1)\"/></svg>",
            'data html link' => "<svg $ns><a href=\"data:text/html;base64,PHNjcmlwdD4=\"/></svg>",
            'data svg in an image' => "<svg $ns><image href=\"data:image/svg+xml;base64,PHN2Zz4=\"/></svg>",
            'web address on an image' => "<svg $ns><image href=\"https://example.com/track.png\"/></svg>",
            'web address on use' => "<svg $ns><use href=\"https://example.com/a.svg#x\"/></svg>",
            'web address in a fill' => "<svg $ns><rect fill=\"url(https://example.com/a.svg#p)\"/></svg>",
            'style that imports' => "<svg $ns><style>@import url(https://example.com/a.css);</style></svg>",
            'style that loads' => "<svg $ns><style>.a{background:url('https://example.com/a.png')}</style></svg>",
            'style attribute that loads' => "<svg $ns><rect style=\"fill:url(https://example.com/a.svg#p)\"/></svg>",
            'style with an expression' => "<svg $ns><rect style=\"width:expression(alert(1))\"/></svg>",
            'entity definition' => "<?xml version=\"1.0\"?><!DOCTYPE svg [<!ENTITY a \"b\">]><svg $ns/>",
            'entity definition, odd case' => "<!DOCTYPE svg [<!entity a \"b\">]><svg $ns/>",
            'doctype with an internal subset' => "<!DOCTYPE svg [<!ATTLIST svg x CDATA \"1\">]><svg $ns/>",
            'stylesheet instruction' => "<?xml-stylesheet href=\"https://example.com/a.css\"?><svg $ns/>",
            'element in an unknown namespace' => "<svg $ns><x:thing xmlns:x=\"https://example.com/ns\"/></svg>",
            'attribute in an unknown namespace' => "<svg $ns xmlns:x=\"https://example.com/ns\" x:y=\"1\"/>",
            'not xml' => "<svg $ns><g></svg>",
            'not svg' => "<html xmlns=\"http://www.w3.org/1999/xhtml\"/>",
            'svg without its namespace' => '<svg><rect/></svg>',
            'empty' => '',
        ];
        foreach ($files as $label => $svg) {
            $this->assertNotNull(svg_validator::find_problem($svg), "Accepted: $label");
        }
    }

    /**
     * A file over the limit is refused without being parsed.
     */
    public function test_oversized_file_is_refused(): void {
        $svg = '<svg ' . self::NS . '><!-- ' . str_repeat('x', svg_validator::MAX_BYTES) . ' --></svg>';
        $this->assertSame('too large', svg_validator::find_problem($svg));
    }

    /**
     * Deep nesting does not crash the check: it is refused or accepted, but it finishes.
     */
    public function test_deeply_nested_file_finishes(): void {
        $svg = '<svg ' . self::NS . '>' . str_repeat('<g>', 200) . str_repeat('</g>', 200) . '</svg>';
        $this->assertNull(svg_validator::find_problem($svg));
        $deeper = '<svg ' . self::NS . '>' . str_repeat('<g>', 5000) . str_repeat('</g>', 5000) . '</svg>';
        $this->assertNotNull(svg_validator::find_problem($deeper), 'The XML parser limits depth, so this is not well formed.');
    }
}
