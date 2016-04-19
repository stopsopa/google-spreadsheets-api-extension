<?php

namespace Stopsopa\GoogleSpreadsheets\Lib;
use SimpleXMLElement;
use Exception;
use stdClass;

/**
 * Read more:
 *   http://www.w3schools.com/xml/xml_namespaces.asp
 *   http://twigstechtips.blogspot.com/2011/01/php-parsing-simplexml-nodes-with.html
 * Class SimpleXMLElementHelper
 *
 * Tip:
 *   Using SimpleXMLElement there is no method to check if tag is empty <div/>
 *   or it is build from opening and closed tags <div></div>.
 *   This behaviour leads to conclusion that SimpleXMLElement
 *   is good only to traverse data from xml not xml structure itself
 *   So use this library keeping in mind this conslusion.
 *
 * @package Stopsopa\UtilsBundle\Lib
 */
class SimpleXMLElementHelper {
    /**
     * Because working with pure SimpleXMLElement is really shitty
     * source: http://php.net/manual/en/class.simplexmlelement.php#111394
     */
    public static function normalize(SimpleXMLElement $xml, $force = false, $addNative = false, $ns = null) {

        $r = array();

        $r['name'] = $xml->getName();

        $r['text'] = (string)$xml;

        $children = array();

        if ($addNative) {
            $r['native'] = $xml;
        }

        $attributes = array();
        foreach($xml->attributes() as $k => $v) {
            $attributes[]  = array(
                'name' => $k,
                'val' => (string)$v
            );
        }

        if($force or count($attributes) > 0)
            $r['attributes'] = $attributes;


        foreach($xml->children() as $k => $v) {
            $children[] = static::normalize($v, $force);
        }

        if($force or count($children) > 0)
            $r['children'] = $children;


        if (is_null($ns)) {
            $ns = $xml->getNamespaces(true);
        }

        if (count($ns)) {

            $nstags = array();

            foreach ($ns as $name => $url) {

                $tmp = array();

                foreach ($xml->children($url) as $xmlx) {

                    $t = static::normalize($xmlx, $force, $addNative, $ns);

                    if ($t) {
                        $tmp[] = $t;
                    }
                }

                if (count($tmp)) {
                    $nstags[$name] = $tmp;
                }
            }

            if (count($nstags)) {
                $r['nstags'] = $nstags;
            }

            // nsattrs

            $nsattrs = array();

            foreach ($ns as $name => $url) {

                $tmp = array();

                foreach ($xml->attributes($url) as $name => $val) {

                    $tmp[] = array(
                        'name' => $name,
                        'val' => (string)$val
                    );
                }

                if (count($tmp)) {
                    $nsattrs[$name] = $tmp;
                }
            }

            if (count($nsattrs)) {
                $r['nsattrs'] = $nsattrs;
            }
        }

        return $r;
    }
    public static function parseFile($file, $force = false, $addNative = false) {

        if (!file_exists($file)) {
            throw new Exception("File '$file' doesn't exists");
        }

        if (!is_readable($file)) {
            throw new Exception("File '$file' is not readdable");
        }

        return static::parseString(file_get_contents($file), $force, $addNative);
    }

    /**
     * @param $xml
     * @param bool $force set to true to always create 'text', 'attribute', and 'children' even if empty
     * @param bool $addNative, add native SimpleXMLElement in each node of returned array under key 'native'
     * @param null $getRidOfNamespaces, here it is possible to implement function
     *                                  to remove namepsaces attributes from input xml
     * @return array
     */
    public static function parseString($xml, $force = false, $addNative = false) {

        $libxml_previous_state = libxml_use_internal_errors(true);

        /* @var $xml SimpleXMLElement */
        $xml = new SimpleXMLElement($xml);

        $errors = libxml_get_errors();

        libxml_clear_errors();

        libxml_use_internal_errors($libxml_previous_state);

        return array(
            'xml'       => static::normalize($xml, $force, $addNative),
            'errors'    => $errors ?: array()
        );
    }
}