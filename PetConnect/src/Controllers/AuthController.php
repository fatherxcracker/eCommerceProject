<?php

namespace App\Controllers;

use App\Models\User;
use Slim\Views\Twig;

class AuthController extends BaseController
{
    public function __construct(
        Twig $view,
        private Database $db   
    ) {
        parent::__construct($view);
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

        $_SESSION['user_id']   = $user->id;
        $_SESSION['user_name'] = $user->name;
        $_SESSION['user_role'] = $user->role;

        $this->flash('success', 'Account created! Welcome to PetConnect.');
        return $this->redirect($response, '/');
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

        if ($user->totp_secret) {
            $_SESSION['2fa_pending_user_id'] = $user->id;
            return $this->redirect($response, '/2fa');
        }

        $this->setUserSession($user);
        $this->flash('success', "Welcome back, {$user->name}!");
        return $this->redirect($response, '/');
    }

    public function show2FA(Request $request, Response $response): Response
    {
        if (empty($_SESSION['2fa_pending_user_id'])) {
            return $this->redirect($response, '/login');
        }
        return $this->render($response, 'auth/2fa_verify.twig');
    }

    public function verify2FA(Request $request, Response $response): Response
    {
        $data   = (array) $request->getParsedBody();
        $userId = $_SESSION['2fa_pending_user_id'] ?? null;

        if (!$userId) {
            return $this->redirect($response, '/login');
        }

        $user = User::find($userId);
        $code = $data['code'] ?? '';

        // TODO: validate TOTP code against $user->totp_secret using a TOTP library
        $isValid = true; // placeholder — replace with real TOTP check

        if (!$isValid) {
            $this->flash('error', 'Invalid verification code.');
            return $this->redirect($response, '/2fa');
        }

        unset($_SESSION['2fa_pending_user_id']);
        $this->setUserSession($user);
        return $this->redirect($response, '/');
    }

    public function logout(Request $request, Response $response): Response
    {
        session_destroy();
        return $this->redirect($response, '/login');
    }

    public function profile(Request $request, Response $response): Response
    {
        $user = User::find($this->currentUserId());
        return $this->render($response, 'auth/profile.twig', ['user' => $user]);
    }

    public function updateProfile(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $user = User::find($this->currentUserId());

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
        // TODO: implement email-based password reset flow
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
