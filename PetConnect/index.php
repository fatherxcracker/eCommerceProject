<?php
session_start();

$pets = [
    ["name" => "Bella", "type" => "Dog", "breed" => "Golden Retriever", "age" => "2 years", "location" => "Montreal", "status" => "Available"],
    ["name" => "Milo", "type" => "Cat", "breed" => "Tabby", "age" => "1 year", "location" => "Laval", "status" => "Available"],
    ["name" => "Rocky", "type" => "Dog", "breed" => "Husky", "age" => "3 years", "location" => "Longueuil", "status" => "Pending"],
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $_SESSION["flash"] = "Your adoption request was submitted successfully!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PetConnect</title>
    <link rel="stylesheet" href="Assets/styles.css">
</head>
<body>

<?php if (!empty($_SESSION["flash"])): ?>
    <div class="flash flash--success">
        <?= htmlspecialchars($_SESSION["flash"]) ?>
    </div>
    <?php unset($_SESSION["flash"]); ?>
<?php endif; ?>

<nav class="navbar">
    <div class="navbar__logo">PetConnect</div>

    <div class="navbar__search">
        <input type="text" id="petSearch" placeholder="Search pets...">
    </div>

    <ul class="navbar__links">
        <li><a href="#">Home</a></li>
        <li><a href="#pets">Adopt</a></li>
        <li><a href="#assistant">AI Help</a></li>
    </ul>
</nav>

<section class="hero">
    <div class="hero__text">
        <p class="eyebrow">Find your new best friend</p>
        <h1>Adopt a pet and give them a loving home.</h1>
        <p>
            Browse available pets, search  and send an adoption request.
        </p>
        <a href="#pets" class="btn btn--primary btn--lg">Browse Pets</a>
    </div>

    <!-- <div class="hero__card">
        <h2>Today’s Match</h2>
        <p>Bella is friendly, calm, and ready for adoption.</p>
        <span class="badge badge--available">Available</span>
    </div>
</section> -->

<section class="container" id="pets">
    <h2>Available Pets</h2>

    <div class="pet-grid" id="petGrid">
        <?php foreach ($pets as $pet): ?>
            <article class="pet-card">
                <div class="pet-card__body">
                    <span class="badge badge--<?= strtolower($pet["status"]) ?>">
                        <?= htmlspecialchars($pet["status"]) ?>
                    </span>

                    <h2 class="pet-card__name"><?= htmlspecialchars($pet["name"]) ?></h2>

                    <p class="pet-card__meta">
                        <?= htmlspecialchars($pet["type"]) ?> ·
                        <?= htmlspecialchars($pet["breed"]) ?> ·
                        <?= htmlspecialchars($pet["age"]) ?>
                    </p>

                    <p class="pet-card__location">
                        📍 <?= htmlspecialchars($pet["location"]) ?>
                    </p>

                    <form method="POST">
                        <button class="btn btn--primary btn--full" type="submit">
                            Request Adoption
                        </button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section id="assistant">
    <div class="assistant-box">
        <h2>AI Pet Assistant</h2>
        <p>Have a question?</p>
        <input type="text" id="aiQuestion" placeholder="Ask: What pet is best for an apartment?">
        <button class="btn btn--secondary" onclick="answerQuestion()">Ask</button>
        <p id="aiAnswer"></p>
    </div>
</section>

<script src="Assets/app.js"></script>
</body>
</html>