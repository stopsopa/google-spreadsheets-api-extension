<?php

namespace Stopsopa\GoogleSpreadsheets\Lib;

/**
 * Stopsopa\GoogleSpreadsheets\Lib\UtilArray.
 */
class UtilArray
{
    public static function sortKeysRecursive($data, $param = 0, $reverse = false) {

        if (is_array($data)) {

            if ($reverse) {
                krsort($data, $param ?: SORT_REGULAR);
            }
            else if (is_callable($param)) {
                uksort($data, $param);
            }
            else {
                ksort($data, $param ?: SORT_REGULAR);
            }

            foreach ($data as &$d) {
                $d = static::sortKeysRecursive($d, $param, $reverse);
            }
        }

        return $data;
    }
}