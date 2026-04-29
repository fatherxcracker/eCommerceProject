<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\Twig;

abstract class BaseController
{
    public function __construct(
        protected Twig $view,
        protected string $basePath = ''
    ) {}

    protected function render(Response $response, string $template, array $data = []): Response
    {
        return $this->view->render($response, $template, $data);
    }

    protected function redirect(Response $response, string $path, int $status = 302): Response
    {
        $url = str_starts_with($path, 'http') ? $path : $this->basePath . $path;
        return $response->withHeader('Location', $url)->withStatus($status);
    }

    protected function json(Response $response, mixed $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR));
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
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    protected function isAdmin(): bool
    {
        return ($_SESSION['user_role'] ?? '') === 'admin';
    }
}
