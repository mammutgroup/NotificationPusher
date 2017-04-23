<?php
namespace Sly\NotificationPusher\Adapter;

use Sly\NotificationPusher\Adapter\Fcm\Message;
use Sly\NotificationPusher\Model\BaseOptionedModel;
use Sly\NotificationPusher\Adapter\Fcm\Client as ServiceClient;

class Fcm extends Gcm
{
    /**
     * Get opened client.
     *
     * @return \ZendService\Google\Gcm\Client
     */
    public function getOpenedClient()
    {
        if (!isset($this->openedClient)) {
            $this->openedClient = new ServiceClient();
            $this->openedClient->setApiKey($this->getParameter('apiKey'));

            $newClient = new \Zend\Http\Client(
                null,
                [
                    'adapter' => 'Zend\Http\Client\Adapter\Socket',
                    'sslverifypeer' => false
                ]
            );

            $this->openedClient->setHttpClient($newClient);
        }

        return $this->openedClient;
    }

    /**
     * @param array $tokens
     * @param BaseOptionedModel $message
     * @return Message
     */
    public function getServiceMessageFromOrigin(array $tokens, BaseOptionedModel $message)
    {
        $data = $message->getOptions();
        $data['message'] = $message->getText();
        $serviceMessage = new Message();
        $serviceMessage->setRegistrationIds($tokens)
            ->setData($data)
            ->setCollapseKey($this->getParameter('collapseKey'))
            ->setRestrictedPackageName($this->getParameter('restrictedPackageName'))
            ->setDelayWhileIdle($this->getParameter('delayWhileIdle', false))
            ->setTimeToLive($this->getParameter('ttl', 600))
            ->setDryRun($this->getParameter('dryRun', false))
            ->setPriority($this->getParameter('priority'));
        return $serviceMessage;
    }

    /**
     * Get the feedback.
     *
     * @return array
     */
    public function getFeedback()
    {
        return $this->response->getResults();
    }
}