<?php

namespace App\Middleware\Maintenance;    // namespace App\Middleware\Maintenance;   

use App\Response\SlimResponse;

class MaintenanceMiddleware
{
    public function process(Request $request, Handler $handler): Response
    {
        $response = new SlimResponse();
        return $response
            ->withHeader('Location', '/maintenance')
            ->withStatus(302);
    }
}