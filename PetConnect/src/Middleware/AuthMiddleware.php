<?php

namespace App\Middleware;

class AuthMiddleware
{
    public function process(Request $request, Handler $handler): Response
    {
        if (empty($_SESSION['user_id'])) {
            $response = new SlimResponse();
            return $response
                ->withHeader('Location', '/login')
                ->withStatus(302);
        }

        return $handler->handle($request);
    }
}
