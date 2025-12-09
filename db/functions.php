<?php

// db/functions.php - Fonctions utilitaires principales de l'application
require_once 'config.php';

/**
 * Met à jour l'attribut 'last' si le nouvel ID utilisé est supérieur à l'actuel 'last'.
 * @param string $filename
 * @param int $new_id
 * @return bool
 */
function id_update_last($filename, $new_id) {
    $xml = xml_load($filename);
    $current_last = (int) $xml['last'];

    if ($new_id > $current_last) {
        $xml['last'] = $new_id;
        return xml_save($xml, $filename);
    }
    return true;
}

/**
 * Vérifie si l'ID est déjà utilisé dans le fichier XML.
 * @param string $filename
 * @param int $id
 * @return bool
 */
function id_is_used($filename, $id) {
    $xml = xml_load($filename);

    foreach ($xml->$tag as $item) {
        if ((int) $item['id'] === $id) {
            return true;
        }
    }
    return false;
}

// =================================================================
// FONCTIONS DE GESTION XML DE BASE
// =================================================================

/**
 * Charge un fichier XML.
 * @param string $filename Le nom du fichier (e.g., BOOKS_FILE)
 * @return SimpleXMLElement|false
 */
function xml_load($filename) {
    // Détermine la balise racine pour la création si besoin
    if (strpos($filename, 'members.xml') !== false) {
        $root_tag = 'members';
    } elseif (strpos($filename, 'loans.xml') !== false) {
        $root_tag = 'loans';
    } elseif (strpos($filename, 'admin.xml') !== false) {
        $root_tag = 'admins';
    } else {
        $root_tag = 'library'; // books.xml
    }

    $path = ROOT_PATH . $filename;

    if (file_exists($path) && filesize($path) > 0) {
        $content = trim(file_get_contents($path));
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        libxml_clear_errors();

        if ($xml !== false) {
            // S'assure que l'attribut 'last' existe (utilisé pour les ID)
            if (!isset($xml['last'])) {
                $xml->addAttribute('last', '0');
            }
            return $xml;
        }
    }

    // Si le fichier est vide, n'existe pas ou est invalide, crée un objet SimpleXMLElement vide
    $xml = new SimpleXMLElement('<' . $root_tag . ' last="0"></' . $root_tag . '>');
    return $xml;
}

/**
 * Sauvegarde un objet SimpleXMLElement dans un fichier.
 * @param SimpleXMLElement $xml L'objet à sauvegarder
 * @param string $filename Le nom du fichier
 * @return bool
 */
function xml_save(SimpleXMLElement $xml, $filename) {
    $path = ROOT_PATH . $filename;

    // Utiliser DOMDocument pour un formatage plus lisible
    $dom = dom_import_simplexml($xml)->ownerDocument;
    $dom->formatOutput = true; // Active l'indentation
    // Tente de sauvegarder
    return $dom->save($path) !== false;
}

/**
 * Retourne l'ID suivant (last+1).
 * @param string $filename Le fichier (pour utiliser l'attribut 'last')
 * @return int Le nouvel ID
 */
function id_get_next($filename) {
    $xml = xml_load($filename);
    $last_id = (int) $xml['last'];
    $next_id = $last_id + 1;
    return $next_id;
}

/**
 * Lit le dernier ID utilisé pour SUGGÉRER le prochain ID SANS modifier l'attribut 'last' du XML.
 * (Corrige l'incrémentation lors du simple rafraîchissement.)
 * @param string $filename Le fichier (pour lire l'attribut 'last')
 * @return int L'ID suggéré (last + 1)
 */
function id_get_last($filename) {
    $xml = xml_load($filename);
    return (int) $xml['last'];
}

// =================================================================
// FONCTIONS POUR LES FILTRES 
// =================================================================

/**
 * Récupère et compte tous les auteurs uniques.
 * @return array Tableau associatif [auteur => count] trié par auteur.
 */
function counts_authors() {
    $xml_books = xml_load(BOOKS_FILE);
    $authors = array();
    foreach ($xml_books->book as $book) {
        $author_list = preg_split('/\+/', (string) $book['auts'], -1, PREG_SPLIT_NO_EMPTY);
        foreach ($author_list as $author) {
            $author = trim($author);
            if (!empty($author)) {
                $authors[$author] = isset($authors[$author]) ? $authors[$author] + 1 : 1;
            }
        }
    }
    ksort($authors);
    return $authors;
}

/**
 * Récupère et compte toutes les séries uniques.
 * @return array Tableau associatif [auteur => count] trié par auteur.
 */
function counts_series() {
    $xml_books = xml_load(BOOKS_FILE);
    $series = array();
    foreach ($xml_books->book as $book) {
        $serie_attr = (string) $book['ser'];
        if (!empty($serie_attr)) {
            $serie_list = preg_split('/\|/', $serie_attr, -1, PREG_SPLIT_NO_EMPTY);
            // Vérifier qu'on a bien au moins 2 éléments (numéro et nom)
            if (count($serie_list) >= 2) {
                $serie = trim($serie_list[1]);
                if (!empty($serie)) {
                    $series[$serie] = isset($series[$serie]) ? $series[$serie] + 1 : 1;
                }
            }
        }
    }
    ksort($series);
    return $series;
}

/**
 * Récupère et compte tous les genres uniques.
 * @return array Tableau associatif [genre => count] trié par genre.
 */
function counts_genres() {
    $xml_books = xml_load(BOOKS_FILE);
    $genres = array();
    foreach ($xml_books->book as $book) {
        $genre_list = preg_split('/\+/', (string) $book['cats'], -1, PREG_SPLIT_NO_EMPTY);
        foreach ($genre_list as $genre) {
            $genre = trim($genre);
            if (!empty($genre)) {
                $genres[$genre] = isset($genres[$genre]) ? $genres[$genre] + 1 : 1;
            }
        }
    }
    ksort($genres);
    return $genres;
}

/**
 * Démarre une session sécurisée
 */
function session_start_secure() {
    if (defined('IS_HTTPS') && IS_HTTPS) {
        ini_set('session.cookie_secure', 1);
    }
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Formate une date de 'AAAA/MM/JJ' en 'JJ/MM/AAAA'.
 * Retourne la date originale si le format n'est pas reconnu.
 * @param string $date_str La chaîne de date au format 'AAAA/MM/JJ'.
 * @return string La date formatée 'JJ/MM/AAAA' ou la chaîne originale.
 */
function date_toJJMMAAA($date_str) {
    // Vérifie si la chaîne correspond au format AAAA/MM/JJ
    if (!empty($date_str) && preg_match('/^(\d{4})\/(\d{2})\/(\d{2})$/', $date_str, $matches)) {
        return $matches[3] . '/' . $matches[2] . '/' . $matches[1];
    }
    return $date_str; // Retourne l'original si le format ne correspond pas
}

?>