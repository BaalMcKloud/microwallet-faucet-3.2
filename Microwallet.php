<?php

class Microwallet
{
    protected $apiKey;

    public function __construct($apiKey = null) {
        if (is_null($apiKey)) {
            throw new Exception('API key missing.');
        }
        $this->apiKey = $apiKey;
    }

    public function send($to = null, $amount = null)
    {
        if (is_null($to) || is_null($amount)) {
            return array(
                'success' => false,
                'message' => 'Recipient and/or amount missing.',
                'html' => '<div class="alert alert-danger">Recipient and/or amount missing.</div>',
                'response' => null,
            );
        }

        $postData = 'api_key=' . rawurlencode($this->apiKey) . '&to=' . rawurlencode($to) . '&amount=' . rawurlencode($amount);

        $request = '';
        $request .= "POST /api/v1/send HTTP/1.0\r\n";
        $request .= "Host: www.microwallet.org\r\n";
        $request .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $request .= "Content-Length: " . strlen($postData) . "\r\n";
        $request .= "Connection: close\r\n\r\n";
        $request .= $postData . "\r\n";

        $fp = @fsockopen('ssl://www.microwallet.org', 443);
        if (!$fp) {
            return array(
                'success' => false,
                'message' => 'Failed to send.',
                'html' => '<div class="alert alert-danger">Failed to send.</div>',
                'response' => null,
            );
        }
        @fputs($fp, $request);
        $response = '';
        while (!@feof($fp)) {
            $response .= @fgets($fp, 1024);
        }
        @fclose($fp);

        list($header, $response) = explode("\r\n\r\n", $response);
        $responseJson = json_decode($response);

        if (isset($responseJson->status) && $responseJson->status === 200) {
            return array(
                'success' => true,
                'message' => 'Payment sent to your Microwallet.org account.',
                'html' => '<div class="alert alert-success">' . htmlspecialchars($amount) . ' satoshi was sent to <a target="_blank" href="https://www.microwallet.org/?u=' . rawurlencode($to) . '">your Microwallet.org account</a>.</div>',
                'response' => $response,
            );
        }

        if (isset($responseJson->message)) {
            return array(
                'success' => false,
                'message' => $responseJson->message,
                'html' => '<div class="alert alert-danger">' . htmlspecialchars($responseJson->message) . '</div>',
                'response' => $response,
            );
        }

        return array(
            'success' => false,
            'message' => 'Unknown error.',
            'html' => '<div class="alert alert-danger">Unknown error.</div>',
            'response' => $response,
        );
    }
}
