<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Service;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TypesenseService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private HttpClientInterface $client;

    private string $typesenseApiKey;

    private string $typesenseBaseUrl;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
        $this->typesenseBaseUrl = '';
        $this->typesenseApiKey = '';
    }

    public function setConfig(array $config): void
    {
        $this->typesenseBaseUrl = $config['typesense_base_url'] ?? '';
        $this->typesenseApiKey = $config['typesense_api_key'] ?? '';
    }

    /**
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function doProxyRequest(string $path, Request $request): Response
    {
        // TODO: Check bearer token
        // TODO: Check permissions
        return new Response('Try later', Response::HTTP_UNAUTHORIZED);

        $url = $this->typesenseBaseUrl.'/'.$path;
        $method = $request->getMethod();

        // Forward the request to Typesense server and return the response
        try {
            $response = $this->client->request($method, $url, [
                'headers' => [
                    'X-TYPESENSE-API-KEY' => $this->typesenseApiKey,
                ],
                'body' => $request->getContent(),
            ]);

            return new Response($response->getContent(), $response->getStatusCode(), $response->getHeaders());
        } catch (\Exception $e) {
            return new Response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
