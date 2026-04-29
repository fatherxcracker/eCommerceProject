<?php

namespace App\Controllers;

use App\Models\AdoptionRequest;
use App\Models\Pet;
use App\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class AdminController extends BaseController
{
    public function __construct(Twig $view, string $basePath = '')
    {
        parent::__construct($view, $basePath);
    }

    public function dashboard(Request $request, Response $response): Response
    {
        return $this->render($response, 'admin/dashboard.twig', [
            'stats' => [
                'total_pets'       => Pet::count(),
                'available_pets'   => Pet::count('status = ?', [Pet::STATUS_AVAILABLE]),
                'adopted_pets'     => Pet::count('status = ?', [Pet::STATUS_ADOPTED]),
                'total_users'      => User::count('role = ?', [User::ROLE_USER]),
                'pending_requests' => AdoptionRequest::count('status = ?', [AdoptionRequest::STATUS_PENDING]),
            ],
            'recent_requests' => AdoptionRequest::findWhere(
                'status = ? ORDER BY submitted_at DESC LIMIT 5',
                [AdoptionRequest::STATUS_PENDING]
            ),
        ]);
    }

    public function managePets(Request $request, Response $response): Response
    {
        $pets = Pet::all('ORDER BY id DESC');
        return $this->render($response, 'admin/dashboard.twig', ['pets' => $pets, 'view' => 'pets']);
    }

    public function manageUsers(Request $request, Response $response): Response
    {
        $users = User::findWhere('role = ?', [User::ROLE_USER]);
        return $this->render($response, 'admin/user_list.twig', ['users' => $users]);
    }

    public function deleteUser(Request $request, Response $response, array $args): Response
    {
        $user = User::find((int) $args['id']);

        if ($user && !$user->isAdmin()) {
            $user->delete();
            $this->flash('success', 'User removed.');
        }

        return $this->redirect($response, '/admin/users');
    }

    public function manageAdoptions(Request $request, Response $response): Response
    {
        $requests = AdoptionRequest::all('ORDER BY submitted_at DESC');
        return $this->render($response, 'admin/adoption_mgmt.twig', ['requests' => $requests]);
    }
}
