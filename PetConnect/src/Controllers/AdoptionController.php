<?php

namespace App\Controllers;

use App\Models\AdoptionHistory;
use App\Models\AdoptionRequest;
use App\Models\Pet;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class AdoptionController extends BaseController
{
    public function __construct(Twig $view, string $basePath = '')
    {
        parent::__construct($view, $basePath);
    }

    public function showApplyForm(Request $request, Response $response, array $args): Response
    {
        $pet = Pet::find((int) $args['id']);

        if (!$pet || !$pet->isAvailable()) {
            $this->flash('error', 'This pet is not available for adoption.');
            return $this->redirect($response, '/pets');
        }

        return $this->render($response, 'adoption/apply_form.twig', ['pet' => $pet]);
    }

    public function apply(Request $request, Response $response, array $args): Response
    {
        if (!$this->isLoggedIn()) {
            return $this->redirect($response, '/login');
        }

        $pet    = Pet::find((int) $args['id']);
        $data   = (array) $request->getParsedBody();
        $userId = $this->currentUserId();

        if (!$pet || !$pet->isAvailable()) {
            $this->flash('error', 'This pet is not available for adoption.');
            return $this->redirect($response, '/pets');
        }

        $existing = AdoptionRequest::findOne(
            'user_id = ? AND pet_id = ? AND status = ?',
            [$userId, $pet->id, AdoptionRequest::STATUS_PENDING]
        );

        if ($existing) {
            $this->flash('error', 'You already have a pending request for this pet.');
            return $this->redirect($response, '/pets/' . $pet->id);
        }

        $adoptionRequest = AdoptionRequest::create([
            'user_id'      => $userId,
            'pet_id'       => $pet->id,
            'status'       => AdoptionRequest::STATUS_PENDING,
            'message'      => htmlspecialchars($data['message'] ?? ''),
            'submitted_at' => date('Y-m-d H:i:s'),
        ]);

        $pet->status = Pet::STATUS_PENDING;
        $pet->save();

        $this->flash('success', 'Adoption request submitted! We will review it soon.');
        return $this->redirect($response, '/adoptions/' . $adoptionRequest->id);
    }

    public function history(Request $request, Response $response): Response
    {
        if (!$this->isLoggedIn()) {
            return $this->redirect($response, '/login');
        }

        $requests = AdoptionRequest::findByUser($this->currentUserId());
        $history  = AdoptionHistory::findByUser($this->currentUserId());

        return $this->render($response, 'adoption/history.twig', [
            'requests' => $requests,
            'history'  => $history,
        ]);
    }

    public function status(Request $request, Response $response, array $args): Response
    {
        if (!$this->isLoggedIn()) {
            return $this->redirect($response, '/login');
        }

        $adoptionRequest = AdoptionRequest::findOne(
            'id = ? AND user_id = ?',
            [(int) $args['id'], $this->currentUserId()]
        );

        if (!$adoptionRequest) {
            $response->getBody()->write('<h1>404 — Request not found</h1>');
            return $response->withStatus(404);
        }

        return $this->render($response, 'adoption/status.twig', [
            'request' => $adoptionRequest,
        ]);
    }

    public function approve(Request $request, Response $response, array $args): Response
    {
        $adoptionRequest = AdoptionRequest::find((int) $args['id']);

        if (!$adoptionRequest) {
            $response->getBody()->write('<h1>404 — Request not found</h1>');
            return $response->withStatus(404);
        }

        $adoptionRequest->updateStatus(AdoptionRequest::STATUS_APPROVED);

        $pet = Pet::find((int) $adoptionRequest->pet_id);
        if ($pet) {
            $pet->status = Pet::STATUS_ADOPTED;
            $pet->save();
        }

        AdoptionHistory::create([
            'user_id'      => $adoptionRequest->user_id,
            'pet_id'       => $adoptionRequest->pet_id,
            'completed_at' => date('Y-m-d H:i:s'),
        ]);

        AdoptionRequest::rejectOtherPending((int) $adoptionRequest->pet_id, (int) $adoptionRequest->id);

        $this->flash('success', 'Adoption approved.');
        return $this->redirect($response, '/admin/adoptions');
    }

    public function reject(Request $request, Response $response, array $args): Response
    {
        $adoptionRequest = AdoptionRequest::find((int) $args['id']);

        if (!$adoptionRequest) {
            $response->getBody()->write('<h1>404 — Request not found</h1>');
            return $response->withStatus(404);
        }

        $adoptionRequest->updateStatus(AdoptionRequest::STATUS_REJECTED);

        $otherPending = AdoptionRequest::findWhere(
            'pet_id = ? AND status = ?',
            [$adoptionRequest->pet_id, AdoptionRequest::STATUS_PENDING]
        );

        if (empty($otherPending)) {
            $pet = Pet::find((int) $adoptionRequest->pet_id);
            if ($pet) {
                $pet->status = Pet::STATUS_AVAILABLE;
                $pet->save();
            }
        }

        $this->flash('success', 'Adoption request rejected.');
        return $this->redirect($response, '/admin/adoptions');
    }
}
