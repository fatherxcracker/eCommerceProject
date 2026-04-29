<?php
// todo: implement security middleware
namespace App\Middleware;
    
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;

class SecurityMiddleware
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private string $basePath = ''
    ) {}

    public function __invoke(Request $request, Handler $handler): Response
    {
        return $handler->handle($request);
    }
}