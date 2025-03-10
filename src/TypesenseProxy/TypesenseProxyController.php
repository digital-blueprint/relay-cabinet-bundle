<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseProxy;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TypesenseProxyController extends AbstractController
{
    private $typesenseService;

    public function __construct(TypesenseProxyService $typesenseService)
    {
        $this->typesenseService = $typesenseService;
    }

    #[Route(path: '/cabinet/typesense/{path}', name: 'typesense_proxy', requirements: ['path' => '.+'])]
    public function proxy(Request $request, string $path): Response
    {
        return $this->typesenseService->doProxyRequest($path, $request);
    }
}
