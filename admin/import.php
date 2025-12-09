<?php
/**
 * admin/import.php
 * Module de fonction pour l'import de données.
 */

// require_once 'admin.php' inclut déjà '../db/functions.php'
require_once 'admin.php'; 
require_once '../db/crud_books.php'; // Pour la fonction book_add()
require_once '../db/config.php'; // Pour les constantes de chemin (COVERS_STORAGE_PATH, BOOKS_FILE)


/**
 * Exécute le processus d'importation de données.
 * @param array|null $xml_file_data Informations de $_FILES pour le fichier XML.
 * @param array|null $cover_zip_data Informations de $_FILES pour le fichier ZIP des couvertures.
 * @param string $format Le format du fichier XML ('gcstar', 'calibre', 'custom').
 * @return mixed Un message d'erreur (string) ou le nombre de livres ajoutés (int) en cas de succès.
 */
function perform_import($xml_file_data, $cover_zip_data, $format) { 

    // --- 0. PRÉREQUIS : Vérification des fonctions de base ---
    if (!function_exists('book_add') || !function_exists('id_get_next') || !function_exists('id_update_last')) {
        return "Une fonction essentielle à l'import (book_add, id_get_next ou id_update_last) est manquante. Vérifiez les inclusions.";
    }

    // 1. Vérification du fichier XML
    if (!is_array($xml_file_data) || $xml_file_data['error'] !== UPLOAD_ERR_OK) {
        return "Erreur lors du téléversement du fichier de données XML.";
    }
    
    $xml_temp_path = $xml_file_data['tmp_name'];

    // 2. Traitement du fichier ZIP de couvertures (si présent)
    if (is_array($cover_zip_data) && $cover_zip_data['error'] === UPLOAD_ERR_OK) {
        $cover_zip_temp_path = $cover_zip_data['tmp_name'];
        $result = cover_unzip($cover_zip_temp_path); 
        if ($result !== true) {
            return "Erreur lors du traitement des couvertures : " . $result;
        }
    }

    // 3. Chargement du XML
    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($xml_temp_path);
    if ($xml === false) {
        libxml_clear_errors();
        return "Le fichier XML est invalide ou corrompu.";
    }

    // 4. Traitement du XML selon le format
    $result = "Format d'importation inconnu.";
    $import_count = 0; // Ajout d'une variable pour le nombre de livres

    if ($format === 'gcstar') {
        $import_count = process_gcstar_xml($xml);
    } elseif ($format === 'calibre') {
        $import_count = process_calibre_xml($xml);
    } elseif ($format === 'custom') {
        $import_count = process_custom_xml($xml);
    }
    
    // 5. Gestion du résultat de parsing
    if (is_int($import_count)) {
        if ($import_count > 0) {
            return $import_count; // Retourne le nombre de livres (entier)
        } else {
            return "Aucun livre valide trouvé ou ajouté dans le fichier " . strtoupper($format) . ".";
        }
    }
    
    // Si $import_count est une chaîne (message d'erreur), on la retourne
    return $import_count;
}

// --------------------------------------------------------------------------
// FONCTION UTILITAIRE GÉNÉRIQUE
// --------------------------------------------------------------------------

// ... (import_add_book reste inchangée) ...

/**
 * Ajoute un livre à la base de données XML et met à jour l'ID séquentiel.
 * @param array $book_data Tableau des données du livre (clés: tit, auts, etc.).
 * @param string $cover_file Nom du fichier de couverture.
 * @return bool Vrai si l'ajout a réussi.
 */
function import_add_book($book_data, $cover_file) {
    if (empty($book_data['tit'])) {
        return false;
    }
    
    $next_id = id_get_next(BOOKS_FILE); 
    
    if (book_add($book_data, $cover_file, $next_id)) {
        id_update_last(BOOKS_FILE, $next_id);
        return true;
    }
    return false;
}

// --------------------------------------------------------------------------
// FONCTIONS DE PARSING SPÉCIFIQUE
// --------------------------------------------------------------------------

/**
 * Parse un fichier XML au format GCstar et ajoute les livres à la base.
 * @param SimpleXMLElement $xml L'objet SimpleXMLElement chargé.
 * @return mixed Message d'erreur (string) ou le nombre de livres ajoutés (int).
 */
function process_gcstar_xml($xml) {
    if (!isset($xml->item)) {
        return "Le fichier GCstar ne contient aucun tag <item>.";
    }

    $import_count = 0;

    foreach ($xml->item as $item) {
        // ... (Logique de parsing) ...
        $authors_list = array();
        if (isset($item->authors->line)) {
            foreach ($item->authors->line as $author_line) {
                $author_raw = (string)$author_line->col;
                if (!empty($author_raw)) {
                    $parts = explode(', ', $author_raw, 2);
                    if (count($parts) === 2) {
                        $authors_list[] = trim($parts[1]) . ' ' . trim($parts[0]);
                    } else {
                        $authors_list[] = $author_raw;
                    }
                }
            }
        }
        $author_name = implode('+', $authors_list);

        $genre_list = array();
        if (isset($item->genre->line)) {
            foreach ($item->genre->line as $genre_line) {
                $genre_raw = (string)$genre_line->col;
                if (!empty($genre_raw)) {
                    $genre_list[] = $genre_raw;
                }
            }
        }
        $genres = implode('+', $genre_list);

        $pub_date_raw = (string)$item['publication'];
        $pub_date_formatted = '';
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})/', $pub_date_raw, $matches)) {
            $pub_date_formatted = $matches[3] . '/' . $matches[2] . '/' . $matches[1];
        } else if (preg_match('/(\d{4})/', $pub_date_raw, $matches)) {
             $pub_date_formatted = $matches[1] . '/01/01';
        }

        $cover_file = '';
        if (isset($item['cover'])) {
            $cover_path_parts = explode('/', (string)$item['cover']);
            $cover_file = end($cover_path_parts);
        }

        $book_data = array(
            'tit'       => (string)$item['title'],
            'auts'      => $author_name,
            'pub'       => (string)$item['publisher'],
            'isbn'      => (string)$item['isbn'],
            'pub_date'  => $pub_date_formatted,
            'desc'      => (string)$item->description,
            'cats'      => $genres,
            'note'      => 0,
        );

        // 6. Ajout du livre via la fonction générique
        if (import_add_book($book_data, $cover_file)) {
            $import_count++;
        }
    }
    
    // Retourne le nombre. perform_import gérera si c'est 0.
    return $import_count; 
}


