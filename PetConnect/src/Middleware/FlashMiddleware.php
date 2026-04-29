<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Views\Twig;

class FlashMiddleware
{
    public function __construct(private Twig $view) {}

    public function __invoke(Request $request, Handler $handler): Response
    {
        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);

        $this->view->getEnvironment()->addGlobal('flash', $flash);
        $this->view->getEnvironment()->addGlobal('session', $_SESSION);

        return $handler->handle($request);
    }
}
