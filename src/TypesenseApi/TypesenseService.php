<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseApi;

use Dbp\Relay\CabinetBundle\Authorization\AuthorizationService;
use Dbp\Relay\CabinetBundle\Service\ConfigurationService;
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

    private ConfigurationService $config;

    public function __construct(HttpClientInterface $client, AuthorizationService $auth, ConfigurationService $config)
    {
        $this->client = $client;
        $this->auth = $auth;
        $this->config = $config;
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

        $url = $this->config->getTypesenseApiUrl().'/'.$path;
        $method = $request->getMethod();

        // Forward the request to Typesense server and return the response
        try {
            $response = $this->client->request($method, $url, [
                'headers' => [
                    'X-TYPESENSE-API-KEY' => $this->config->getTypesenseApiKey(),
                ],
                'body' => $request->getContent(),
            ]);

            // We must not send all headers back to the client!
            // The request will be broken if we do, and we will get a "Network Error" in the browser
            $headers = [
                // Disallow throwing of exceptions for getHeaders, so we can output the response as we get it
                'Content-Type' => $response->getHeaders(false)['content-type'],
            ];

            // Disallow throwing of exceptions for getContent, so we can output the response as we get it
            return new Response($response->getContent(false), $response->getStatusCode(), $headers);
        } catch (\Exception $e) {
            return new Response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
