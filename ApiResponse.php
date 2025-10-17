<?php
class ApiResponse
{
    private $format;
    private $body;
    private $headers;
    private $httpCode;

    public function __construct() {}
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
            return null;
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
     * Sets the a single header for the response
     * 
     * @param array $header The header name
     * @param array $value The header value
     */
    public function setHeader($header, $value): void
    {
        $this->headers[$header] = $value;
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
