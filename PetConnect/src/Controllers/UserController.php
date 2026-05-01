<?php

namespace App\Controllers;

use App\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RedBeanPHP\R;
use Slim\Views\Twig;

class UserController extends BaseController
{
    public function __construct(Twig $view, string $basePath = '')
    {
        parent::__construct($view, $basePath);
    }

    public function profile(Request $request, Response $response): Response
    {
        if (!$this->isLoggedIn()) {
            return $this->redirect($response, '/login');
        }

        $userId = $this->currentUserId();
        $user   = User::find($userId);
        $stats  = [
            'total'    => R::count('adoptionrequest', 'user_id = ?', [$userId]),
            'approved' => R::count('adoptionrequest', 'user_id = ? AND status = ?', [$userId, 'approved']),
            'pending'  => R::count('adoptionrequest', 'user_id = ? AND status = ?', [$userId, 'pending']),
        ];
        return $this->render($response, 'auth/profile.twig', [
            'user'  => $user,
            'stats' => $stats,
        ]);
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

        $files = $request->getUploadedFiles();
        if (!empty($files['avatar']) && $files['avatar']->getError() === UPLOAD_ERR_OK) {
            $avatarPath = $this->handleAvatarUpload($files['avatar']);
            if ($avatarPath !== null) {
                $this->deleteAvatarFile($user->avatar);
                $user->avatar = $avatarPath;
            }
        }

        if (!empty($data['remove_avatar'])) {
            $this->deleteAvatarFile($user->avatar);
            $user->avatar = '';
        }

        $user->save();
        $_SESSION['user_name']   = $user->name;
        $_SESSION['user_avatar'] = $user->avatar;
        $this->flash('success', 'Profile updated.');
        return $this->redirect($response, '/profile');
    }

    public function deleteAccount(Request $request, Response $response): Response
    {
        if (!$this->isLoggedIn()) {
            return $this->redirect($response, '/login');
        }

        $userId = $this->currentUserId();

        R::exec('DELETE FROM adoptionhistory WHERE user_id = ?', [$userId]);
        R::exec('DELETE FROM adoptionrequest WHERE user_id = ?', [$userId]);

        $user = User::find($userId);
        if ($user) {
            $this->deleteAvatarFile($user->avatar);
            $user->delete();
        }

        session_destroy();
        return $this->redirect($response, '/');
    }

    private function handleAvatarUpload($uploadedFile): ?string
    {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $extension = strtolower(pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION));

        if (!in_array($extension, $allowed, true)) {
            $this->flash('error', 'Avatar must be a JPG, PNG, GIF, or WEBP image.');
            return null;
        }

        if ($uploadedFile->getSize() > 5 * 1024 * 1024) {
            $this->flash('error', 'Avatar must be smaller than 5 MB.');
            return null;
        }

        $filename  = sprintf('%s.%s', bin2hex(random_bytes(8)), $extension);
        $uploadDir = __DIR__ . '/../../Assets/uploads/avatars/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $uploadedFile->moveTo($uploadDir . $filename);
        return '/Assets/uploads/avatars/' . $filename;
    }

    private function deleteAvatarFile(?string $relativePath): void
    {
        if (!$relativePath) return;
        $absolute = __DIR__ . '/../..' . $relativePath;
        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }
}
