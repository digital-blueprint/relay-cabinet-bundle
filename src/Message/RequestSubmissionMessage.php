<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Message;

use Dbp\Relay\CabinetBundle\Entity\Request;

class RequestSubmissionMessage
{
    /**
     * @var Request
     */
    private $request;

    /**
     * RequestSubmissionMessage constructor.
     */
    public function __construct(
        Request $request
    ) {
        $this->request = $request;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
