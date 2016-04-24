<?php
namespace Stopsopa\GoogleSpreadsheets\Services;

use PHPUnit_Framework_TestCase;
use Stopsopa\GoogleSpreadsheets\Utils\CellConverter;
use Exception;

class CellConverterTest extends PHPUnit_Framework_TestCase {
//    public function testToLetter() {
//
//        $tmp = array();
//        for ($i = 1 ; $i < 300 ; $i += 1 ) {
//            $tmp[] = CellConverter::toLetter($i);
//        }
//
//        $expected = <<<end
//["A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U","V","W","X","Y","Z","AA","AB","AC","AD","AE","AF","AG","AH","AI","AJ","AK","AL","AM","AN","AO","AP","AQ","AR","AS","AT","AU","AV","AW","AX","AY","AZ","BA","BB","BC","BD","BE","BF","BG","BH","BI","BJ","BK","BL","BM","BN","BO","BP","BQ","BR","BS","BT","BU","BV","BW","BX","BY","BZ","CA","CB","CC","CD","CE","CF","CG","CH","CI","CJ","CK","CL","CM","CN","CO","CP","CQ","CR","CS","CT","CU","CV","CW","CX","CY","CZ","DA","DB","DC","DD","DE","DF","DG","DH","DI","DJ","DK","DL","DM","DN","DO","DP","DQ","DR","DS","DT","DU","DV","DW","DX","DY","DZ","EA","EB","EC","ED","EE","EF","EG","EH","EI","EJ","EK","EL","EM","EN","EO","EP","EQ","ER","ES","ET","EU","EV","EW","EX","EY","EZ","FA","FB","FC","FD","FE","FF","FG","FH","FI","FJ","FK","FL","FM","FN","FO","FP","FQ","FR","FS","FT","FU","FV","FW","FX","FY","FZ","GA","GB","GC","GD","GE","GF","GG","GH","GI","GJ","GK","GL","GM","GN","GO","GP","GQ","GR","GS","GT","GU","GV","GW","GX","GY","GZ","HA","HB","HC","HD","HE","HF","HG","HH","HI","HJ","HK","HL","HM","HN","HO","HP","HQ","HR","HS","HT","HU","HV","HW","HX","HY","HZ","IA","IB","IC","ID","IE","IF","IG","IH","II","IJ","IK","IL","IM","IN","IO","IP","IQ","IR","IS","IT","IU","IV","IW","IX","IY","IZ","JA","JB","JC","JD","JE","JF","JG","JH","JI","JJ","JK","JL","JM","JN","JO","JP","JQ","JR","JS","JT","JU","JV","JW","JX","JY","JZ","KA","KB","KC","KD","KE","KF","KG","KH","KI","KJ","KK","KL","KM"]
//end
//        ;
//        $result = json_encode($tmp);
//
//        $this->assertSame($expected, $result);
//
//        try {
//            CellConverter::toLetter(0);
//        }
//        catch (Exception $e) {
//            return $this->assertSame("Number is '0' but can't be less then 1", $e->getMessage());
//        }
//
//        $this->assertSame(true, false, 'Exception expected');
//    }
    /**
     * @expectedException Exception
     * @expectedExceptionMessage 475229 is out of range
     * @expectedExceptionCode null
     */
    public function testToLetterException() {
        CellConverter::toLetter(475228 + 1);
    }
    /**
     * @expectedException Exception
     * @expectedExceptionMessage Number is '0' but can't be less then 1
     * @expectedExceptionCode null
     */
    public function testToLetterLessException() {
        CellConverter::toLetter(0);
    }
    public function testToNumber() {

        for ($i = 1 ; $i < 300 ; $i += 1 ) {
            $this->assertSame($i, CellConverter::toNumber(CellConverter::toLetter($i)));
        }
    }
    /**
     * @expectedException Exception
     * @expectedExceptionMessage ZZZA is out of range
     * @expectedExceptionCode null
     */
    public function testToNumberException() {
        CellConverter::toNumber('ZZZA');
    }
    public function testAnyToRC() {

        for ($r = 15 ; $r < 35; $r += 1 ) {
            for ($c = 1 ; $c < 15 ; $c += 1 ) {

                $tmp = CellConverter::anyToRC(CellConverter::toLetter($c).$r);

                $this->assertSame($tmp['r'], $r);
                $this->assertSame($tmp['c'], $c);

                $rc = CellConverter::anyToRC('R'.$tmp['r'].'C'.$tmp['c']);

                $this->assertSame($rc['r'], $r);
                $this->assertSame($rc['c'], $c);
            }
        }

        $wrong = 'R45C67d';

        try {
            CellConverter::anyToRC($wrong);
        }
        catch (Exception $e) {
            return $this->assertSame("Wrong decomposition of cell literal '".strtoupper($wrong)."'", $e->getMessage());
        }

        $this->assertSame(true, false);
    }
}