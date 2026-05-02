<?php

namespace App\Controllers;

use App\Models\User;
use App\Services\Mailer;
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

        User::create([
            'name'          => htmlspecialchars($data['name']),
            'email'         => strtolower(trim($data['email'])),
            'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
            'role'          => User::ROLE_USER,
        ]);

        $this->flash('success', 'Account created! Please log in.');
        return $this->redirect($response, '/login');
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

        // Generate a 6-digit code and store it in the session (user is NOT logged in yet)
        $code    = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = time() + 300; // 5 minutes

        $_SESSION['2fa_user_id']  = $user->id;
        $_SESSION['2fa_code']     = $code;
        $_SESSION['2fa_expires']  = $expires;
        $_SESSION['2fa_attempts'] = 0; // track wrong guesses

        // Send the code by email
        try {
            Mailer::send(
                $user->email,
                $user->name,
                'Your PetConnect verification code',
                "Hi {$user->name},\n\nYour one-time login code is: {$code}\n\nThis code expires in 5 minutes. If you did not request this, please ignore this email.\n\n— PetConnect"
            );
        } catch (\RuntimeException $e) {
            // Email failed — clear 2FA session and tell the user
            unset($_SESSION['2fa_user_id'], $_SESSION['2fa_code'], $_SESSION['2fa_expires'], $_SESSION['2fa_attempts']);
            $this->flash('error', 'We could not send your verification code. Please try again later.');
            return $this->redirect($response, '/login');
        }

        return $this->redirect($response, '/2fa');
    }

    public function show2FA(Request $request, Response $response): Response
    {
        // If someone navigates to /2fa directly without logging in first, send them back
        if (empty($_SESSION['2fa_user_id'])) {
            return $this->redirect($response, '/login');
        }

        return $this->render($response, 'auth/2fa_verify.twig');
    }

    public function verify2FA(Request $request, Response $response): Response
    {
        if (empty($_SESSION['2fa_user_id'])) {
            return $this->redirect($response, '/login');
        }

        $data      = (array) $request->getParsedBody();
        $submitted = trim($data['code'] ?? '');

        // Check if the code has expired
        if (time() > ($_SESSION['2fa_expires'] ?? 0)) {
            unset($_SESSION['2fa_user_id'], $_SESSION['2fa_code'], $_SESSION['2fa_expires']);
            $this->flash('error', 'Verification code expired. Please log in again.');
            return $this->redirect($response, '/login');
        }

        // Check if the code matches (hash_equals prevents timing attacks)
        if (!hash_equals($_SESSION['2fa_code'], $submitted)) {
            $_SESSION['2fa_attempts'] = ($_SESSION['2fa_attempts'] ?? 0) + 1;

            // After 3 wrong attempts, kill the session and force a fresh login
            if ($_SESSION['2fa_attempts'] >= 3) {
                unset($_SESSION['2fa_user_id'], $_SESSION['2fa_code'], $_SESSION['2fa_expires'], $_SESSION['2fa_attempts']);
                $this->flash('error', 'Too many incorrect attempts. Please log in again.');
                return $this->redirect($response, '/login');
            }

            $remaining = 3 - $_SESSION['2fa_attempts'];
            $this->flash('error', "Invalid code. {$remaining} attempt(s) remaining.");
            return $this->redirect($response, '/2fa');
        }

        // Code is correct — load the user and log them in
        $userId = $_SESSION['2fa_user_id'];
        unset($_SESSION['2fa_user_id'], $_SESSION['2fa_code'], $_SESSION['2fa_expires']);

        $user = User::find($userId);
        if (!$user) {
            $this->flash('error', 'Session error. Please log in again.');
            return $this->redirect($response, '/login');
        }

        $this->setUserSession($user);
        $this->flash('success', "Welcome back, {$user->name}!");
        return $this->redirect($response, '/pets');
    }

    public function logout(Request $request, Response $response): Response
    {
        session_destroy();
        return $this->redirect($response, '/login');
    }

    public function showResetPassword(Request $request, Response $response): Response
    {
        return $this->render($response, 'auth/reset_password.twig');
    }

    public function resetPassword(Request $request, Response $response): Response
    {
        $data  = (array) $request->getParsedBody();
        $email = strtolower(trim($data['email'] ?? ''));

        // Always show the same message — prevents attackers from discovering which emails are registered
        $genericMsg = 'If that email is registered, a reset code has been sent.';

        $user = User::findByEmail($email);
        if (!$user) {
            $this->flash('info', $genericMsg);
            return $this->redirect($response, '/reset-password');
        }

        $code    = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = time() + 600; // 10 minutes

        // Store a hash of the code in the DB — plain code is never saved
        $user->fill([
            'reset_code_hash' => hash('sha256', $code),
            'reset_expires'   => $expires,
            'reset_attempts'  => 0,
        ]);
        $user->save();

        try {
            Mailer::send(
                $user->email,
                $user->name,
                'Reset your PetConnect password',
                "Hi {$user->name},\n\nYour password reset code is: {$code}\n\nThis code expires in 10 minutes.\n\nIf you did not request this, you can safely ignore this email.\n\n— PetConnect"
            );
        } catch (\RuntimeException $e) {
            $this->flash('error', 'We could not send the reset email. Please try again later.');
            return $this->redirect($response, '/reset-password');
        }

        $_SESSION['reset_email'] = $email;

        $this->flash('info', $genericMsg);
        return $this->redirect($response, '/reset-password/verify');
    }

    public function showVerifyReset(Request $request, Response $response): Response
    {
        if (empty($_SESSION['reset_email'])) {
            return $this->redirect($response, '/reset-password');
        }

        return $this->render($response, 'auth/reset_verify.twig');
    }

    public function verifyResetCode(Request $request, Response $response): Response
    {
        if (empty($_SESSION['reset_email'])) {
            return $this->redirect($response, '/reset-password');
        }

        $data      = (array) $request->getParsedBody();
        $submitted = trim($data['code'] ?? '');
        $user      = User::findByEmail($_SESSION['reset_email']);

        if (!$user) {
            unset($_SESSION['reset_email']);
            $this->flash('error', 'Session expired. Please try again.');
            return $this->redirect($response, '/reset-password');
        }

        // Check expiry
        if (time() > ($user->reset_expires ?? 0)) {
            unset($_SESSION['reset_email']);
            $this->flash('error', 'Reset code expired. Please request a new one.');
            return $this->redirect($response, '/reset-password');
        }

        // Increment attempt counter before checking — prevents brute force
        $attempts = (int) ($user->reset_attempts ?? 0) + 1;
        $user->fill(['reset_attempts' => $attempts]);
        $user->save();

        // Check if the code matches
        if (!hash_equals((string) ($user->reset_code_hash ?? ''), hash('sha256', $submitted))) {
            if ($attempts >= 3) {
                // Too many wrong guesses — wipe the code and force a fresh request
                $user->fill(['reset_code_hash' => null, 'reset_expires' => null, 'reset_attempts' => 0]);
                $user->save();
                unset($_SESSION['reset_email']);
                $this->flash('error', 'Too many incorrect attempts. Please request a new reset code.');
                return $this->redirect($response, '/reset-password');
            }

            $remaining = 3 - $attempts;
            $this->flash('error', "Invalid code. {$remaining} attempt(s) remaining.");
            return $this->redirect($response, '/reset-password/verify');
        }

        // Code is correct — authorize the password change step
        unset($_SESSION['reset_email']);
        $_SESSION['reset_user_id'] = $user->id;

        // Immediately wipe the code so it cannot be reused
        $user->fill(['reset_code_hash' => null, 'reset_expires' => null, 'reset_attempts' => 0]);
        $user->save();

        return $this->redirect($response, '/reset-password/new');
    }

    public function showNewPassword(Request $request, Response $response): Response
    {
        if (empty($_SESSION['reset_user_id'])) {
            return $this->redirect($response, '/reset-password');
        }

        return $this->render($response, 'auth/reset_new_password.twig');
    }

    public function updatePassword(Request $request, Response $response): Response
    {
        if (empty($_SESSION['reset_user_id'])) {
            return $this->redirect($response, '/reset-password');
        }

        $data     = (array) $request->getParsedBody();
        $password = $data['password'] ?? '';
        $confirm  = $data['confirm']  ?? '';

        if (strlen($password) < 8) {
            $this->flash('error', 'Password must be at least 8 characters.');
            return $this->redirect($response, '/reset-password/new');
        }

        if ($password !== $confirm) {
            $this->flash('error', 'Passwords do not match.');
            return $this->redirect($response, '/reset-password/new');
        }

        $user = User::find((int) $_SESSION['reset_user_id']);
        if (!$user) {
            unset($_SESSION['reset_user_id']);
            $this->flash('error', 'Session error. Please try again.');
            return $this->redirect($response, '/reset-password');
        }

        $user->fill(['password_hash' => password_hash($password, PASSWORD_BCRYPT)]);
        $user->save();

        unset($_SESSION['reset_user_id']);
        $this->flash('success', 'Password updated! Please log in with your new password.');
        return $this->redirect($response, '/login');
    }

    private function setUserSession(User $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id']     = $user->id;
        $_SESSION['user_name']   = $user->name;
        $_SESSION['user_role']   = $user->role;
        $_SESSION['user_avatar'] = $user->avatar ?? '';
    }
}
