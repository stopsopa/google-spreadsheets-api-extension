<?php

namespace Stopsopa\GoogleSpreadsheets\Utils;

// http://forum.jedox.com/index.php/Thread/4549-How-to-refer-to-a-cell-in-R1C1-style/
// g(How to refer to a cell in R1C1 style jedox)
class CellConverter {
    public static function toLetter($number)
    {
        $c = 0;
        for ($let = 'A' ; $let <= 'ZZZZZ' ; ++$let) {
            $c += 1;
            if ($number === $c) {
                return $let;
            }
        }
    }
    public static function toNumber($letter)
    {
        $n = 0;
        for ($let = 'A' ; $let < 'ZZZZZ' ; ++$let) {
            $n += 1;
            if ($let === $letter) {
                return $n;
            }
        }
    }

    public static function anyToRC($cell) {

        $cell = strtoupper($cell);

        preg_match('#^R(\d+)C(\d+)$#', $cell, $m);

        if (count($m) === 3) {
            return array(
                'r' => intval($m['1']),
                'c' => intval($m['2'])
            );
        }

        preg_match('#^([A-Z]+)(\d+)$#', $cell, $m);

        if (count($m) === 3) {
            return array(
                'r' => intval($m['2']),
                'c' => CellConverter::toNumber($m['1'])
            );
        }

        throw new Exception("Wrong decomposition of cell literal '$cell'");
    }
}