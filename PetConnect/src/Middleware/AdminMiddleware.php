<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;

class AdminMiddleware
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private string $basePath = ''
    ) {}

    public function __invoke(Request $request, Handler $handler): Response
    {
        if (($_SESSION['user_role'] ?? '') !== 'admin') {
            return $this->responseFactory->createResponse(302)
                ->withHeader('Location', $this->basePath . '/');
        }

        return $handler->handle($request);
    }
}
