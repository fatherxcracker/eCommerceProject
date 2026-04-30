<?php

session_start();

//set maintenance mode to false if we finish maintenance
define('MAINTENANCE_MODE', true);

use App\Controllers\AdminController;
use App\Controllers\AdoptionController;
use App\Controllers\AuthController;
use App\Controllers\PetController;
use App\Middleware\AdminMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\FlashMiddleware;
use App\Middleware\MaintenanceMiddleware;
use App\Middleware\SecurityMiddleware;
use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RedBeanPHP\R;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

require __DIR__ . '/vendor/autoload.php';

// ── 1. DATABASE ───────────────────────────────────────────────────────────────
if (!is_dir(__DIR__ . '/var')) {
    mkdir(__DIR__ . '/var', 0755, true);
}
R::setup('sqlite:' . __DIR__ . '/var/petconnect.db');

// ── 2. BASE PATH ──────────────────────────────────────────────────────────────
$basePath = rtrim(str_ireplace('index.php', '', $_SERVER['SCRIPT_NAME']), '/');

// ── 3. TWIG ───────────────────────────────────────────────────────────────────
$twig = Twig::create(__DIR__ . '/templates', ['cache' => false]);
$twig->getEnvironment()->addGlobal('base_path', $basePath);

// ── 4. DI CONTAINER ───────────────────────────────────────────────────────────
$container = new Container();
AppFactory::setContainer($container);

$container->set(PetController::class,      fn() => new PetController($twig, $basePath));
$container->set(AuthController::class,     fn() => new AuthController($twig, $basePath));
$container->set(AdoptionController::class, fn() => new AdoptionController($twig, $basePath));
$container->set(AdminController::class,    fn() => new AdminController($twig, $basePath));

// ── 5. APPLICATION ────────────────────────────────────────────────────────────
$app = AppFactory::create();
$app->setBasePath($basePath);
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// ── 6. MIDDLEWARE ─────────────────────────────────────────────────────────────
$app->add(TwigMiddleware::create($app, $twig));
$app->add(new FlashMiddleware($twig));
$app->add(new SecurityMiddleware($app->getResponseFactory(), $basePath));
$app->add(new MaintenanceMiddleware($app->getResponseFactory(), $twig, $basePath));
$app->addErrorMiddleware(true, true, true);

// ── 7. SEED ROUTE ─────────────────────────────────────────────────────────────
$app->get('/seed', function (Request $request, Response $response) use ($basePath): Response {
    R::wipe('adoptionhistory');
    R::wipe('adoptionrequest');
    R::wipe('pet');
    R::wipe('category');

    $categories = ['Dog', 'Cat', 'Bird', 'Rabbit'];
    $catIds     = [];
    foreach ($categories as $name) {
        $cat       = R::dispense('category');
        $cat->name = $name;
        R::store($cat);
        $catIds[$name] = $cat->id;
    }

    $pets = [
        ['name' => 'Bella',  'species' => 'Dog',    'breed' => 'Golden Retriever', 'age' => 2, 'size' => 'Large',  'location' => 'Montreal',  'status' => 'available', 'category_id' => $catIds['Dog'],    'description' => 'Bella is a friendly and energetic Golden Retriever who loves to play fetch and cuddle on the couch.'],
        ['name' => 'Milo',   'species' => 'Cat',    'breed' => 'Tabby',            'age' => 1, 'size' => 'Small',  'location' => 'Laval',     'status' => 'available', 'category_id' => $catIds['Cat'],    'description' => 'Milo is a curious tabby cat who loves sunny spots and cozy blankets.'],
        ['name' => 'Rocky',  'species' => 'Dog',    'breed' => 'Husky',            'age' => 3, 'size' => 'Large',  'location' => 'Longueuil', 'status' => 'pending',   'category_id' => $catIds['Dog'],    'description' => 'Rocky is an adventurous Husky who needs a home with a big yard and active owners.'],
        ['name' => 'Luna',   'species' => 'Cat',    'breed' => 'Siamese',          'age' => 2, 'size' => 'Small',  'location' => 'Montreal',  'status' => 'available', 'category_id' => $catIds['Cat'],    'description' => 'Luna is an elegant Siamese with striking blue eyes and a gentle personality.'],
        ['name' => 'Max',    'species' => 'Dog',    'breed' => 'Labrador',         'age' => 4, 'size' => 'Large',  'location' => 'Brossard',  'status' => 'available', 'category_id' => $catIds['Dog'],    'description' => 'Max is a loyal and patient Labrador — perfect for families with children.'],
        ['name' => 'Tweety', 'species' => 'Bird',   'breed' => 'Canary',           'age' => 1, 'size' => 'Small',  'location' => 'Montreal',  'status' => 'available', 'category_id' => $catIds['Bird'],   'description' => 'Tweety fills every room with cheerful song and is easy to care for.'],
        ['name' => 'Coco',   'species' => 'Rabbit', 'breed' => 'Holland Lop',      'age' => 1, 'size' => 'Small',  'location' => 'Laval',     'status' => 'available', 'category_id' => $catIds['Rabbit'], 'description' => 'Coco is a fluffy Holland Lop rabbit who loves hay, veggies, and gentle cuddles.'],
        ['name' => 'Rex',    'species' => 'Dog',    'breed' => 'German Shepherd',  'age' => 5, 'size' => 'Large',  'location' => 'Montreal',  'status' => 'adopted',   'category_id' => $catIds['Dog'],    'description' => 'Rex is a well-trained German Shepherd who found his forever home.'],
    ];

    foreach ($pets as $data) {
        $bean = R::dispense('pet');
        foreach ($data as $key => $value) {
            $bean->$key = $value;
        }
        $bean->image = null;
        R::store($bean);
    }

    // Seed one admin user
    $existing = R::findOne('user', 'email = ?', ['admin@petconnect.ca']);
    if (!$existing) {
        $admin                = R::dispense('user');
        $admin->name          = 'Admin';
        $admin->email         = 'admin@petconnect.ca';
        $admin->password_hash = password_hash('admin1234', PASSWORD_BCRYPT);
        $admin->role          = 'admin';
        R::store($admin);
    }

    $response->getBody()->write(
        '<h1 style="font-family:sans-serif;padding:2rem">Database seeded!</h1>' .
        '<p style="font-family:sans-serif;padding:0 2rem">' .
        count($pets) . ' pets · ' . count($categories) . ' categories · 1 admin user (admin@petconnect.ca / admin1234)' .
        '</p><p style="font-family:sans-serif;padding:0 2rem">' .
        '<a href="' . $basePath . '/pets">Browse pets →</a></p>'
    );
    return $response;
});

