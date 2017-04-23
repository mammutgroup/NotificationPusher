<?php namespace Sly\NotificationPusher\Adapter\Fcm;

use Zend\Http\Client as HttpClient;
use Zend\Json\Json;
use ZendService\Google\Exception;
use ZendService\Google\Gcm\Response;

class Client
{
    /**
     * @const string Server URI
     */
    const SERVER_URI = 'https://fcm.googleapis.com/fcm/send';
    /**
     * @var Zend\Http\Client
     */
    protected $httpClient;
    /**
     * @var string
     */
    protected $apiKey;

    /**
     * Get API Key
     *
     * @return string
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * Set API Key
     *
     * @param string $apiKey
     * @return Client
     * @throws InvalidArgumentException
     */
    public function setApiKey($apiKey)
    {
        if (!is_string($apiKey) || empty($apiKey)) {
            throw new Exception\InvalidArgumentException('The api key must be a string and not empty');
        }
        $this->apiKey = $apiKey;
        return $this;
    }

    /**
     * Get HTTP Client
     *
     * @return Zend\Http\Client
     */
    public function getHttpClient()
    {
        if (!$this->httpClient) {
            $this->httpClient = new HttpClient();
            $this->httpClient->setOptions(array('strictredirects' => true));
        }
        return $this->httpClient;
    }

    /**
     * Set HTTP Client
     *
     * @param Zend\Http\Client
     * @return Client
     */
    public function setHttpClient(HttpClient $http)
    {
        $this->httpClient = $http;
        return $this;
    }

    /**
     * Send Message
     *
     * @param Mesage $message
     * @return Response
     * @throws Exception\RuntimeException
     */
    public function send(Message $message)
    {
        $client = $this->getHttpClient();
        $client->setUri(self::SERVER_URI);
        $headers = $client->getRequest()->getHeaders();
        $headers->addHeaderLine('Authorization', 'key=' . $this->getApiKey());

        $response = $client->setHeaders($headers)
            ->setMethod('POST')
            ->setRawBody($message->toJson())
            ->setEncType('application/json')
            ->send();

        switch ($response->getStatusCode()) {
            case 500:
                throw new Exception\RuntimeException('500 Internal Server Error');
                break;
            case 503:
                $exceptionMessage = '503 Server Unavailable';
                if ($retry = $response->getHeaders()->get('Retry-After')) {
                    $exceptionMessage .= '; Retry After: ' . $retry;
                }
                throw new Exception\RuntimeException($exceptionMessage);
                break;
            case 401:
                throw new Exception\RuntimeException('401 Forbidden; Authentication Error');
                break;
            case 400:
                throw new Exception\RuntimeException('400 Bad Request; invalid message');
                break;
        }

        if (!$response = Json::decode($response->getBody(), Json::TYPE_ARRAY)) {
            throw new Exception\RuntimeException('Response body did not contain a valid JSON response');
        }

        return new Response($response, $message);
    }
}