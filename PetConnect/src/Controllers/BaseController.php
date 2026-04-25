<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\Twig;

abstract class BaseController
{
    public function __construct(protected Twig $view) {}

    protected function render(Response $response, string $template, array $data = []): Response
    {
        return $this->view->render($response, $template, $data);
    }

    protected function redirect(Response $response, string $url, int $status = 302): Response
    {
        return $response->withHeader('Location', $url)->withStatus($status);
    }

    protected function json(Response $response, mixed $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    protected function flash(string $type, string $message): void
    {
        $_SESSION['flash'][$type][] = $message;
    }

    protected function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    protected function currentUserId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    protected function isAdmin(): bool
    {
        return ($_SESSION['user_role'] ?? '') === 'admin';
    }
}
