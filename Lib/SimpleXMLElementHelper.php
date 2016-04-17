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
 * @package Stopsopa\UtilsBundle\Lib
 */
class SimpleXMLElementHelper {
    /**
     * Because working with pure SimpleXMLElement is really shitty
     * source: http://php.net/manual/en/class.simplexmlelement.php#111394
     */
    public static function normalize(SimpleXMLElement $xml, $force = false, $addNative = false, $ns = null) {

        if (is_null($ns)) {
            $ns = $xml->getNamespaces(true);
        }

        $nsc = (bool)count($ns);

        $obj = new StdClass();

        $obj->name = $xml->getName();

        $text = trim((string)$xml);
        $attributes = array();
        $children = array();

        if ($addNative) {
            $obj->native = $xml;
        }

        foreach($xml->attributes() as $k => $v) {
            $attributes[$k]  = (string)$v;
        }

        foreach($xml->children() as $k => $v) {
            $children[] = static::normalize($v, $force);
        }

        if($force or $text !== '')
            $obj->text = $text;

        if($force or count($attributes) > 0)
            $obj->attributes = $attributes;

        if($force or count($children) > 0)
            $obj->children = $children;

        if ($nsc) {

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
                $obj->nstags = $nstags;
            }
        }

        return $obj;
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

//        $errors = libxml_get_errors();

        libxml_clear_errors();

        libxml_use_internal_errors($libxml_previous_state);

        return array(
            'xml'       => static::normalize($xml, $force, $addNative),
//            'errors'    => $errors ?: array()
        );
    }
}