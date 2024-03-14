<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\MessageHandler;

use Dbp\Relay\CabinetBundle\Message\RequestSubmissionMessage;
use Dbp\Relay\CabinetBundle\Service\CabinetService;

class RequestSubmissionHandler
{
    private $api;

    public function __construct(CabinetService $api)
    {
        $this->api = $api;
    }

    public function __invoke(RequestSubmissionMessage $message)
    {
        $this->api->handleRequestSubmissionMessage($message);
    }
}
