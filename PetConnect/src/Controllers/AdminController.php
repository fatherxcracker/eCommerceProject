<?php

namespace App\Controllers;

use App\Models\AdoptionRequest;
use App\Models\Pet;
use App\Models\User;
use Slim\Views\Twig;

class AdminController extends BaseController
{
    public function __construct(Twig $view, private Database $db)
    {
        parent::__construct($view);
    }

    public function dashboard(Request $request, Response $response): Response
    {
        return $this->render($response, 'admin/dashboard.twig', [
            'stats' => [
                'total_pets'       => Pet::count(),
                'available_pets'   => Pet::where('status', Pet::STATUS_AVAILABLE)->count(),
                'adopted_pets'     => Pet::where('status', Pet::STATUS_ADOPTED)->count(),
                'total_users'      => User::where('role', User::ROLE_USER)->count(),
                'pending_requests' => AdoptionRequest::where('status', AdoptionRequest::STATUS_PENDING)->count(),
            ],
            'recent_requests' => AdoptionRequest::with(['user', 'pet'])
                ->where('status', AdoptionRequest::STATUS_PENDING)
                ->latest()
                ->take(5)
                ->get(),
        ]);
    }

    public function managePets(Request $request, Response $response): Response
    {
        $pets = Pet::with('category')->latest()->get();
        return $this->render($response, 'admin/dashboard.twig', ['pets' => $pets, 'view' => 'pets']);
    }

    public function manageUsers(Request $request, Response $response): Response
    {
        $users = User::where('role', User::ROLE_USER)->latest()->get();
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
        $requests = AdoptionRequest::with(['user', 'pet'])->latest()->get();
        return $this->render($response, 'admin/adoption_mgmt.twig', ['requests' => $requests]);
    }
}
