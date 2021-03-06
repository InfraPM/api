<?php
class ApiResponse
{
    private $format;
    private $body;
    private $headers;
    private $httpCode;

    public function __construct()
    {
    }
    /**
     * Return the requested property
     * 
     * @param string $property The property to return
     */
    public function __get(string $property)
    {
        if (isset($this->$property)) {
            return $this->$property;
        } else {
            throw new Exception('Property ' . get_class($this) . '::' . $property . ' does not exist.');
        }
    }
    /**
     * Set the response format (text/xml, application/json, etc.)
     * 
     * @param string $format The format of the response
     */
    public function setFormat(string $format): void
    {
        $this->format = $format;
    }
    /**
     * Set the response body
     * 
     * @param string $body The response body
     */
    public function setBody(string $body): void
    {
        $this->body = $body;
    }
    /**
     * Set the headers for the response
     * 
     * @param array $headers The headers in array format "Header"=>"Value" = Header: Value
     */
    public function setHeaders(array $headers): void
    {
        $this->headers = $headers;
    }
    /**
     * Set the HTTP Repsonse code of the API Response
     * 
     * @param int $httpCode The HTTP Response code of the API Response
     */
    public function setHttpCode(int $httpCode): void
    {
        $this->httpCode = $httpCode;
    }
}
