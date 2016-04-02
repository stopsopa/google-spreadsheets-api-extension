<?php

namespace Stopsopa\GoogleSpreadsheets\Services;
use Exception;
use Google_Http_Request;
use Google_Client;
use Google_Auth_AssertionCredentials;

class GoogleSpreadsheets {
    protected $scopes;
    protected $client;
    const USER_AGENT = ' google-api-php-client/1.0.6-beta';
    public function __construct($p12_key_file_location, $client_email, $scopes = null)
    {
        if (!$scopes) {
            $scopes = array(
                'https://spreadsheets.google.com/feeds'
            );
        }

        $this->scopes = $scopes;

        if (!file_exists($p12_key_file_location)) {
            throw new Exception("File '$p12_key_file_location' file doesn't exists");
        }

        if (!is_readable($p12_key_file_location)) {
            throw new Exception("File '$p12_key_file_location' is not readdable");
        }

        $private_key = file_get_contents($p12_key_file_location);

        $credentials = new Google_Auth_AssertionCredentials(
            $client_email,
            $scopes,
            $private_key
        );

        $client = new Google_Client();

        $client->setAssertionCredentials($credentials);

        $this->client = $client;
    }
    public function api($feedurl, $method = 'GET', $xml = '') {

        if ($this->client->getAuth()->isAccessTokenExpired()) {
            $this->client->getAuth()->refreshTokenWithAssertion();
        }

        $request = new Google_Http_Request("https://spreadsheets.google.com$feedurl");
        //$data = $this->client->execute($request);

        $this->client->getAuth()->authenticatedRequest($request);
        $request->setUserAgent(static::USER_AGENT);
        $request->enableGzip();

        $request->setRequestMethod($method);

        if ($xml) {
            $request->setResponseBody($xml);
        }

        $request->maybeMoveParametersToBody();

        /* @var $httpRequest Google_Http_Request */
        $httpRequest = $this->client->getIo()->makeRequest($request);

        if ( ($code = $httpRequest->getResponseHttpCode()) !== 200) {
            throw new Exception("Wrong status code: ".$code, " response: ".print_r($httpRequest->getResponseBody(), true));
        }

        $data = json_decode($httpRequest->getResponseBody(), true);

        if ($data) {
            return $data;
        }

        return $httpRequest->getResponseBody();
    }
    public function findSpreadsheets($rawResponse = false) {

        $data = $this->api('/feeds/spreadsheets/private/full?alt=json');

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
    public function findWorksheets($key, $rawResponse = false) {

        $data = $this->api("/feeds/worksheets/$key/private/full?alt=json");

        if ($rawResponse) {
            return $data;
        }

        $ret = array();

        foreach ($data['feed']['entry'] as $k) {
            $ret[] = [
                'title' => $k['title']['$t'],
                'id' => preg_replace('#^.*?/([^/]+)$#i', '$1', $k['id']['$t']),
            ];
        }

        return $ret;
    }

}