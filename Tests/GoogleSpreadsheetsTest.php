<?php

namespace Stopsopa\GoogleSpreadsheets\Services;
use PHPUnit_Framework_TestCase;
use Stopsopa\GoogleSpreadsheets\Lib\SimpleXMLElementHelper;
use Stopsopa\GoogleSpreadsheets\Services\GoogleSpreadsheets;
use Google_Service_Drive_DriveFile;
use Google_Service_Drive;
use Google_Service_Drive_ParentReference;
use Exception;
use Stopsopa\GoogleSpreadsheets\Lib\UtilArray;

class GoogleSpreadsheetsTest extends PHPUnit_Framework_TestCase {
    /**
     * All tests will use this directory
     * @var string
     */
    static $workingDirId = '0B27fFwZPLbgxcGdFT1Y4XzFYdXc';
    protected function _getStorageFile() {
        return __DIR__.'/storage.json';
    }
    protected function _getStorage() {
        $file = $this->_getStorageFile();
        return file_exists($file) ? (json_decode(file_get_contents($file), true) ?: array()) : array();
    }
    protected function storage($key, $value = null) {

        $storage = $this->_getStorage();

        if ($value !== null) {

            $storage[$key] = $value;

            $file = $this->_getStorageFile();

            if (file_exists($file)) {
                unlink($file);
            }

            file_put_contents($file, json_encode($storage), FILE_APPEND);
        }

        if (array_key_exists($key, $storage)) {
            return $storage[$key];
        }
    }
    protected function findFirstWorksheet() {

        $service = $this->_getService();

        $key = $this->storage('key');

        $list = $service->findWorksheets($key);

        return $list[0]['id'];
    }

    protected function _getSAI() {
        return getenv('SAI');
    }
    protected function _getKeyFile() {
        return __DIR__.'/key.p12';
    }

    /**
     * @var GoogleSpreadsheets
     */
    protected $service;
    protected function _getService() {

        if (!$this->service) {

            $clientSecret = $this->_getKeyFile();

            $client_email = $this->_getSAI();

            $service = new GoogleSpreadsheets();

            $service->setupByServiceAccountKey($clientSecret, $client_email);

            $this->service = $service;
        }

        return $this->service;
    }
    protected function _getDriveService() {
        return new Google_Service_Drive($this->_getService()->getClient());
    }
    /**
     * https://developers.google.com/drive/v3/web/folder#creating_a_folder
     * Uwaga czasem nie zgadzają się metody z tego api z tymi które są w google/apiclient
     * np manual każe używać ->create) a jest ->insert(
     */
    public function testCreateDirectory() {

        $title = date("Y-m-d-H-i").'-s-travis-ci-build-'.preg_replace('#[^a-z\d]#i', '-', phpversion());

        $title = preg_replace('#--+#', '-', $title);

        $file = new Google_Service_Drive_DriveFile();

        $file->setTitle($title);

        // list of mime types: https://developers.google.com/drive/v3/web/mime-types?hl=pl
        // g(Drive REST API Supported MIME Types)
        $file->setMimeType('application/vnd.google-apps.folder');

        // parent dir
        $parent = new Google_Service_Drive_ParentReference();
        $parent->setId(static::$workingDirId);
        $file->setParents(array($parent));

        $file = $this->_getDriveService()->files->insert($file, [
            'fields' => 'id'
        ]);

        $this->storage('dir', $file->id);

        $this->assertTrue(strlen($file->id) > 0);

        return $file->id;
    }
    public function testCreateSpreadSheet() {

        $title = 'testSpreadSheet';

        $file = new Google_Service_Drive_DriveFile();
        $file->setTitle($title);
        $file->setMimeType('application/vnd.google-apps.spreadsheet');

        // parent dir
        $parent = new Google_Service_Drive_ParentReference();
        $parent->setId($this->storage('dir'));
        $file->setParents(array($parent));

        $file = $this->_getDriveService()->files->insert($file, [
            'fields' => 'id'
        ]);

        $this->assertTrue(strlen($file->id) > 0);

        $this->storage('key', $file->id);
    }
    public function testFindWorksheets() {

        $service = $this->_getService();

        $key = $this->storage('key');

        $data = $service->findWorksheets($key, true);

        $this->assertTrue(count($data['extra']) > 0);
    }
    public function testCreateWorkSheets() {

        $service = $this->_getService();

        $key = $this->storage('key');

        $this->assertTrue(strlen($key) > 0, "No file key in storage");

        $service->createWorkSheet($key, 'First', 20, 20);

        $service->createWorkSheet($key, 'Second', 20, 20);

        $service->createWorkSheet($key, 'Third', 20, 20);

        $data = $service->findWorksheets($key);

        $this->assertTrue(count($data) === 4);
    }
    public function testRmoveWorksheet() {

        $service = $this->_getService();

        $key = $this->storage('key');

        $wid = $this->findFirstWorksheet($key);

        $service->deleteWorksheet($key, $wid);

        $data = $service->findWorksheets($key);

        $this->assertTrue(count($data) === 3);

    }
    public function testRenameWorksheet() {

        $service = $this->_getService();

        $key = $this->storage('key');

        $wid = $this->findFirstWorksheet($key);

        $olddata = $service->getWorksheetMetadata($key, $wid);

        $newname = 'changednameowforksheet ąśżźćęóńł';

        $service->updateWorksheetMetadata($key, $wid, $newname);

        $newdata = $service->getWorksheetMetadata($key, $wid);

        $this->assertNotEquals($olddata['title']['$t'], $newdata['title']['$t'], 'Names of worksheet are equal, but they should not be');

        $this->assertEquals($newname, $newdata['title']['$t'], 'Name of worksheet has not changed');
    }

