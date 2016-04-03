<?php

namespace Stopsopa\GoogleSpreadsheets\Services;
use Exception;
use Google_Http_Request;
use Google_Client;
use Google_Auth_AssertionCredentials;

/**
 * Class GoogleSpreadsheets
 * @package Stopsopa\GoogleSpreadsheets\Services
 * based on
 *  https://developers.google.com/api-client-library/php/guide/aaa_overview
 *  https://developers.google.com/google-apps/spreadsheets/worksheets#create_a_spreadsheet
 *  https://developers.google.com/drive/v2/reference/files/insert
 */
class GoogleSpreadsheets {
    protected $scopes;
    /**
     * @var Google_Client
     */
    protected $client;
    const USER_AGENT = ' google-api-php-client/1.0.6-beta';
    public function __construct($scopes = null)
    {
        if (!$scopes) {
            $scopes = array(
                'https://www.googleapis.com/auth/drive', // from https://developers.google.com/drive/v2/web/scopes
                'https://spreadsheets.google.com/feeds'
            );
        }

        $this->scopes = $scopes;
    }
    protected function _isInitializationCheck() {
        if (!$this->client) {
            throw new Exception("Use one of methods setupByServiceAccountKey|setupByOauthClientId|setupByApiKey to initialize service first");
        }
    }
    public function setupByServiceAccountKey($p12_key_file_location, $client_email) {

        if (!file_exists($p12_key_file_location)) {
            throw new Exception("File '$p12_key_file_location' file doesn't exists");
        }

        if (!is_readable($p12_key_file_location)) {
            throw new Exception("File '$p12_key_file_location' is not readdable");
        }

        $private_key = file_get_contents($p12_key_file_location);

        $credentials = new Google_Auth_AssertionCredentials(
            $client_email,
            $this->scopes,
            $private_key
        );

        $client = new Google_Client();

        $client->setAssertionCredentials($credentials);

        $this->client = $client;

        return $this;
    }
    public function getClient() {

        $this->_isInitializationCheck();

        return $this->client;
    }
    public function setupByOauthClientId() {

    }
    public function setupByApiKey() {

    }
    public function api($feedurl, $method = 'GET', $data = '', $headers = array(), $returnJson = true) {

        if ($returnJson) {
            $feedurl .= '?alt=json';
        }

        $this->_isInitializationCheck();

        if ($this->client->getAuth()->isAccessTokenExpired()) {
            $this->client->getAuth()->refreshTokenWithAssertion();
        }

        if (!preg_match('#^https?://#', $feedurl)) {
            $feedurl = "https://spreadsheets.google.com$feedurl";
        }

        $request = new Google_Http_Request($feedurl);
        //$data = $this->client->execute($request);

        $this->client->getAuth()->authenticatedRequest($request);
        $request->setUserAgent(static::USER_AGENT);
        $request->enableGzip();

        $request->setRequestMethod($method);

        if ($data) {
            $request->setPostBody($data);
        }

        $request->maybeMoveParametersToBody();

        if (count($headers)) {
            $request->setRequestHeaders($headers);
        }

        /* @var $httpRequest Google_Http_Request */
        $httpRequest = $this->client->getIo()->makeRequest($request);

        if (!in_array(($code = $httpRequest->getResponseHttpCode()), [200, 201])) {
            throw new Exception("Wrong status code: ".$code. " response: ".json_encode($httpRequest->getResponseBody(), true));
        }


        if ($returnJson) {

            $data = json_decode($httpRequest->getResponseBody(), true);

            if ($data) {
                return $data;
            }
        }

        return $httpRequest->getResponseBody();
    }
    public function findFiles($rawResponse = false) {

        $data = $this->api('/feeds/spreadsheets/private/full');

        if ($rawResponse) {
            return $data;
        }

        $ret = array();

        foreach ($data['feed']['entry'] as $k) {
            $ret[] = [
                'key' => preg_replace('#^.*?/([^/]+)$#i', '$1', $k['id']['$t']),
                'title' => $k['title']['$t']
            ];
        }

        return $ret;
    }
    public function findByRegexpName($regexp) {
        $list = $this->findFiles();

        $ret = [];

        die(print_r($list));

        return $ret;
    }
    public function getWorksheetData($key, $wid) {

        $data = $this->findWorksheets($key, true);

        foreach ($data['extra'] as $index => $worksheet) {
            if ($worksheet['id'] === $wid) {
                return $data['feed']['entry'][$index];
            }
        }
    }
    public function findWorksheets($key, $rawResponse = false) {

        $data = $this->api("/feeds/worksheets/$key/private/full");

        $ret = array();

        foreach ($data['feed']['entry'] as $k) {
            $ret[] = [
                'title' => $k['title']['$t'],
                'id' => preg_replace('#^.*?/([^/]+)$#i', '$1', $k['id']['$t']),
            ];
        }

        if ($rawResponse) {
            $data['extra'] = $ret;
            return $data;
        }

        return $ret;
    }
    public function createWorkSheet($key, $title, $rows = 50, $cells = 10) {

        $xml = <<<xml
<entry xmlns="http://www.w3.org/2005/Atom" xmlns:gs="http://schemas.google.com/spreadsheets/2006">
  <title>$title</title>
  <gs:rowCount>$rows</gs:rowCount>
  <gs:colCount>$cells</gs:colCount>
</entry>
xml
;

        return $this->api("/feeds/worksheets/$key/private/full", 'post', $xml, [
            "Content-Type" => "application/atom+xml"
        ]);
    }

    /**
     * https://developers.google.com/google-apps/spreadsheets/worksheets#delete_a_worksheet
     */
    public function deleteWorksheet($key, $wid) {
        return $this->api("/feeds/worksheets/$key/private/full/$wid/version", 'delete');
    }
    /**
     * https://developers.google.com/google-apps/spreadsheets/worksheets#modify_a_worksheets_title_and_size
     */
    public function renameWorksheet($key, $wid, $title) {

        $list = $this->getWorksheetData($key, $wid);

        $edit = $list['link'][5]['href'];

        $xml = <<<xml
<entry xmlns="http://www.w3.org/2005/Atom" xmlns:gs="http://schemas.google.com/spreadsheets/2006">
    <title>$title</title>
    <gs:colCount>{$list['gs$colCount']['$t']}</gs:colCount>
    <gs:rowCount>{$list['gs$rowCount']['$t']}</gs:rowCount>
</entry>
xml
        ;
        $this->api($edit, 'put', $xml, [
            "Content-Type" => "application/atom+xml"
        ]);

        return $this;
    }

}