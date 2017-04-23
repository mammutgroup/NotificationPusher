<?php

/*
 * This file is part of NotificationPusher.
 *
 * (c) 2013 Cédric Dugat <cedric@dugat.me>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sly\NotificationPusher\Adapter;

use Pushok\AuthProvider\Token;
use Pushok\Client;
use Pushok\Notification;
use Pushok\Payload;
use Pushok\Payload\Alert;
use Sly\NotificationPusher\Collection\DeviceCollection;
use Sly\NotificationPusher\Exception\AdapterException;
use Sly\NotificationPusher\Exception\PushException;
use Sly\NotificationPusher\Model\BaseOptionedModel;
use Sly\NotificationPusher\Model\PushInterface;
use ZendService\Apple\Apns\Client\AbstractClient as ServiceAbstractClient;
use ZendService\Apple\Apns\Client\Feedback as ServiceFeedbackClient;
use ZendService\Apple\Apns\Client\Message as ServiceClient;
use ZendService\Apple\Apns\Message as ServiceMessage;
use ZendService\Apple\Apns\Response\Message as ServiceResponse;

/**
 * APNS adapter.
 *
 * @uses \Sly\NotificationPusher\Adapter\BaseAdapter
 *
 * @author Cédric Dugat <cedric@dugat.me>
 */
class Apns extends BaseAdapter
{
    /** @var ServiceClient */
    private $openedClient;
    /** @var ServiceFeedbackClient */
    private $feedbackClient;

    /**
     * {@inheritdoc}
     *
     * @throws \Sly\NotificationPusher\Exception\AdapterException
     */
    public function __construct(array $parameters = [])
    {
        parent::__construct($parameters);

        $cert = $this->getParameter('private_key_path');

        if (false === file_exists($cert)) {
            throw new AdapterException(sprintf('Certificate %s does not exist', $cert));
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Sly\NotificationPusher\Exception\PushException
     */
    public function push(PushInterface $push)
    {
        $client = $this->getOpenedServiceClient();

        $pushedDevices = new DeviceCollection();

        $payload = $this->getServiceMessageFromOrigin($push->getMessage());

        $notifications = [];

        foreach ($push->getDevices() as $device) {
            $notifications[] = new Notification($payload, $device->getToken());
        }

        try {
            $client->addNotifications($notifications);
            $this->response = $client->push();
        } catch (\Exception $e) {
            throw new PushException($e->getMessage());
        }

        foreach ($this->response as $response) {
            if ((int)$response->getStatusCode() === 200){
                $pushedDevices->add($response->getApnsId());
            }
        }

        return $pushedDevices;
    }

    /**
     * Feedback.
     *
     * @return array
     */
    public function getFeedback()
    {
        //@todo feedback??
        $client = $this->getOpenedFeedbackClient();
        $responses = [];
        $serviceResponses = $client->feedback();

        foreach ($serviceResponses as $response) {
            $responses[$response->getToken()] = new \DateTime(date('c', $response->getTime()));
        }

        return $responses;
    }

    /**
     * Get opened client.
     *
     * @param \ZendService\Apple\Apns\Client\AbstractClient $client Client
     *
     * @return \ZendService\Apple\Apns\Client\AbstractClient
     */
    public function getOpenedClient(ServiceAbstractClient $client)
    {
        $authProvider = Token::create($this->getParameters());
        $client = new Client($authProvider, $this->isProductionEnvironment());
        return $client;
    }

    /**
     * Get opened ServiceClient
     *
     * @return ServiceAbstractClient
     */
    private function getOpenedServiceClient()
    {
        if (!isset($this->openedClient)) {
            $this->openedClient = $this->getOpenedClient(new ServiceClient());
        }

        return $this->openedClient;
    }

    /**
     * Get opened ServiceFeedbackClient
     *
     * @return ServiceAbstractClient
     */
    private function getOpenedFeedbackClient()
    {
        if (!isset($this->feedbackClient)) {
            $this->feedbackClient = $this->getOpenedClient(new ServiceClient());
        }

        return $this->feedbackClient;
    }

    /**
     * Get service message from origin.
     *
     * @param \Sly\NotificationPusher\Model\DeviceInterface $device Device
     * @param BaseOptionedModel|\Sly\NotificationPusher\Model\MessageInterface $message Message
     *
     * @return \ZendService\Apple\Apns\Message
     */
    public function getServiceMessageFromOrigin(BaseOptionedModel $message)
    {
        $options = $message->getOptions();
        $alert = Alert::create()->setTitle($message->getText());
        $alert = $alert->setBody($message->getText());

        $payload = Payload::create()->setAlert($alert);
        $this->getCustomParameters($payload, $options);

        foreach ($options as $key => $value) {
            $payload->setCustomValue($key, $value);
        }

        return $payload;
    }

    /**
     * {@inheritdoc}
     */
    public function supports($token)
    {
        return is_string($token); //@todo check token
    }

    /**
     * {@inheritdoc}
     */
    public function getDefinedParameters()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultParameters()
    {
        return [
            'key_id' => null,
            'team_id' => null,
            'app_bundle_id' => null,
            'private_key_path' => null,
            'private_key_secret' => null
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredParameters()
    {
        return [
            'key_id',
            'team_id',
            'app_bundle_id',
            'private_key_path',
            'private_key_secret'
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getCustomParameters($payload, &$options = [])
    {
        $data = [
            'badge' => 'setBadge',
            'sound' => 'setSound',
            'contentAvailable' => 'setContentAvailability',
            'category' => 'setCategory',
            'threadId' => 'setThreadId'
        ];

        foreach ($data as $key => $setterMethod) {
            if (isset($options[$key])) {
                $payload->$setterMethod($options[$key]);
                unset($options[$key]);
            }
        }
    }
}
