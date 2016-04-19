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
            print_r($httpRequest);
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
    public function findSpreadsheets($rawResponse = false) {

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

        return $this->api($edit, 'put', $xml, array(
            "Content-Type" => "application/atom+xml"
        ));
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

        $firstError = 200;

        $data = array();

        $xml = SimpleXMLElementHelper::parseString($xml);

        if (isset($xml['xml']['nstags'])) {

            $xml = $xml['xml']['nstags'];

            foreach ($xml['atom'] as &$tag) {
                if ($tag['name'] === 'entry') {

                    $x = &$tag['nstags'];

                    $title      = $this->_findTag($x, 'atom', 'title');
                    $content    = $this->_findTag($x, 'atom', 'content');
                    $status     = $this->_findTag($x, 'batch', 'status');
                    $id         = $this->_findTag($x, 'batch', 'id');

                    // if error then there is no gs:cell node
                    $cell       = $this->_findTag($x, 'gs', 'cell');

                    $code       = $this->_findTag($status, 'attributes', 'code');

                    $reason     = $this->_findTag($status, 'attributes', 'reason');

                    if ($code && $reason) {

                        $code       = (int)$code['val'];

                        if ($firstError === 200 && $code !== 200) {
                            $firstError = $code;
                        }

                        $row = array(
                            'a1'            => $title['text'],
                            'status'        => $code,
                            'reason'        => $reason['val']
                        );

                        if ($cell) {
                            $t             = $this->_findTag($cell, 'attributes', 'col');
                            if ($t) {
                                $row['col']             = (int)$t['val'];
                            }

                            $t             = $this->_findTag($cell, 'attributes', 'row');
                            if ($t) {
                                $row['row']             = (int)$t['val'];
                            }

                            $t             = $this->_findTag($cell, 'attributes', 'numericValue');
                            $row['numericValue']    = $t ? $t['val'] : null;

                            $t             = $this->_findTag($cell, 'attributes', 'inputValue');
                            $row['inputValue']    = $t ? $t['val'] : null;

                            $row['inputValue']      = $content['text'];
                        }

                        $data[$id['text']] = $row;
                    }
                }
            }
        }

        return array(
            'status'    => $firstError,
            'data'      => $data
        );
    }
    protected function &_findTag(&$set, $ns, $title) {
        if (isset($set[$ns]) && is_array($set[$ns])) {
            foreach ($set[$ns] as &$d) {
                if ($d['name'] === $title) {
                    return $d;
                }
            }
        }
        $e = null;
        return $e;
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
     *         "min-row" : 2, // inclusive
     *         "max-row" : 6, // inclusive
     *         "min-col" : 2, // inclusive
     *         "max-col" : 8  // inclusive
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

        $tmp = array();

        if (isset($data['feed']['entry'])) {

            foreach ($data['feed']['entry'] as $cell) {
                $row = array(
                    'col'           => (int)$cell['gs$cell']['col'],
                    'row'           => (int)$cell['gs$cell']['row'],
                    'inputValue'    => $cell['gs$cell']['inputValue'],
                    'numericValue'  => isset($cell['gs$cell']['numericValue']) ? $cell['gs$cell']['numericValue'] : null,
                    'a1'            => $cell['title']['$t']
                );

                $row['val'] = (substr($row['inputValue'], 0, 1) === '=') ? $row['numericValue'] : $row['inputValue'] ;

                $tmp["R{$row['row']}C{$row['col']}"] = $row;
            }
        }

        return $tmp;
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
    public function listApi($key, $wid, $rawResponse = false) {

        $raw = $this->api("/feeds/list/$key/$wid/private/full");

        if ($rawResponse) {
            return $raw;
        }

        $data = array(
            'title' => $raw['feed']['title']['$t'],
            'totalResults' => (int)$raw['feed']['openSearch$totalResults']['$t'],
            'startIndex' => (int)$raw['feed']['openSearch$startIndex']['$t'],
        );

        $list = array();

        foreach ($raw['feed']['entry'] as &$d) {
            $row = array(
                'id' => preg_replace('#^.*?/([^/]+)$#', '$1', $d['id']['$t']),
                'edit' => $d['link'][1]['href']
            );

            $dat = array();
            foreach ($d as $key => $dd) {
                if (strpos($key, 'gsx$') === 0) {
                    $dat[substr($key, 4)] = $dd['$t'];
                }
            }

            $row['data'] = $dat;

            $list[] = $row;
        }

        $data['data'] = $list;

        return $data;
    }

    /**
     * @param $key
     * @param $wid
     * @return GoogleSpreadsheetsList
     */
    public function getList($key, $wid) {
        return new GoogleSpreadsheetsList($this, $key, $wid);
    }

    /**
     * This method doesn't work even if it is done acording the documentation:
     *   https://developers.google.com/google-apps/spreadsheets/data#add_a_list_row
     *   So i create Class to manipulate rows of worksheet
     * @param $key
     * @param $wid
     * @param $data
     */
//    protected function listInsert($key, $wid, $data) {
////    public function listInsert($key, $wid, $data) {
//
//        $xml = '<entry xmlns="http://www.w3.org/2005/Atom" xmlns:gsx="http://schemas.google.com/spreadsheets/2006/extended">'."\n";
//
//        foreach ($data as $col => $value) {
////            $col = $this->_normalizeKey($col);
//            $xml .= "    <gsx:$col><![CDATA[$value]]></gsx:$col>\n";
//        }
//
//        $xml .= '</entry>';
//
//        // --------------- i try by pure curl ---- vvv
////        if ($this->client->getAuth()->isAccessTokenExpired()) {
////            $this->client->getAuth()->refreshTokenWithAssertion();
////        }
////
////        $token = json_decode((string)$this->client->getAccessToken(), true)["access_token"];
////
////        $token = preg_replace('#^.*?\.\.(.*)$#', '$1', $token);
//
////            $headers = array(
////                "Content-Type: application/atom+xml",
////                "Authorization: Bearer " . $token,
////    //            "Authorization: GoogleLogin auth=" . $token,
////                "GData-Version: 3.0"
////            );
////
////            $curl = curl_init();
////            curl_setopt($curl, CURLOPT_URL, "https://spreadsheets.google.com/feeds/list/$key/$wid/private/full");
////            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
////            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
////            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
////            curl_setopt($curl, CURLOPT_POST, true);
////            curl_setopt($curl, CURLOPT_POSTFIELDS, $xml);
////            $response = curl_exec($curl);
////            $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
////            curl_close($curl);
////
////            print_r($response);
////            die(print_r($status));
//        // unfortunately using curl still doesn't work, i think that documentation is out of date nom
//        // --------------- i try by pure curl ---- ^^^
//
//
//
//
////        print_r($xml);
//
//        $data = $this->api("/feeds/list/$key/$wid/private/full", 'post', $xml, array(
//            "Content-Type" => "application/atom+xml",
//            "GData-Version" => "3.0"
//        ));
//
//        print_r($data);
//    }
}