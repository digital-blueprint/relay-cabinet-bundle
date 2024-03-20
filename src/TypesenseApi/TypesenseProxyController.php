<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseApi;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TypesenseProxyController extends AbstractController
{
    private $client;
    private $typesenseHost;
    private $typesenseApiKey;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
        // TODO: Use our own configuration
        $this->typesenseHost = $_ENV['TYPESENSE_HOST'];
        $this->typesenseApiKey = $_ENV['TYPESENSE_API_KEY'];
    }

    /**
     * @Route("/cabinet/typesense/{path}", name="typesense_proxy", requirements={"path" = ".+"})
     */
    public function proxy(Request $request, string $path): Response
    {
        // TODO: Check permissions

        $url = $this->typesenseHost.'/'.$path;
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