// ── 8. HOME ───────────────────────────────────────────────────────────────────
$app->get('/', function (Request $request, Response $response) use ($basePath): Response {
    return $response->withHeader('Location', $basePath . '/pets')->withStatus(302);
});

// ── 9. PET ROUTES ─────────────────────────────────────────────────────────────
// Specific routes before parameterised {id}
$app->get('/pets',                           [PetController::class, 'index']);
$app->get('/pets/search',                    [PetController::class, 'search']);
$app->get('/pets/live-search',               [PetController::class, 'liveSearch']);
$app->get('/pets/category/{category}',       [PetController::class, 'filter']);
$app->get('/pets/{id:[0-9]+}',              [PetController::class, 'show']);

// ── 10. AUTH ROUTES ───────────────────────────────────────────────────────────
$app->get('/register',       [AuthController::class, 'showRegister']);
$app->post('/register',      [AuthController::class, 'register']);
$app->get('/login',          [AuthController::class, 'showLogin']);
$app->post('/login',         [AuthController::class, 'login']);
$app->get('/2fa',            [AuthController::class, 'show2FA']);
$app->post('/2fa',           [AuthController::class, 'verify2FA']);
$app->post('/logout',        [AuthController::class, 'logout']);
$app->get('/profile',        [AuthController::class, 'profile']);
$app->post('/profile',       [AuthController::class, 'updateProfile']);
$app->get('/reset-password', [AuthController::class, 'showResetPassword']);
$app->post('/reset-password',[AuthController::class, 'resetPassword']);

// ── 11. ADOPTION ROUTES ───────────────────────────────────────────────────────
$app->get('/pets/{id:[0-9]+}/adopt',  [AdoptionController::class, 'showApplyForm']);
$app->post('/pets/{id:[0-9]+}/adopt', [AdoptionController::class, 'apply']);
$app->get('/adoptions',               [AdoptionController::class, 'history']);
$app->get('/adoptions/{id:[0-9]+}',  [AdoptionController::class, 'status']);

// ── 12. ADMIN ROUTES (protected) ─────────────────────────────────────────────
$authMiddleware  = new AuthMiddleware($app->getResponseFactory(), $basePath);
$adminMiddleware = new AdminMiddleware($app->getResponseFactory(), $basePath);

$app->group('/admin', function (\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('',                                 [AdminController::class, 'dashboard']);
    $group->get('/pets',                            [AdminController::class, 'managePets']);
    $group->get('/pets/create',                     [PetController::class,   'create']);
    $group->post('/pets',                           [PetController::class,   'store']);
    $group->get('/pets/{id:[0-9]+}/edit',          [PetController::class,   'edit']);
    $group->post('/pets/{id:[0-9]+}',              [PetController::class,   'update']);
    $group->post('/pets/{id:[0-9]+}/delete',       [PetController::class,   'delete']);
    $group->get('/users',                           [AdminController::class, 'manageUsers']);
    $group->post('/users/{id:[0-9]+}/delete',      [AdminController::class, 'deleteUser']);
    $group->get('/adoptions',                       [AdminController::class, 'manageAdoptions']);
    $group->post('/adoptions/{id:[0-9]+}/approve', [AdoptionController::class, 'approve']);
    $group->post('/adoptions/{id:[0-9]+}/reject',  [AdoptionController::class, 'reject']);
})->add($adminMiddleware)->add($authMiddleware);

// ── 13. RUN ───────────────────────────────────────────────────────────────────
$app->run();