    /**
     * https://developers.google.com/google-apps/spreadsheets/data#update_multiple_cells_with_a_batch_request
     */
    public function testUpdateByBatch() {
        // https://developers.google.com/google-apps/spreadsheets/data#update_multiple_cells_with_a_batch_request

        $service = $this->_getService();

        $key = $this->storage('key');

        $wid = $this->findFirstWorksheet($key);

        $service->update($key, $wid, array(
            'D3' => '1',
            'D4' => '2',
            'D5' => '=SUM(D3:D4)'
        ));
    }
    public function testValesOfD5() {

        $service = $this->_getService();

        $key = $this->storage('key');

        $wid = $this->findFirstWorksheet($key);

        $result = $service->findWorksheetData($key, $wid);

        $expected = json_decode(<<<end
{
  "R3C4": {
    "a1": "D3",
    "col": 4,
    "inputValue": "1",
    "numericValue": "1.0",
    "row": 3,
    "val": "1"
  },
  "R4C4": {
    "a1": "D4",
    "col": 4,
    "inputValue": "2",
    "numericValue": "2.0",
    "row": 4,
    "val": "2"
  },
  "R5C4": {
    "a1": "D5",
    "col": 4,
    "inputValue": "=SUM(R[-2]C[0]:R[-1]C[0])",
    "numericValue": "3.0",
    "row": 5,
    "val": "3.0"
  }
}
end
, true);

        $result = UtilArray::sortKeysRecursive($result);
        $result = json_encode($result);

        $expected = UtilArray::sortKeysRecursive($expected);
        $expected = json_encode($expected);

        $this->assertSame($expected, $result, "Written data are not the same as readed");
    }
    public function testFindFirstFreeRowForData() {

        $service = $this->_getService();

        $key = $this->storage('key');

        $wid = $this->findFirstWorksheet($key);

        $last = $service->findFirstFreeRowForData($key, $wid);

        $this->assertSame($last, 6, "Ostatni wiersz nie jest prawidłowy");
    }
    public function testGetSpecificRowsAndCollumns() {

        $service = $this->_getService();

        $key = $this->storage('key');

        $wid = $this->findFirstWorksheet($key);

        $result = $service->findWorksheetData($key, $wid, false, array(
            'max-col' => 4,
            'max-row' => 3
        ));

        $expected = json_decode(<<<end
{
  "R3C4" : {
    "a1": "D3",
    "col": 4,
    "inputValue": "1",
    "numericValue": "1.0",
    "row": 3,
    "val": "1"
  }
}
end
, true);

        $result = UtilArray::sortKeysRecursive($result);
        $result = json_encode($result);

        $expected = UtilArray::sortKeysRecursive($expected);
        $expected = json_encode($expected);

        $this->assertSame($expected, $result, "Getting data by ranges doesn't work");
    }
    public function testChangeSizeOfWorksheet() {

        $service = $this->_getService();

        $key = $this->storage('key');

        $wid = $this->findFirstWorksheet($key);

        $rows = 40;

        $cols = 41;

        $service->updateWorksheetMetadata($key, $wid, null, $rows, $cols);

        $data = $service->getWorksheetMetadata($key, $wid);

        $rowsr = $data['gs$rowCount']['$t'];

        $colsr = $data['gs$colCount']['$t'];

        $this->assertEquals($rowsr, $rows, "Number of expected rows ($rows) doesn't match to real number of rows ($rowsr)");

        $this->assertEquals($colsr, $cols, "Number of expected columns ($cols) doesn't match to real number of columns ($colsr)");
    }
    public function testWriteOutOfRange() {

        $service = $this->_getService();

        $key = $this->storage('key');

        $wid = $this->findFirstWorksheet($key);

        $xml = $service->update($key, $wid, array(
            'R22C220' => 'outofrange'
        ));

        $this->assertEquals($xml['data']['R22C220']['status'], 400, "Status should be 400");
    }
    public function testCreateList() {

        $service = $this->_getService();

        $key = $this->storage('key');

        $wlist = $service->findWorksheets($key);

        $list = $wlist[1]['id'];

        $this->storage('list', $list);

//            'F1' => "Łódź space" // in fetched result there will be no spaces, key will be 'Łódźspace'
        $service->update($key, $list, array(
            'A1' => "Name",
            'B1' => "Surname",
            'C1' => "Age",
            'D1' => "Weight",
            'E1' => "Height",
        ));

//            'F2' => "Coś"
        $service->update($key, $list, array(
            'A2' => "John",
            'B2' => "Smith",
            'C2' => "31",
            'D2' => "80",
            'E2' => "180",
        ));
    }
    public function testListAdd() {

        $service = $this->_getService();

        $key = $this->storage('key');

        $list = $this->storage('list');

        $list = $service->getList($key, $list);

        $result = $list->add(array(
            "Name"      => 'nametest',
            "Surname"   => 'surnametest',
            "Age"       => 'agetest',
            "Weight"    => 'weighttest',
            "Height"    => 'heighttest'
        ));

        $expected = array (
            'data' =>
                array (
                    'R3C1' =>
                        array (
                            'a1' => 'A3',
                            'col' => 1,
                            'inputValue' => 'nametest',
                            'numericValue' => NULL,
                            'reason' => 'Success',
                            'row' => 3,
                            'status' => 200,
                        ),
                    'R3C2' =>
                        array (
                            'a1' => 'B3',
                            'col' => 2,
                            'inputValue' => 'surnametest',
                            'numericValue' => NULL,
                            'reason' => 'Success',
                            'row' => 3,
                            'status' => 200,
                        ),
                    'R3C3' =>
                        array (
                            'a1' => 'C3',
                            'col' => 3,
                            'inputValue' => 'agetest',
                            'numericValue' => NULL,
                            'reason' => 'Success',
                            'row' => 3,
                            'status' => 200,
                        ),
                    'R3C4' =>
                        array (
                            'a1' => 'D3',
                            'col' => 4,
                            'inputValue' => 'weighttest',
                            'numericValue' => NULL,
                            'reason' => 'Success',
                            'row' => 3,
                            'status' => 200,
                        ),
                    'R3C5' =>
                        array (
                            'a1' => 'E3',
                            'col' => 5,
                            'inputValue' => 'heighttest',
                            'numericValue' => NULL,
                            'reason' => 'Success',
                            'row' => 3,
                            'status' => 200,
                        ),
                ),
            'status' => 200,
        );

        $result = UtilArray::sortKeysRecursive($result);
        $result = json_encode($result);

        $expected = UtilArray::sortKeysRecursive($expected);
        $expected = json_encode($expected);

        $this->assertSame($expected, $result, "Getting data by ranges doesn't work");
    }
    public function testListGet() {

        $service = $this->_getService();

        $key = $this->storage('key');

        $list = $this->storage('list');

        $l = $service->getList($key, $list);

        $result = $l->get();

        $expected = array (
            2 =>
                array (
                    'Name' =>
                        array (
                            'col' => 1,
                            'row' => 2,
                            'inputValue' => 'John',
                            'numericValue' => NULL,
                            'a1' => 'A2',
                            'val' => 'John',
                        ),
                    'Surname' =>
                        array (
                            'col' => 2,
                            'row' => 2,
                            'inputValue' => 'Smith',
                            'numericValue' => NULL,
                            'a1' => 'B2',
                            'val' => 'Smith',
                        ),
                    'Age' =>
                        array (
                            'col' => 3,
                            'row' => 2,
                            'inputValue' => '31',
                            'numericValue' => '31.0',
                            'a1' => 'C2',
                            'val' => '31',
                        ),
                    'Weight' =>
                        array (
                            'col' => 4,
                            'row' => 2,
                            'inputValue' => '80',
                            'numericValue' => '80.0',
                            'a1' => 'D2',
                            'val' => '80',
                        ),
                    'Height' =>
                        array (
                            'col' => 5,
                            'row' => 2,
                            'inputValue' => '180',
                            'numericValue' => '180.0',
                            'a1' => 'E2',
                            'val' => '180',
                        ),
                ),
            3 =>
                array (
                    'Name' =>
                        array (
                            'col' => 1,
                            'row' => 3,
                            'inputValue' => 'nametest',
                            'numericValue' => NULL,
                            'a1' => 'A3',
                            'val' => 'nametest',
                        ),
                    'Surname' =>
                        array (
                            'col' => 2,
                            'row' => 3,
                            'inputValue' => 'surnametest',
                            'numericValue' => NULL,
                            'a1' => 'B3',
                            'val' => 'surnametest',
                        ),
                    'Age' =>
                        array (
                            'col' => 3,
                            'row' => 3,
                            'inputValue' => 'agetest',
                            'numericValue' => NULL,
                            'a1' => 'C3',
                            'val' => 'agetest',
                        ),
                    'Weight' =>
                        array (
                            'col' => 4,
                            'row' => 3,
                            'inputValue' => 'weighttest',
                            'numericValue' => NULL,
                            'a1' => 'D3',
                            'val' => 'weighttest',
                        ),
                    'Height' =>
                        array (
                            'col' => 5,
                            'row' => 3,
                            'inputValue' => 'heighttest',
                            'numericValue' => NULL,
                            'a1' => 'E3',
                            'val' => 'heighttest',
                        ),
                ),
        );

        $result = UtilArray::sortKeysRecursive($result);
        $result = json_encode($result);

        $expected = UtilArray::sortKeysRecursive($expected);
        $expected = json_encode($expected);

        $this->assertSame($expected, $result, "Getting data by ranges doesn't work");

        $result     = $l->get(3);

        $expected = array (
            3 =>
                array (
                    'Name' =>
                        array (
                            'col' => 1,
                            'row' => 3,
                            'inputValue' => 'nametest',
                            'numericValue' => NULL,
                            'a1' => 'A3',
                            'val' => 'nametest',
                        ),
                    'Surname' =>
                        array (
                            'col' => 2,
                            'row' => 3,
                            'inputValue' => 'surnametest',
                            'numericValue' => NULL,
                            'a1' => 'B3',
                            'val' => 'surnametest',
                        ),
                    'Age' =>
                        array (
                            'col' => 3,
                            'row' => 3,
                            'inputValue' => 'agetest',
                            'numericValue' => NULL,
                            'a1' => 'C3',
                            'val' => 'agetest',
                        ),
                    'Weight' =>
                        array (
                            'col' => 4,
                            'row' => 3,
                            'inputValue' => 'weighttest',
                            'numericValue' => NULL,
                            'a1' => 'D3',
                            'val' => 'weighttest',
                        ),
                    'Height' =>
                        array (
                            'col' => 5,
                            'row' => 3,
                            'inputValue' => 'heighttest',
                            'numericValue' => NULL,
                            'a1' => 'E3',
                            'val' => 'heighttest',
                        ),
                ),
        );

        $result = UtilArray::sortKeysRecursive($result);
        $result = json_encode($result);

        $expected = UtilArray::sortKeysRecursive($expected);
        $expected = json_encode($expected);

        $this->assertSame($expected, $result, "Getting data by ranges doesn't work");

        $result     = $l->get(4);

        $this->assertSame(json_encode($result), '[]', "Getting data by ranges doesn't work");
    }
//    public function testListGet2() {
//
//        $service = $this->_getService();
//
//        $key = $this->storage('key');
//
//        $list = $this->storage('list');
//
//        $data = $service->listGet($key, $list);
//
//        unset($data['data'][0]['id']);
//
//        unset($data['data'][0]['edit']);
//
////        "łódźspace": "Coś"
//        $expected = json_decode(<<<end
//{
//  "data": [
//    {
//      "data": {
//        "age": "31",
//        "height": "180",
//        "name": "John",
//        "surname": "Smith",
//        "weight": "80"
//      }
//    }
//  ],
//  "startIndex": 1,
//  "title": "Second",
//  "totalResults": 1
//}
//end
//,true);
//
//        UtilArray::sortKeysRecursive($data);
//
//        UtilArray::sortKeysRecursive($expected);
//
//        $expected   = json_encode($expected);
//
//        $data       = json_encode($data);
//
//        $this->assertSame($data, $expected, "Retrieved data from list is not the same as expected");
//    }
//    public function testListInsert() {
//
//        $service = $this->_getService();
//
//        $key = $this->storage('key');
//
//        $list = $this->storage('list');
//
//        $service->listInsert($key, $list, array(
//            'Name'          => 'Name test',
//            'Surname'       => 'Surname test',
//            'Age'           => 'Age test',
//            'Weight'        => 'Weight test',
//            'Height'        => 'Height test',
//        ));
////            'Łódź space'    => 'Łódź test',
//
//    }
}