<?php

namespace Stopsopa\GoogleSpreadsheets\Services;
use Exception;
use Google_Http_Request;
use Google_Client;
use Google_Auth_AssertionCredentials;
use Stopsopa\GoogleSpreadsheets\Lib\SimpleXMLElementHelper;
use Stopsopa\GoogleSpreadsheets\Utils\CellConverter;

/**
 * Class GoogleSpreadsheets
 * @package Stopsopa\GoogleSpreadsheets\Services
 * based on
 *  https://developers.google.com/api-client-library/php/guide/aaa_overview
 *  https://developers.google.com/google-apps/spreadsheets/worksheets#create_a_spreadsheet
 *  https://developers.google.com/drive/v2/reference/files/insert
 *  https://developers.google.com/gdata/docs/1.0/reference
 *
 *  also
 *  http://blog.evantahler.com/blog/curl-your-way-into-the-google-analytics-api.html
 */
class GoogleSpreadsheetsList {
    protected $nextLine;
    protected $headers;
    protected $flipHeaders;
    protected $key;
    protected $wid;
    /**
     * @var GoogleSpreadsheets
     */
    protected $service;
    public function __construct(GoogleSpreadsheets $service, $key, $wid) {

        $this->service = $service;

        $this->key = $key;

        $this->wid = $wid;

        $this->headers = array();
    }
    protected function _init() {

        if ( ! $this->nextLine ) {
            $data = $this->service->findWorksheetData($this->key, $this->wid);

            $last = 0;

            $headers = true;
            foreach ($data as $row) {

                if ($headers && $row['row'] === 1) {
                    if ($row['inputValue']) {
                        $this->headers[$row['col']] = $row['inputValue'];
                    }
                    else {
                        $headers = false;
                    }
                }

                if ($row['row'] > $last) {
                    $last = $row['row'];
                }
            }

            $this->nextLine = $last + 1;

            if (!count($this->headers)) {
                throw new Exception("Header row is not defined in worksheet (key: {$this->key}, wid: {$this->wid})");
            }

            $this->flipHeaders = array_flip($this->headers);
        }
    }
    public function add($data, $row = null) {

        $this->_init();

        $r = $row ? $row : $this->nextLine;

        $tmp = array();

        foreach ($this->flipHeaders as $name => $col) {
            if (array_key_exists($name, $data)) {
                $tmp["R{$r}C$col"] = $data[$name];
            }
        }

        $data = $this->service->update($this->key, $this->wid, $tmp);

        if (is_null($row) && $data['status'] === 200) {
            $this->nextLine += 1;
        }

        return $data;
    }

    /**
     * @param $row - row index (indexed from 1)
     * @param $data - array where key is name of column an value is data to write in cell
     * @throws Exception
     */
    public function update($row, $data) {
        return $this->add($data, $row);
    }

    /**
     * @param int|array $filters -
     *          int: num of row (1 indexed) if one row needed
     *          array of filters (min-row, max-row - both inclusive) if many rows needed
     * @throws Exception
     */
    public function get($filters = null) {

        $this->_init();

        if (is_null($filters)) {
            $filters = array();
        }

        if (!is_array($filters)) {
            $filters = array(
                'min-row' => $filters,
                'max-row' => $filters
            );
        }

        if (empty($filters['min-row']) || $filters['min-row'] < 2) {
            $filters['min-row'] = 2;
        }

        $filters['max-col'] = count($this->headers);

        $data = $this->service->findWorksheetData($this->key, $this->wid, false, $filters);

        $tmp = array();

        $i = $filters['min-row'];

        $cond = true;

        while ($cond) {

            $test = null;

            $row = array();

            foreach ($this->flipHeaders as $name => $in) {

                $key = "R{$i}C{$in}";

                if (array_key_exists($key, $data)) {

                    if (!$test && $data[$key]['val']) {
                        $test = $data[$key]['val'];
                    }

                    $row[$name] = $data[$key];
                }
                else {
                    $row[$name] = null;
                }
            }

            if ($test) {
                $tmp[$i] = $row;
                $i += 1;
            }
            else {
                $cond = false;
            }
        }

        return $tmp;
    }
}