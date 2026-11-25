<?php

namespace OfxParser;

use SimpleXMLElement;

/**
 * An OFX parser library
 *
 * Heavily refactored from Guillaume Bailleul's grimfor/ofxparser
 *
 * @author Guillaume BAILLEUL <contact@guillaume-bailleul.fr>
 * @author James Titcumb <hello@jamestitcumb.com>
 * @author Oliver Lowe <mrtriangle@gmail.com>
 */
class Parser
{
    /**
     * Factory to extend support for OFX document structures.
     * @param SimpleXMLElement $xml
     * @return Ofx
     */
    protected function createOfx(SimpleXMLElement $xml)
    {
        return new Ofx($xml);
    }

    /**
     * Load an OFX file into this parser by way of a filename
     *
     * @param string $ofxFile A path that can be loaded with file_get_contents
     * @return Ofx
     * @throws \InvalidArgumentException
     */
    public function loadFromFile($ofxFile)
    {
        if (!file_exists($ofxFile)) {
            throw new \InvalidArgumentException("File '{$ofxFile}' could not be found");
        }

        return $this->loadFromString(file_get_contents($ofxFile));
    }

    /**
     * Load an OFX by directly using the text content
     *
     * @param string $ofxContent
     * @return  Ofx
     */
    public function loadFromString($ofxContent)
    {
        $ofxContent = str_replace(["\r\n", "\r"], "\n", $ofxContent);
        $ofxContent = mb_convert_encoding($ofxContent, 'UTF-8', 'ISO-8859-1');

        $sgmlStart = stripos($ofxContent, '<OFX>');
        $ofxHeader =  trim(substr($ofxContent, 0, $sgmlStart));
        $header = $this->parseHeader($ofxHeader);

        $ofxSgml = trim(substr($ofxContent, $sgmlStart));
        if (stripos($ofxHeader, '<?xml') === 0) {
            $ofxXml = $ofxSgml;
        } else {
            $ofxXml = $this->convertSgmlToXml($ofxSgml);
        }

        $xml = $this->xmlLoadString($ofxXml);

        $ofx = $this->createOfx($xml);
        $ofx->buildHeader($header);

        return $ofx;
    }

    /**
     * Load an XML string without PHP errors - throws exception instead
     *
     * @param string $xmlString
     * @throws \RuntimeException
     * @return \SimpleXMLElement
     */
    private function xmlLoadString($xmlString)
    {
        libxml_clear_errors();
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlString);

        if ($errors = libxml_get_errors()) {
            throw new \RuntimeException('Failed to parse OFX: ' . var_export($errors, true));
        }

        return $xml;
    }

    /**
     * Parse the SGML Header to an Array
     *
     * @param string $ofxHeader
     * @param int $sgmlStart
     * @return array
     */
    private function parseHeader($ofxHeader)
    {
        $header = [];


        $ofxHeader = trim($ofxHeader);
        // Remove empty new lines.
        $ofxHeader = preg_replace('/^\n+/m', '', $ofxHeader);

        // Check if it's an XML file (OFXv2)
        if(preg_match('/^<\?xml/', $ofxHeader) === 1) {
            // Only parse OFX headers and not XML headers.
            $ofxHeader = preg_replace('/<\?xml .*?\?>\n?/', '', $ofxHeader);
            $ofxHeader = preg_replace(['/"/', '/\?>/', '/<\?OFX/i'], '', $ofxHeader);

            // <? // The syntax parser is confused by the regex above... this unconfuses it.

            $ofxHeaderLine = explode(' ', trim($ofxHeader));

            foreach ($ofxHeaderLine as $value) {
                $tag = explode('=', trim($value));
                $header[$tag[0]] = $tag[1];
            }

            return $header;
        }

        $ofxHeaderLines = explode("\n", $ofxHeader);
        foreach ($ofxHeaderLines as $value) {
            $tag = explode(':', trim($value));
            $header[$tag[0]] = $tag[1];
        }

        return $header;
    }

    /**
     * Convert an SGML to an XML string
     *
     * @param string $sgml
     * @return string
     */
    private function convertSgmlToXml($sgml)
    {
        // Add line breaks before all tags to fix parse errors
        $sgml = preg_replace('!<(([^/][A-Za-z0-9.]*)|(/[A-Za-z0-9.]+))>!', "\n<\\1>", $sgml);

        // Turn all special characters into ampersand?
        $sgml = preg_replace('/&(?!#?[a-z0-9]+;)/', '&amp;', $sgml);

        $lines = explode("\n", $sgml);
        $tags = [];

        foreach ($lines as $i => &$line) {
            $line = trim($line) . "\n";

            // Matches tags like <SOMETHING> or </SOMETHING>
            if (!preg_match("!^<(/?[A-Za-z0-9.]+)>(.*)$!", trim($line), $matches)) {
                continue;
            }

            // If matches </SOMETHING>, looks back and closes all unmatched tags like
            // <OTHERTHING>VAL to <OTHERTHING>VAL</OTHERTHING> until finds the opening tag <SOMETHING>
            if ($matches[1][0] == '/') { // If a closing tag...
                $tag = substr($matches[1], 1);

                while (($last = array_pop($tags)) && $last[1] != $tag) {
                    $lines[$last[0]] = "<{$last[1]}>{$last[2]}</{$last[1]}>";
                }
            } else {
                $tags[] = [$i, $matches[1], $matches[2]];
            }
        }

        // Clean up by closing any remaining tags
        if ($tags) {
            while ($last = array_pop($tags)) {
                $lines[] = "</{$last[1]}>";
            }
        }

        // Jam all our corrected lines into one happy line again!
        // Then break out open tags for more readable/testable XML
        $ret = implode('', array_map('trim', $lines));
        $ret = trim(preg_replace('!<([^/][A-Za-z0-9.]*)>!', "\n<\\1>", $ret));

        // Finally, break out multiple close tags on the same line
        while (preg_match('!(</[A-Za-z0-9.]+>){2}!', $ret) === 1) {
            $ret = preg_replace('!(</[A-Za-z0-9.]+>)(</[A-Za-z0-9.]+>)!', "\\1\n\\2", $ret);
        }

        return $ret;
    }
}
