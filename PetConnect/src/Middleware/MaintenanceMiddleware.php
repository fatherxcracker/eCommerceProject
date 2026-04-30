<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Views\Twig;

class MaintenanceMiddleware
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private Twig $twig,
        private string $basePath = ''
    ) {}

    public function __invoke(Request $request, Handler $handler): Response
    {
        if (!MAINTENANCE_MODE) {
            return $handler->handle($request);
        }

        $response = $this->responseFactory->createResponse(503);
        return $this->twig->render($response, 'maintenance/maintenance.twig');
    }
}