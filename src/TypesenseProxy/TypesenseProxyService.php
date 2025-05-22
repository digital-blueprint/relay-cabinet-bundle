<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseProxy;

use Dbp\Relay\CabinetBundle\Authorization\AuthorizationService;
use Dbp\Relay\CabinetBundle\Service\ConfigurationService;
use Dbp\Relay\CabinetBundle\TypesenseSync\TypesenseConnection;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TypesenseProxyService implements LoggerAwareInterface
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

    public function doProxyRequest(string $path, Request $request): Response
    {
        if (!$this->auth->isAuthenticated()) {
            throw new HttpException(Response::HTTP_UNAUTHORIZED, 'access denied');
        }

        try {
            $this->auth->checkCanUse();
        } catch (ApiError) {
            throw new HttpException(Response::HTTP_FORBIDDEN, 'access denied');
        }

        $isSearch = ($path === 'multi_search' || $path === 'search');

        // Scoped keys only work for search endpoints, and only with keys that only allow searching, so we use a scoped
        // key for searches, and another key for everything else.
        if ($isSearch) {
            $connection = new TypesenseConnection($this->config->getTypesenseApiUrl(), $this->config->getTypesenseApiKey());
            $proxyKey = $connection->getClient()->keys->generateScopedSearchKey(
                $this->config->getTypesenseProxyApiSearchKey(), ['cache_ttl' => $this->config->getTypesenseSearchCacheTtl()]);
        } else {
            $proxyKey = $this->config->getTypesenseProxyApiKey();
        }

        $url = $this->config->getTypesenseApiUrl().'/'.$path;
        $method = $request->getMethod();
        $queryParams = $request->query->all();
        // The header key wins over this in my testing, but just to be safe don't allow users to set
        // the api key by always overriding it here.
        $queryParams['x-typesense-api-key'] = $proxyKey;

        $requestContent = $request->getContent();

        if ($isSearch) {
            $partitionRequestContents = TypesensePartitionedSearch::splitJsonRequest($requestContent, $this->config->getTypesenseSearchPartitions());
            $responses = [];
            foreach ($partitionRequestContents as $partitionRequestContent) {
                $responses[] = $this->client->request($method, $url, [
                    'headers' => [
                        'X-TYPESENSE-API-KEY' => $proxyKey,
                    ],
                    'body' => $partitionRequestContent,
                    'query' => $queryParams,
                ]);
            }

            $responseContents = [];
            $status = null;
            $failContent = null;
            $headers = [];
            foreach ($responses as $response) {
                $status = $response->getStatusCode();
                $headers = $response->getHeaders(false);
                if ($status !== 200) {
                    $failContent = $response->getContent(false);
                    $responseContents = [];
                    // Something is wrong, cancel all
                    foreach ($responses as $r) {
                        $r->getStatusCode(); // docs say this is needed to stop throw on destruction
                        $r->cancel();
                    }
                    break;
                }
                $responseContents[] = $response->getContent(false);
            }
            $headers = [
                'Content-Type' => $headers['content-type'],
            ];

            if ($failContent !== null) {
                return new Response($failContent, $status, $headers);
            } else {
                return new Response(TypesensePartitionedSearch::mergeJsonResponses($requestContent, $responseContents), $status, $headers);
            }
        } else {
            // not a search, just pass through
            $response = $this->client->request($method, $url, [
                'headers' => [
                    'X-TYPESENSE-API-KEY' => $proxyKey,
                ],
                'body' => $requestContent,
                'query' => $queryParams,
            ]);
            $headers = $response->getHeaders(false);
            $headers = [
                'Content-Type' => $headers['content-type'],
            ];

            return new Response($response->getContent(false), $response->getStatusCode(), $headers);
        }
    }
}
