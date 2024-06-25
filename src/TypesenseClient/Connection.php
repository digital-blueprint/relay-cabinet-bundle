<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseClient;

use Monolog\Level;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\HttplugClient;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Component\HttpClient\TraceableHttpClient;
use Typesense\Client;

class Connection implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Retries do exponential backoff. We don't really care if it takes long,
     * as long as it succeeds, so retry a lot (10=4095 seconds in total)
     * See https://symfony.com/doc/current/http_client.html#retry-failed-requests
     * for the retry logic.
     */
    public const TYPESENSE_CLIENT_RETRY_COUNT = 10;

    /**
     * @var array<string,string>
     */
    private array $config;

    private string $baseUrl;
    private string $apikey;

    public function __construct(string $baseUrl, string $apikey)
    {
        $this->baseUrl = $baseUrl;
        $this->apikey = $apikey;
        $this->logger = new NullLogger();
    }

    public function getClient(): Client
    {
        $parsedUrl = parse_url($this->baseUrl);
        if ($parsedUrl === false) {
            throw new \InvalidArgumentException('Invalid url provided');
        }
        $scheme = $parsedUrl['scheme'] ?? 'http';
        $host = $parsedUrl['host'] ?? 'localhost';
        $port = $parsedUrl['port'] ?? ($scheme === 'https' ? 443 : ($scheme === 'http' ? 80 : '8108'));

        $this->config = [
            'host' => $host,
            'port' => $port,
            'protocol' => $scheme,
        ];

        // We disabled the typesense internal retry logic and just use the symfony one instead
        $symfonyClient = new TraceableHttpClient(HttpClient::create());
        $symfonyClient->setLogger($this->logger);
        $symfonyClient = new RetryableHttpClient(
            $symfonyClient, null, self::TYPESENSE_CLIENT_RETRY_COUNT,
            $this->logger);

        return new Client(
            [
                'api_key' => $this->apikey,
                'nodes' => [
                    $this->config,
                ],
                'num_retries' => 0,
                'log_level' => Level::Info->value,
                'client' => new HttplugClient($symfonyClient),
            ]
        );
    }
}
