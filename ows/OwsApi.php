<?php

require __DIR__ . '/../Api.php';

class OwsApi extends Api
{
    protected $request = NULL;
    protected $token = NULL;
    protected $public = TRUE;
    protected $dataList = [];

    public function __construct()
    {
        parent::__construct();
    }

    public function readRequest(): void
    {
        if (isset($this->apiRequest->getVar['request'])) {
            $this->request = $this->apiRequest->getVar['request'];
        }
        if (isset($this->apiRequest->getVar['token'])) {
            $this->token = $this->apiRequest->getVar['token'];
        } else {
            $this->token = 'public';
        }
        if ($this->token == 'public' || $this->token == '' || $this->token == NULL) {
            $this->public = TRUE;
        } else {
            $this->public = FALSE;
        }

        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            if ($this->public) {
                $this->allowAnyOrigin();
            }
            return;
        }
    }

    /**
     * Converts the array of headers returned by http_get_last_response_headers()
     * into an associative array.
     */
    function parseHeaders($headers)
    {
        $head = array();
        if (!is_array($headers))
            return $head;
        foreach ($headers as $k => $v) {
            $t = explode(':', $v, 2);
            if (isset($t[1])) {
                $head[trim($t[0])] = trim($t[1]);
            }
        }
        return $head;
    }
}
