<?php

namespace App\Middleware;

use Slim\Views\Twig;

class FlashMiddleware 
{
    public function __construct(private Twig $view) {}

    public function process(Request $request, Handler $handler): Response
    {
        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);

        $this->view->getEnvironment()->addGlobal('flash', $flash);

        return $handler->handle($request);
    }
}
