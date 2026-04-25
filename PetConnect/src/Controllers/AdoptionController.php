<?php

namespace App\Controllers;

use App\Models\AdoptionHistory;
use App\Models\AdoptionRequest;
use App\Models\Pet;
use Slim\Views\Twig;

class AdoptionController extends BaseController
{
    public function __construct(Twig $view, private Database $db)
    {
        parent::__construct($view);
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
        $pet    = Pet::find((int) $args['id']);
        $data   = (array) $request->getParsedBody();
        $userId = $this->currentUserId();

        if (!$pet || !$pet->isAvailable()) {
            $this->flash('error', 'This pet is not available for adoption.');
            return $this->redirect($response, '/pets');
        }

        $existing = AdoptionRequest::where('user_id', $userId)
            ->where('pet_id', $pet->id)
            ->where('status', AdoptionRequest::STATUS_PENDING)
            ->first();

        if ($existing) {
            $this->flash('error', 'You already have a pending request for this pet.');
            return $this->redirect($response, '/pets/' . $pet->id);
        }

        $adoptionRequest = AdoptionRequest::create([
            'user_id'      => $userId,
            'pet_id'       => $pet->id,
            'status'       => AdoptionRequest::STATUS_PENDING,
            'message'      => htmlspecialchars($data['message'] ?? ''),
            'submitted_at' => now(),
        ]);

        $pet->status = Pet::STATUS_PENDING;
        $pet->save();

        $this->flash('success', 'Adoption request submitted! We will review it soon.');
        return $this->redirect($response, '/adoptions/' . $adoptionRequest->id);
    }

    public function history(Request $request, Response $response): Response
    {
        $requests = AdoptionRequest::findByUser($this->currentUserId());
        $history  = AdoptionHistory::findByUser($this->currentUserId());

        return $this->render($response, 'adoption/history.twig', [
            'requests' => $requests,
            'history'  => $history,
        ]);
    }

    public function status(Request $request, Response $response, array $args): Response
    {
        $adoptionRequest = AdoptionRequest::with('pet')
            ->where('id', (int) $args['id'])
            ->where('user_id', $this->currentUserId())
            ->first();

        if (!$adoptionRequest) {
            return $response->withStatus(404);
        }

        return $this->render($response, 'adoption/status.twig', [
            'request' => $adoptionRequest,
        ]);
    }

    public function approve(Request $request, Response $response, array $args): Response
    {
        $adoptionRequest = AdoptionRequest::with('pet')->find((int) $args['id']);

        if (!$adoptionRequest) {
            return $response->withStatus(404);
        }

        $adoptionRequest->updateStatus(AdoptionRequest::STATUS_APPROVED);

        $adoptionRequest->pet->status = Pet::STATUS_ADOPTED;
        $adoptionRequest->pet->save();

        AdoptionHistory::create([
            'user_id'      => $adoptionRequest->user_id,
            'pet_id'       => $adoptionRequest->pet_id,
            'completed_at' => now(),
        ]);

        AdoptionRequest::where('pet_id', $adoptionRequest->pet_id)
            ->where('id', '!=', $adoptionRequest->id)
            ->where('status', AdoptionRequest::STATUS_PENDING)
            ->update(['status' => AdoptionRequest::STATUS_REJECTED]);

        $this->flash('success', 'Adoption request approved.');
        return $this->redirect($response, '/admin/adoptions');
    }

    public function reject(Request $request, Response $response, array $args): Response
    {
        $adoptionRequest = AdoptionRequest::with('pet')->find((int) $args['id']);

        if (!$adoptionRequest) {
            return $response->withStatus(404);
        }

        $adoptionRequest->updateStatus(AdoptionRequest::STATUS_REJECTED);

        $otherPending = AdoptionRequest::where('pet_id', $adoptionRequest->pet_id)
            ->where('status', AdoptionRequest::STATUS_PENDING)
            ->exists();

        if (!$otherPending) {
            $adoptionRequest->pet->status = Pet::STATUS_AVAILABLE;
            $adoptionRequest->pet->save();
        }

        $this->flash('success', 'Adoption request rejected.');
        return $this->redirect($response, '/admin/adoptions');
    }
}
