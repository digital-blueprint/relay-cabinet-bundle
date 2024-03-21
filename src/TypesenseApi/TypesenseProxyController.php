<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseApi;

use Dbp\Relay\CabinetBundle\Service\TypesenseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TypesenseProxyController extends AbstractController
{
    private $typesenseService;

    public function __construct(TypesenseService $typesenseService)
    {
        $this->typesenseService = $typesenseService;
    }

    /**
     * @Route("/cabinet/typesense/{path}", name="typesense_proxy", requirements={"path" = ".+"})
     */
    public function proxy(Request $request, string $path): Response
    {
        // TODO: Check permissions

        return $this->typesenseService->doProxyRequest($path, $request);
    }
}