/**
 * Parse un fichier XML au format Calibre et ajoute les livres à la base.
 * @param SimpleXMLElement $xml L'objet SimpleXMLElement chargé.
 * @return mixed Message d'erreur (string) ou le nombre de livres ajoutés (int).
 */
function process_calibre_xml($xml) {
    if (!isset($xml->record)) {
        return "Le fichier Calibre ne contient aucun tag <record>.";
    }

    $import_count = 0;

    foreach ($xml->record as $item) {
        // ... (Logique de parsing) ...
        $authors_list = array();
        if (isset($item->authors->author)) {
            foreach ($item->authors->author as $author_xml) {
                $author_raw = (string)$author_xml;
                if (!empty($author_raw)) {
                    $parts = explode(', ', $author_raw, 2);
                    if (count($parts) === 2) {
                        $authors_list[] = trim($parts[1]) . ' ' . trim($parts[0]);
                    } else {
                        $authors_list[] = $author_raw;
                    }
                }
            }
        }
        $author_name = implode('+', $authors_list);

        $genre_list = array();
        if (isset($item->tags->tag)) {
            foreach ($item->tags->tag as $tag) {
                $genre_raw = (string)$tag;
                if (!empty($genre_raw)) {
                    $genre_list[] = $genre_raw;
                }
            }
        }
        $genres = implode('+', $genre_list);

        $pub_date_raw = (string)$item->pubdate;
        $pub_date_formatted = '';
        
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $pub_date_raw, $matches)) {
            $pub_date_formatted = $matches[1] . '/' . $matches[2] . '/' . $matches[3];
        } else if (preg_match('/(\d{4})/', $pub_date_raw, $matches)) {
             $pub_date_formatted = $matches[1] . '/01/01';
        }
        
        $cover_file = '';
        if (isset($item->cover)) {
            $cover_path_parts = explode('/', (string)$item->cover);
            $cover_file = end($cover_path_parts);
        }

        $description_raw = (string)$item->comments;
        $description = trim(strip_tags($description_raw)); 
        $description = html_entity_decode($description, ENT_QUOTES, 'UTF-8');
        $description = trim($description);

        $book_data = array(
            'tit'       => (string)$item->title,
            'auts'      => $author_name,
            'pub'       => (string)$item->publisher,
            'isbn'      => (string)$item->isbn,
            'pub_date'  => $pub_date_formatted,
            'desc'      => $description, 
            'cats'      => $genres,
            'note'      => 0,
        );

        // 7. Ajout du livre via la fonction générique
        if (import_add_book($book_data, $cover_file)) {
            $import_count++;
        }
    }
    
    // Retourne le nombre.
    return $import_count; 
}


/**
 * Parse un fichier XML au format Personnalisé (µBib export) et ajoute les livres à la base.
 * @param SimpleXMLElement $xml L'objet SimpleXMLElement chargé.
 * @return mixed Message d'erreur (string) ou le nombre de livres ajoutés (int).
 */
function process_custom_xml($xml) {
    if (!isset($xml->book)) {
        return "Le fichier Personnalisé ne contient aucun tag <book>.";
    }

    $import_count = 0;

    foreach ($xml->book as $book) {
        
        $pub_date_raw = (string)$book['pub_date'];
        $cover_file = (string)$book['cov'];
        
        $book_data = array(
            'tit'       => (string)$book['tit'],
            'auts'      => (string)$book['auts'], 
            'pub'       => (string)$book['pub'],
            'isbn'      => (string)$book['isbn'],
            'pub_date'  => $pub_date_raw, 
            'desc'      => isset($book->desc) ? (string)$book->desc : '', 
            'cats'      => (string)$book['cats'], 
            'note'      => (int)$book['rat'], 
        );

        // Ajout du livre via la fonction générique
        if (import_add_book($book_data, $cover_file)) {
            $import_count++;
        }
    }
    
    // Retourne le nombre.
    return $import_count; 
}


// ... (cover_unzip reste inchangée) ...
function cover_unzip($zip_path) { 
    if (!class_exists('ZipArchive')) {
        return "L'extension ZIP n'est pas activée sur ce serveur PHP.";
    }
    
    if (!is_dir(COVERS_STORAGE_PATH) && !mkdir(COVERS_STORAGE_PATH, 0777, true)) {
        return "Impossible de créer le répertoire des couvertures (" . COVERS_STORAGE_PATH . ").";
    }

    $zip = new ZipArchive;
    if ($zip->open($zip_path) === TRUE) {
        if ($zip->extractTo(COVERS_STORAGE_PATH)) {
            $zip->close();
            return true;
        } else {
            $zip->close();
            return "Échec de l'extraction des fichiers du ZIP dans le répertoire " . COVERS_STORAGE_PATH . ".";
        }
    } else {
        return "Impossible d'ouvrir le fichier ZIP de couvertures. Il est peut-être corrompu.";
    }
}
?>