<?php

namespace App\Controllers;

use App\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class AuthController extends BaseController
{
    public function __construct(Twig $view, string $basePath = '')
    {
        parent::__construct($view, $basePath);
    }

    public function showRegister(Request $request, Response $response): Response
    {
        return $this->render($response, 'auth/register.twig');
    }

    public function register(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();

        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            $this->flash('error', 'All fields are required.');
            return $this->redirect($response, '/register');
        }

        if (strlen($data['password']) < 8) {
            $this->flash('error', 'Password must be at least 8 characters.');
            return $this->redirect($response, '/register');
        }

        if (User::findByEmail($data['email'])) {
            $this->flash('error', 'Email already registered.');
            return $this->redirect($response, '/register');
        }

        $user = User::create([
            'name'          => htmlspecialchars($data['name']),
            'email'         => strtolower(trim($data['email'])),
            'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
            'role'          => User::ROLE_USER,
        ]);

        $this->setUserSession($user);
        $this->flash('success', 'Account created! Welcome to PetConnect.');
        return $this->redirect($response, '/pets');
    }

    public function showLogin(Request $request, Response $response): Response
    {
        return $this->render($response, 'auth/login.twig');
    }

    public function login(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $user = User::findByEmail($data['email'] ?? '');

        if (!$user || !$user->verifyPassword($data['password'] ?? '')) {
            $this->flash('error', 'Invalid email or password.');
            return $this->redirect($response, '/login');
        }

        $this->setUserSession($user);
        $this->flash('success', "Welcome back, {$user->name}!");
        return $this->redirect($response, '/pets');
    }

    public function show2FA(Request $request, Response $response): Response
    {
        return $this->render($response, 'auth/2fa_verify.twig');
    }

    public function verify2FA(Request $request, Response $response): Response
    {
        return $this->redirect($response, '/pets');
    }

    public function logout(Request $request, Response $response): Response
    {
        session_destroy();
        return $this->redirect($response, '/login');
    }

    public function profile(Request $request, Response $response): Response
    {
        if (!$this->isLoggedIn()) {
            return $this->redirect($response, '/login');
        }

        $user = User::find($this->currentUserId());
        return $this->render($response, 'auth/profile.twig', ['user' => $user]);
    }

    public function updateProfile(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $user = User::find($this->currentUserId());

        if (!$user) {
            return $this->redirect($response, '/login');
        }

        $user->name = htmlspecialchars($data['name'] ?? $user->name);

        if (!empty($data['password'])) {
            $user->password_hash = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        $user->save();
        $_SESSION['user_name'] = $user->name;
        $this->flash('success', 'Profile updated.');
        return $this->redirect($response, '/profile');
    }

    public function showResetPassword(Request $request, Response $response): Response
    {
        return $this->render($response, 'auth/reset_password.twig');
    }

    public function resetPassword(Request $request, Response $response): Response
    {
        $this->flash('info', 'If your email is registered, you will receive a reset link.');
        return $this->redirect($response, '/login');
    }

    private function setUserSession(User $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user->id;
        $_SESSION['user_name'] = $user->name;
        $_SESSION['user_role'] = $user->role;
    }
}
