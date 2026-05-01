<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class ChatController extends BaseController
{
    private const API_URL           = 'https://api.groq.com/openai/v1/chat/completions';
    private const MODEL             = 'llama-3.3-70b-versatile';
    private const MAX_TOKENS        = 1024;
    private const MAX_HISTORY_PAIRS = 10;

    private const SYSTEM_PROMPT = <<<'PROMPT'
You are a friendly and knowledgeable assistant for PetConnect, a pet adoption platform based in the Montreal area of Canada. Your role is to help logged-in users with three things:

1. Pet information and advice — answer questions about dog breeds, cat care, bird care, rabbit care, feeding, training, vet visits, and general animal welfare.
2. Adoption guidance — explain the adoption request process on PetConnect: users browse pets, click "Adopt", fill in a message, and wait for admin approval. Statuses are "available" (no request yet), "pending" (request submitted and under review), and "adopted" (approved and completed). Users can check their adoption history and request status at /adoptions.
3. Site navigation — help users find pages: browse all pets at /pets, filter by category (Dog, Cat, Bird, Rabbit), search by name or breed using the search bar, view adoption history at /adoptions, edit profile at /profile.

Keep answers concise (2–4 sentences unless more detail is genuinely needed). Be warm and encouraging about pet adoption. Do not make up specific pet listings or invent data — if asked about which specific pets are currently available, suggest the user visit /pets to browse the live listings.
PROMPT;

    public function __construct(Twig $view, string $basePath = '')
    {
        parent::__construct($view, $basePath);
    }

    public function message(Request $request, Response $response): Response
    {
        if (!$this->isLoggedIn()) {
            return $this->json($response, ['error' => 'Unauthorized'], 401);
        }

        $body    = (array) $request->getParsedBody();
        $userMsg = trim($body['message'] ?? '');

        if ($userMsg === '' || mb_strlen($userMsg) > 2000) {
            return $this->json($response, ['error' => 'Invalid message'], 400);
        }

        $apiKey = $_ENV['GROQ_API_KEY'] ?? '';
        if ($apiKey === '') {
            return $this->json($response, ['error' => 'Chat service is not configured.'], 503);
        }

        $history   = $_SESSION['chat_history'] ?? [];
        $history[] = ['role' => 'user', 'content' => $userMsg];

        $maxMessages = self::MAX_HISTORY_PAIRS * 2;
        if (count($history) > $maxMessages) {
            $history = array_slice($history, -$maxMessages);
        }

        // Prepend system message then pass history directly (role/content format matches Groq)
        $messages = array_merge(
            [['role' => 'system', 'content' => self::SYSTEM_PROMPT]],
            $history
        );

        $payload = json_encode([
            'model'      => self::MODEL,
            'max_tokens' => self::MAX_TOKENS,
            'messages'   => $messages,
        ], JSON_THROW_ON_ERROR);

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_SSL_VERIFYPEER => false, // dev-only: WAMP lacks a CA bundle
        ]);

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);

        if ($curlErr !== '' || $raw === false) {
            return $this->json($response, ['error' => 'Could not reach the chat service. Please try again.'], 503);
        }

        $decoded = json_decode($raw, true);

        if ($httpCode !== 200 || !isset($decoded['choices'][0]['message']['content'])) {
            $groqError = $decoded['error']['message'] ?? $raw;
            error_log('Groq API error ' . $httpCode . ': ' . $raw);
            return $this->json($response, ['error' => 'Chat error: ' . $groqError], 502);
        }

        $assistantMsg = $decoded['choices'][0]['message']['content'];

        $history[]                = ['role' => 'assistant', 'content' => $assistantMsg];
        $_SESSION['chat_history'] = $history;

        return $this->json($response, ['reply' => $assistantMsg]);
    }
}
