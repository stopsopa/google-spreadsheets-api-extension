<?php

namespace Stopsopa\GoogleSpreadsheets\Utils;
use Exception;

// http://forum.jedox.com/index.php/Thread/4549-How-to-refer-to-a-cell-in-R1C1-style/
// g(How to refer to a cell in R1C1 style jedox)
class CellConverter {
    public static function toLetter($number)
    {
        $number = (int)$number;

        if ($number < 1) {
            throw new Exception("Number is '$number' but can't be less then 1");
        }

        $c = 0;
        for ($let = 'A' ; $let <= 'ZZZ' ; ++$let) {
            $c += 1;
            if ($number === $c) {
                return $let;
            }
        }

        throw new Exception("$number is out of range");
    }
    public static function toNumber($letter)
    {
        $n = 0;
        for ($let = 'A' ; $let < 'ZZZ' ; ++$let) {
            $n += 1;
            if ($let === $letter) {
                return $n;
            }
        }

        throw new Exception("$letter is out of range");
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