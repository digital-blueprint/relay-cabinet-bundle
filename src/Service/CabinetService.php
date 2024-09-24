<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Service;

use Dbp\Relay\BasePersonBundle\API\PersonProviderInterface;
use Dbp\Relay\BasePersonBundle\Entity\Person;
use Dbp\Relay\CabinetBundle\Blob\BlobService;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

class CabinetService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var PersonProviderInterface
     */
    private $personProvider;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var MessageBusInterface
     */
    private $bus;

    /**
     * @var BlobService
     */
    private $blobService;

    public function __construct(
        PersonProviderInterface $personProvider,
        EntityManagerInterface $em,
        MessageBusInterface $bus,
        BlobService $blobService
    ) {
        $this->personProvider = $personProvider;
        $this->em = $em;
        $this->bus = $bus;
        $this->logger = new NullLogger();
        $this->blobService = $blobService;
    }

    public function setConfig(array $config)
    {
    }

    private function getCurrentPerson(): Person
    {
        $person = $this->personProvider->getCurrentPerson();

        if (!$person) {
            throw ApiError::withDetails(Response::HTTP_FORBIDDEN, "Current person wasn't found!", 'cabinet:current-person-not-found');
        }

        return $person;
    }

    public function checkConnection()
    {
        $this->em->getConnection()->getNativeConnection();
    }

    protected function log($level, string $message, array $context = [])
    {
        $context['service'] = 'cabinet';
        $this->logger->log($level, $message, $context);
    }

    protected function logWarning(string $message, array $context = [])
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    protected function logInfo(string $message, array $context = [])
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    protected function logError(string $message, array $context = [])
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }
}
