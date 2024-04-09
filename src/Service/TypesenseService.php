<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Service;

use Dbp\Relay\CabinetBundle\Authorization\AuthorizationService;
use Dbp\Relay\CoreBundle\Exception\ApiError;
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

    /**
     * @var AuthorizationService
     */
    private $auth;

    private string $typesenseApiKey;

    private string $typesenseBaseUrl;

    public function __construct(HttpClientInterface $client, AuthorizationService $auth)
    {
        $this->client = $client;
        $this->auth = $auth;
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
        if (!$this->auth->isAuthenticated()) {
            throw new ApiError(Response::HTTP_UNAUTHORIZED, 'access denied');
        }

        // Do basic authorization checks for the provided bearer token
        // TODO: Check permissions
        $this->auth->checkCanUse();

        // return new Response('Try later', Response::HTTP_UNAUTHORIZED);
        // var_dump($request->get('x-typesense-api-key'));

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

            // We must not send all headers back to the client!
            // The request will be broken if we do, and we will get a "Network Error" in the browser
            $headers = [
                'Content-Type' => $response->getHeaders()['content-type'],
            ];

            return new Response($response->getContent(), $response->getStatusCode(), $headers);
        } catch (\Exception $e) {
            return new Response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
