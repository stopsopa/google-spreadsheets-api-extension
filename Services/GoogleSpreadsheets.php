<?php

namespace Stopsopa\GoogleSpreadsheets\Services;

class GoogleSpreadsheets {
    protected $scopes;
    protected $client;
    public function __construct($p12KeyFile, $client_email, $scopes = null)
    {
        if (!$scopes) {
            $scopes = array(
                'https://spreadsheets.google.com/feeds'
            );
        }

        $this->scopes = $scopes;




    }
}