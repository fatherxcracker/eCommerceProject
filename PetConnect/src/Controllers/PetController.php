<?php

namespace App\Controllers;

use App\Models\Category;
use App\Models\Database;
use App\Models\Pet;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class PetController extends BaseController
{
    public function __construct(Twig $view, private Database $db)
    {
        parent::__construct($view);
    }

    public function index(Request $request, Response $response): Response
    {
        $params     = $request->getQueryParams();
        $categories = Category::all();
        $pets       = empty($params) ? Pet::with('category')->get() : Pet::filter($params);

        return $this->render($response, 'pets/pet_list.twig', [
            'pets'       => $pets,
            'categories' => $categories,
            'filters'    => $params,
        ]);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $pet = Pet::with('category')->find((int) $args['id']);

        if (!$pet) {
            return $response->withStatus(404);
        }

        return $this->render($response, 'pets/pet_detail.twig', ['pet' => $pet]);
    }

    public function search(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams()['q'] ?? '';
        $pets  = Pet::search($query);

        return $this->render($response, 'pets/pet_search.twig', [
            'pets'  => $pets,
            'query' => $query,
        ]);
    }

    public function liveSearch(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams()['q'] ?? '';
        $pets  = Pet::search($query)->map(fn($p) => [
            'id'    => $p->id,
            'name'  => $p->name,
            'breed' => $p->breed,
            'image' => $p->image,
        ]);

        return $this->json($response, $pets);
    }

    public function filter(Request $request, Response $response, array $args): Response
    {
        $category = Category::where('name', $args['category'])->first();
        $pets     = $category ? Pet::findByCategory($category->id) : collect();

        return $this->render($response, 'pets/pet_list.twig', [
            'pets'            => $pets,
            'categories'      => Category::all(),
            'active_category' => $args['category'],
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        return $this->render($response, 'pets/pet_form.twig', [
            'categories' => Category::all(),
            'pet'        => null,
        ]);
    }

    public function store(Request $request, Response $response): Response
    {
        $data       = (array) $request->getParsedBody();
        $uploadedFiles = $request->getUploadedFiles();

        // Handle image upload
        $imagePath = $this->handleImageUpload($uploadedFiles['image'] ?? null);

        Pet::create([
            'name'        => htmlspecialchars($data['name']),
            'species'     => $data['species'],
            'breed'       => $data['breed'],
            'age'         => (int) $data['age'],
            'size'        => $data['size'],
            'location'    => $data['location'],
            'description' => htmlspecialchars($data['description']),
            'image'       => $imagePath,
            'status'      => Pet::STATUS_AVAILABLE,
            'category_id' => (int) $data['category_id'],
        ]);

        $this->flash('success', 'Pet listing created.');
        return $this->redirect($response, '/admin/pets');
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $pet = Pet::find((int) $args['id']);

        if (!$pet) {
            return $response->withStatus(404);
        }

        return $this->render($response, 'pets/pet_form.twig', [
            'pet'        => $pet,
            'categories' => Category::all(),
        ]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $pet  = Pet::find((int) $args['id']);
        $data = (array) $request->getParsedBody();

        if (!$pet) {
            return $response->withStatus(404);
        }

        $pet->fill([
            'name'        => htmlspecialchars($data['name']),
            'species'     => $data['species'],
            'breed'       => $data['breed'],
            'age'         => (int) $data['age'],
            'size'        => $data['size'],
            'location'    => $data['location'],
            'description' => htmlspecialchars($data['description']),
            'status'      => $data['status'],
            'category_id' => (int) $data['category_id'],
        ]);

        $uploadedFiles = $request->getUploadedFiles();
        if (!empty($uploadedFiles['image'])) {
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
            return 'default-pet.jpg';
        }

        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        $filename   = sprintf('%s.%s', bin2hex(random_bytes(8)), $extension);
        $uploadDir  = __DIR__ . '/../../public/uploads/pets/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $uploadedFile->moveTo($uploadDir . $filename);
        return '/uploads/pets/' . $filename;
    }
}
