<?php

namespace App\Middleware;

class AdminMiddleware 
{
    public function process(Request $request, Handler $handler): Response
    {
        if (($_SESSION['user_role'] ?? '') !== 'admin') {
            $response = new SlimResponse();
            return $response
                ->withHeader('Location', '/')
                ->withStatus(403);
        }

        return $handler->handle($request);
    }
}
