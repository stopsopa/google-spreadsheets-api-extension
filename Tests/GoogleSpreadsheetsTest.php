<?php

namespace Stopsopa\GoogleSpreadsheets\Services;
use PHPUnit_Framework_TestCase;
use Stopsopa\GoogleSpreadsheets\Services\GoogleSpreadsheets;
use Google_Service_Drive_DriveFile;
use Google_Service_Drive;
use Google_Service_Drive_ParentReference;
use Exception;

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

        $service->createWorkSheet($key, 'First');

        $service->createWorkSheet($key, 'Second');

        $service->createWorkSheet($key, 'Third');

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

        $olddata = $service->getWorksheetData($key, $wid);

        $newname = 'changednameowforksheet ąśżźćęóńł';

        $service->renameWorksheet($key, $wid, $newname);

        $newdata = $service->getWorksheetData($key, $wid);

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
    /**
     * https://developers.google.com/google-apps/spreadsheets/data#retrieve_a_cell-based_feed
     */
    public function testGetCellsData() {

        $service = $this->_getService();

        $key = $this->storage('key');

        $wid = $this->findFirstWorksheet($key);

        $data = $service->getCellsData($key, $wid);

        // ..................
    }
    public function testGetSpecificRowsAndCollumns() {
        // https://developers.google.com/google-apps/spreadsheets/data#fetch_specific_rows_or_columns
    }
}