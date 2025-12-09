<?php
// db/config.php
// Constantes de l'application
define('APP_NAME', 'µBibliothèque');
define('APP_VERSION', '1.1');
define('LIB_NAME', 'Résidence Jean Madern'); // Sera remplacé par get_bib_name() si utilisé

// Constantes pour les fichiers XML
define('BOOKS_FILE', 'data/books.xml');
define('MEMBERS_FILE', 'data/members.xml');
define('LOANS_FILE', 'data/loans.xml');
define('ADMIN_FILE', 'data/admin.xml');

// --- Chemins pour les ressources (COUVERTURES) ---

// 1. Chemin Absolu Système de Fichiers (Utilisé par PHP pour lire/écrire)
// __DIR__ est le dossier 'config'. On remonte d'un niveau pour être à la racine de 'bibliotheque/'
//define('ROOT_PATH', __DIR__ . '/../');
define('ROOT_PATH', dirname(__DIR__) . '/');

// Paramètres pour les couvertures
// Chemin du répertoire covers/ (depuis la racine système)
define('COVERS_STORAGE_PATH', __DIR__ . '/../covers/');
// Chemin Web (Utilisé par le HTML dans les balises <img>)
define('COVERS_WEB_PATH', 'covers/');
// Nom de la couverture par défaut
define('DEFAULT_COVER', 'default.jpg');
define('COVER_HEIGHT_MAX', 210); // Hauteur maximale en pixels
define('JPEG_QUALITY', 80);      // Qualité JPEG (0-100)
//
// Durée de la session (en secondes)
define('SESSION_LIFETIME', 3600); // 1 heure

// Nombre de lignes dans un tableau de données pour la pagination
define('ITEMS_PER_PAGE', 50);


/**
 * Récupère le nom de la bibliothèque stocké dans l'attribut "name"
 * de l'élément racine <admins> du fichier data/admin.xml.
 * @return string Le nom de la bibliothèque, ou ??? si non trouvé.
 */
function get_bib_name() {
    // Appel de la fonction helper pour charger le fichier XML
    $xml_admins = xml_load(ADMIN_FILE);
    
    // Vérifie si le chargement a réussi et si l'attribut 'name' est présent sur la racine <admins>
    if ($xml_admins && isset($xml_admins['name'])) {
        // Retourne la valeur de l'attribut 'name'
        return (string)$xml_admins['name'];
    }
    
    // Valeur par défaut si le fichier n'existe pas ou l'attribut est manquant
    return '???'; 
}

function get_mode() {
    $xml_admins = xml_load(ADMIN_FILE);
    if ($xml_admins && isset($xml_admins['mode'])) {
        // Retourne la valeur de l'attribut 'mode'
        return (string)$xml_admins['mode'];
    }
    return 'cover';
}

