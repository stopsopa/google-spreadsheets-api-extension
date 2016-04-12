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
 */
class GoogleSpreadsheets {
    protected $scopes;
    /**
     * @var Google_Client
     */
    protected $client;
    protected $endpoint = 'https://spreadsheets.google.com';
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
            if (strpos($feedurl, "?") === false) {
                $feedurl .= '?';
            }
            else {
                $feedurl .= '&';
            }
            $feedurl .= 'alt=json';
        }

        $this->_isInitializationCheck();

        if ($this->client->getAuth()->isAccessTokenExpired()) {
            $this->client->getAuth()->refreshTokenWithAssertion();
        }

        if (!preg_match('#^https?://#', $feedurl)) {
            $feedurl = $this->endpoint.$feedurl;
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

        if (!in_array(($code = $httpRequest->getResponseHttpCode()), array(200, 201))) {
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
            $ret[] = array(
                'key' => preg_replace('#^.*?/([^/]+)$#i', '$1', $k['id']['$t']),
                'title' => $k['title']['$t']
            );
        }

        return $ret;
    }
    public function getWorksheetMetadata($key, $wid) {

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
            $ret[] = array(
                'title' => $k['title']['$t'],
                'id' => preg_replace('#^.*?/([^/]+)$#i', '$1', $k['id']['$t']),
            );
        }

        if ($rawResponse) {
            $data['extra'] = $ret;
            return $data;
        }

        return $ret;
    }
    public function createWorkSheet($key, $title, $rows = 50, $cols = 10) {

        $xml = <<<xml
<entry xmlns="http://www.w3.org/2005/Atom" xmlns:gs="http://schemas.google.com/spreadsheets/2006">
  <title>$title</title>
  <gs:rowCount>$rows</gs:rowCount>
  <gs:colCount>$cols</gs:colCount>
</entry>
xml
        ;

        return $this->api("/feeds/worksheets/$key/private/full", 'post', $xml, array(
            "Content-Type" => "application/atom+xml"
        ));
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
    public function updateWorksheetMetadata($key, $wid, $title = null, $rows = null, $cols = null) {

        $list = $this->getWorksheetMetadata($key, $wid);

        if ($title === null) {
            $title = $list['content']['$t'];
        }

        if ($rows === null) {
            $rows = $list['gs$rowCount']['$t'];
        }

        if ($cols === null) {
            $cols = $list['gs$colCount']['$t'];
        }

        $edit = $list['link'][5]['href'];

        $xml = <<<xml
<entry xmlns="http://www.w3.org/2005/Atom" xmlns:gs="http://schemas.google.com/spreadsheets/2006">
    <title>$title</title>
    <gs:colCount>$cols</gs:colCount>
    <gs:rowCount>$rows</gs:rowCount>
</entry>
xml
        ;
        $this->api($edit, 'put', $xml, array(
            "Content-Type" => "application/atom+xml"
        ));

        return $this;
    }
    /**
     * https://developers.google.com/google-apps/spreadsheets/data#retrieve_a_list-based_feed
     */
    public function update($key, $wid, $data) {

        $xml = <<<xml
<feed xmlns="http://www.w3.org/2005/Atom"
      xmlns:batch="http://schemas.google.com/gdata/batch"
      xmlns:gs="http://schemas.google.com/spreadsheets/2006">
  <id>{$this->endpoint}/feeds/cells/$key/$wid/private/full</id>
xml;


        foreach ($data as $cell => $d) {

            $rc = CellConverter::anyToRC($cell);

            $c = 'R'.$rc['r'].'C'.$rc['c'];

            $xml .= <<<xml
  <entry>
    <batch:id>$c</batch:id>
    <batch:operation type="update"/>
    <id>{$this->endpoint}/feeds/cells/$key/$wid/private/full/$c</id>
    <link rel="edit" type="application/atom+xml" href="{$this->endpoint}/feeds/cells/$key/$wid/private/full/$c"/>
    <gs:cell row="{$rc['r']}" col="{$rc['c']}" inputValue="$d"/>
  </entry>
xml;
        }


        $xml .= '</feed>';

        $xml = $this->api("/feeds/cells/$key/$wid/private/full/batch", 'post', $xml, array(
            "Content-Type" => "application/atom+xml",

            // http://stackoverflow.com/a/24128641
            // http://stackoverflow.com/a/23465333
            // g(Updating cell in Google Spreadsheets returns error “Missing resource version ID” / “The remote server returned an error: (400) Bad Request.”)
            "If-Match"=> "*"
        ));

        $xml = SimpleXMLElementHelper::parseString($xml);

        $xml = $xml['xml'];

        $data = array();

        $firstError = 200;

        foreach ($xml->children as &$a) {
            if ($a->name === 'atom:entry') {

                $x = &$a->children;

                $title      = $this->_findNode($x, 'atom:title');
                $content    = $this->_findNode($x, 'atom:content');
                $status     = $this->_findNode($x, 'batch:status');
                $id         = $this->_findNode($x, 'batch:id');

                // if error then there is no gs:cell node
                $cell       = $this->_findNode($x, 'gs:cell');

                $code       = (int)$status->attributes['code'];

                if ($firstError === 200 && $code !== 200) {
                    $firstError = $code;
                }

                $row = array(
                    'a1'            => $title->text,
                    'status'        => $code,
                    'reason'        => $status->attributes['reason']
                );

                if ($cell) {
                    $row['col']             = (int)$cell->attributes['col'];
                    $row['row']             = (int)$cell->attributes['row'];
                    $row['numericValue']    = isset($cell->attributes['numericValue']) ? $cell->attributes['numericValue'] : null;
                    $row['inputValue']      = $content->text;
                }

                $data[$id->text] = $row;
            }
        }

        return array(
            'status' => $firstError,
            'data' => $data
        );
    }
    protected function _findNode($collection, $name) {
        foreach ($collection as &$c) {
            if ($c->name === $name) {
                return $c;
            }
        }
    }

    /**
     * @param $key
     * @param $wid
     * @param bool $rawResponse
     * @param array $filter
     *   https://developers.google.com/google-apps/spreadsheets/data#fetch_specific_rows_or_columns
     *   g(Fetch specific rows or columns The Sheets API allows users to fetch specific rows or columns from a worksheet by providing additional URL parameters when making a request.)
     *   eq:
     *       [
     *         "min-row" : 2,
     *         "max-row" : 6,
     *         "min-col" : 2,
     *         "max-col" : 8
     *       ]
     *
     * @return array|mixed|string
     * @throws Exception
     */
    public function findWorksheetData($key, $wid, $rawResponse = false, $filter = array()) {

        if ($filter) {
            $tmp = array();
            foreach ($filter as $name => $value) {
                $tmp[] = "$name=$value";
            }
            $filter = '?'.implode('&', $tmp);
        }
        else {
            $filter = '';
        }

        $data = $this->api("/feeds/cells/$key/$wid/private/full$filter");

        if ($rawResponse) {
            return $data;
        }

        return array_map(function ($cell) {

            $row = array(
                'col'           => (int)$cell['gs$cell']['col'],
                'row'           => (int)$cell['gs$cell']['row'],
                'inputValue'    => $cell['gs$cell']['inputValue'],
                'numericValue'  => isset($cell['gs$cell']['numericValue']) ? $cell['gs$cell']['numericValue'] : null,
                'a1'            => $cell['title']['$t']
            );

            $row['val'] = (substr($row['inputValue'], 0, 1) === '=') ? $row['numericValue'] : $row['inputValue'] ;

            return $row;

        }, $data['feed']['entry']);
    }
    public function findFirstFreeRowForData($key, $wid) {

        $data = $this->findWorksheetData($key, $wid);

        $last = 0;

        foreach ($data as $row) {
            if ($row['row'] > $last) {
                $last = $row['row'];
            }
        }

        return $last + 1;
    }
    protected function _dump($data) {
        fwrite(STDOUT, print_r($data, true));
    }
}