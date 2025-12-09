<?php
// random_book.php
// Ce script sélectionne aléatoirement l'ID d'un livre DISPONIBLE.

// 1. INCLUSIONS NÉCESSAIRES
require_once 'db/config.php';
require_once 'db/functions.php';

// 2. CHARGEMENT DES DONNÉES
$xml_books = xml_load(BOOKS_FILE);

if (!$xml_books) {
    // Erreur de chargement XML
    http_response_code(500);
    echo 'Erreur: Impossible de charger la base de livres.';
    exit;
}

// 3. FILTRAGE DES LIVRES DISPONIBLES
$available_ids = [];

foreach ($xml_books->book as $book) {
    // Vérifie si le livre est marqué comme disponible (disp=1)
    if (isset($book['disp']) && (int)$book['disp'] === 1) {
        $available_ids[] = (int)$book['id'];
    }
}

// 4. SÉLECTION ALÉATOIRE
if (empty($available_ids)) {
    // Aucun livre disponible
    http_response_code(404);
    echo 'Aucun livre disponible trouvé.';
    exit;
}

// Choisir un ID au hasard dans le tableau des IDs disponibles
$randomIndex = array_rand($available_ids);
$random_book_id = $available_ids[$randomIndex];

// 5. SORTIE
// Le script n'affiche que l'ID du livre choisi (ex: "42").
header('Content-Type: text/plain');
echo $random_book_id;
exit;

?>