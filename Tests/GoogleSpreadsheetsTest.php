<?php

namespace Stopsopa\GoogleSpreadsheets\Services;
use PHPUnit_Framework_TestCase;
use Stopsopa\GoogleSpreadsheets\Services\GoogleSpreadsheets;

class GoogleSpreadsheetsTest extends PHPUnit_Framework_TestCase {
    protected function _getSAI() {
        return getenv('SAI');
    }
    protected function _getKeyFile() {
        return __DIR__.'/key.p12';
    }
    static $service;
    protected function _getService() {

        if (!static::$service) {
            $clientSecret = $this->_getKeyFile();

            $client_email = $this->_getSAI();

            $service = new GoogleSpreadsheets($clientSecret, $client_email);

            static::$service = $service;
        }

        return static::$service;
    }
    public function testConnection() {

        $service = $this->_getService();

        // list spreadsheets
        $data = $service->api('/feeds/spreadsheets/private/full?alt=json');

        // list worksheets
        // $data = $service->findWorksheets('1mgiEx..RaVPk');

        // lista worksheets
        //$data = $service->api("/feeds/worksheets/$key/private/basic?alt=json");

        //$data = $service->api("/feeds/worksheets/$key/private/basic?alt=json");

        //print_r($data);


//        $data = $service->api("/feeds/cells/1mgiE...RaVPk/o3rt4hz/private/basic?alt=json");
        fwrite(STDOUT, print_r($data['feed']['entry'][0]['title'], true));


    }
}