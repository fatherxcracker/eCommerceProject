<?php

namespace App\Controllers;

use App\Models\Category;
use App\Models\Pet;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class PetController extends BaseController
{
    public function __construct(Twig $view, string $basePath = '')
    {
        parent::__construct($view, $basePath);
    }

    public function index(Request $request, Response $response): Response
    {
        $params     = array_filter($request->getQueryParams());
        $categories = Category::all('ORDER BY name ASC');
        $pets       = empty($params) ? Pet::all('ORDER BY id DESC') : Pet::filter($params);

        return $this->render($response, 'pets/pet_list.twig', [
            'pets'       => $pets,
            'categories' => $categories,
            'filters'    => $request->getQueryParams(),
        ]);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $pet = Pet::find((int) $args['id']);

        if (!$pet) {
            $response->getBody()->write('<h1>404 — Pet not found</h1>');
            return $response->withStatus(404);
        }

        return $this->render($response, 'pets/pet_detail.twig', ['pet' => $pet]);
    }

    public function search(Request $request, Response $response): Response
    {
        $query = trim($request->getQueryParams()['q'] ?? '');
        $pets  = Pet::search($query);

        return $this->render($response, 'pets/pet_search.twig', [
            'pets'  => $pets,
            'query' => $query,
        ]);
    }

    public function liveSearch(Request $request, Response $response): Response
    {
        $query   = trim($request->getQueryParams()['q'] ?? '');
        $pets    = Pet::search($query);
        $payload = array_map(fn($p) => [
            'id'    => (int) $p->id,
            'name'  => $p->name,
            'breed' => $p->breed,
            'image' => $p->image,
        ], $pets);

        return $this->json($response, $payload);
    }

    public function filter(Request $request, Response $response, array $args): Response
    {
        $category = Category::findOne('name = ?', [$args['category']]);
        $pets     = $category ? Pet::findByCategory((int) $category->id) : [];

        return $this->render($response, 'pets/pet_list.twig', [
            'pets'            => $pets,
            'categories'      => Category::all('ORDER BY name ASC'),
            'active_category' => $args['category'],
            'filters'         => [],
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        return $this->render($response, 'pets/pet_form.twig', [
            'categories' => Category::all('ORDER BY name ASC'),
            'pet'        => null,
        ]);
    }

    public function store(Request $request, Response $response): Response
    {
        $data          = (array) $request->getParsedBody();
        $uploadedFiles = $request->getUploadedFiles();
        $imagePath     = $this->handleImageUpload($uploadedFiles['image'] ?? null);

        Pet::create([
            'name'        => htmlspecialchars($data['name'] ?? ''),
            'species'     => $data['species'] ?? '',
            'breed'       => $data['breed'] ?? '',
            'age'         => (int) ($data['age'] ?? 0),
            'size'        => $data['size'] ?? '',
            'location'    => $data['location'] ?? '',
            'description' => htmlspecialchars($data['description'] ?? ''),
            'image'       => $imagePath,
            'status'      => Pet::STATUS_AVAILABLE,
            'category_id' => (int) ($data['category_id'] ?? 0),
        ]);

        $this->flash('success', 'Pet listing created.');
        return $this->redirect($response, '/admin/pets');
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $pet = Pet::find((int) $args['id']);

        if (!$pet) {
            $response->getBody()->write('<h1>404 — Pet not found</h1>');
            return $response->withStatus(404);
        }

        return $this->render($response, 'pets/pet_form.twig', [
            'pet'        => $pet,
            'categories' => Category::all('ORDER BY name ASC'),
        ]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $pet  = Pet::find((int) $args['id']);
        $data = (array) $request->getParsedBody();

        if (!$pet) {
            $response->getBody()->write('<h1>404 — Pet not found</h1>');
            return $response->withStatus(404);
        }

        $pet->fill([
            'name'        => htmlspecialchars($data['name'] ?? ''),
            'species'     => $data['species'] ?? '',
            'breed'       => $data['breed'] ?? '',
            'age'         => (int) ($data['age'] ?? 0),
            'size'        => $data['size'] ?? '',
            'location'    => $data['location'] ?? '',
            'description' => htmlspecialchars($data['description'] ?? ''),
            'status'      => $data['status'] ?? Pet::STATUS_AVAILABLE,
            'category_id' => (int) ($data['category_id'] ?? 0),
        ]);

        $uploadedFiles = $request->getUploadedFiles();
        if (!empty($uploadedFiles['image']) && $uploadedFiles['image']->getError() === UPLOAD_ERR_OK) {
            $pet->image = $this->handleImageUpload($uploadedFiles['image']);
        }

        $pet->save();
        $this->flash('success', 'Pet listing updated.');
        return $this->redirect($response, '/admin/pets');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $pet = Pet::find((int) $args['id']);

        if ($pet) {
            $pet->delete();
            $this->flash('success', 'Pet listing removed.');
        }

        return $this->redirect($response, '/admin/pets');
    }

    private function handleImageUpload($uploadedFile): string
    {
        if (!$uploadedFile || $uploadedFile->getError() !== UPLOAD_ERR_OK) {
            return '';
        }

        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        $filename  = sprintf('%s.%s', bin2hex(random_bytes(8)), $extension);
        $uploadDir = __DIR__ . '/../../Assets/uploads/pets/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $uploadedFile->moveTo($uploadDir . $filename);
        return '/Assets/uploads/pets/' . $filename;
    }
}
