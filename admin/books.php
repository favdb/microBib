<?php

require_once 'admin.php';
require_once '../db/crud_books.php';
require_once 'process_cover.php';
check_admin_access();

// --- Logique du Contrôleur ---

$mode = isset($_GET['mode']) ? $_GET['mode'] : 'list';
$book_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$filter_cover = isset($_GET['cov']) ? $_GET['cov'] : '0';
$message = '';
$error = '';
$post_data = []; // Initialisation
$xml_books = xml_load(BOOKS_FILE);

// ... (fetch_url_content reste identique) ...
if (!function_exists('fetch_url_content')) {

    /**
     * Tente de récupérer le contenu d'une URL en utilisant file_get_contents puis cURL.
     * @param string $url L'URL de la ressource.
     * @return string|false Le contenu ou false en cas d'échec.
     */
    function fetch_url_content($url) {
        // Tentative 1: file_get_contents
        $content = @file_get_contents($url);
        if ($content !== false) {
            return $content;
        }

        // Tentative 2: cURL
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_FAILONERROR, true);
            // Ajout d'un User-Agent pour éviter le blocage par certains serveurs
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MyBookApp/1.0)');
            $content = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code >= 200 && $http_code < 300 && $content !== false) {
                return $content;
            }
        }

        return false;
    }

}


// ------------------------------------------------------------------
// LOGIQUE DE TRAITEMENT POST (AJOUT/MODIFICATION)
// ------------------------------------------------------------------
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'id';
$sort_dir = isset($_GET['dir']) && $_GET['dir'] === 'desc' ? 'desc' : 'asc';
$current_page = isset($_GET['page']) ? (int) $_GET['page'] : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_data = $_POST;
    $is_update = isset($post_data['book_id']) && (int) $post_data['book_id'] > 0;
    $book_title = isset($post_data['tit']) ? trim($post_data['tit']) : '';
    $new_id_input = isset($post_data['id']) ? (int) $post_data['id'] : 0;

    // --- 1. TRAITEMENT DE LA COUVERTURE ---
    $cover_filename = isset($post_data['current_cover']) ? $post_data['current_cover'] : '';

    // CAS A : UPLOAD DE FICHIER
    if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
        $cover_info = $_FILES['cover'];
        $mime_type = mime_content_type($cover_info['tmp_name']);
        $result = cover_process_image($cover_info['tmp_name'], $mime_type, 'upload', $book_title);

        if (is_string($result) && strpos($result, 'Erreur') === 0) {
            $error = $result;
        } else {
            $cover_filename = $result;
        }
    }
    // CAS B : SAISIE D'URL
    elseif (isset($post_data['cover_url']) && trim($post_data['cover_url']) !== '' && empty($error)) {
        $url = trim($post_data['cover_url']);

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            $error = "Erreur: L'URL de la couverture fournie n'est pas une URL valide.";
        } else {
            $cover_filename = $url;
        }
    }

    // --- 2. CONVERSION DE LA DATE (JJ/MM/AAAA -> AAAA/MM/JJ) ---
    $pub_date_input = trim($post_data['pub_date']);
    $pub_date_storage = $pub_date_input;

    if (!empty($pub_date_input) && preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $pub_date_input, $matches)) {
        $pub_date_storage = $matches[3] . '/' . $matches[2] . '/' . $matches[1]; // AAAA/MM/JJ
    } elseif (empty($pub_date_input) || preg_match('/^(\d{4})\/(\d{2})\/(\d{2})$/', $pub_date_input)) {
        $pub_date_storage = $pub_date_input;
    } else {
        $error = "Le format de la date de publication doit être JJ/MM/AAAA.";
    }

    // --- 3. NORMALISATION DES DONNÉES ---
    $series_num_input = isset($post_data['ser_num']) ? trim($post_data['ser_num']) : '';
    $series_name_input = isset($post_data['ser_name']) ? trim($post_data['ser_name']) : '';
    $series_attr = '';

    if (!empty($series_num_input) && !empty($series_name_input)) {
        $series_attr = $series_num_input . '|' . $series_name_input;
    } elseif (!empty($series_name_input)) {
        $series_attr = '|' . $series_name_input;
    }

    $data = [
        'tit' => trim($post_data['tit']),
        'ser' => $series_attr,
        'auts' => trim($post_data['auts']),
        'pub' => trim($post_data['pub']),
        'pub_date' => $pub_date_storage,
        'isbn' => trim($post_data['isbn']),
        'cats' => trim($post_data['cats']),
        'desc' => trim($post_data['desc']),
        'cov' => $cover_filename,
        'note' => (int) $post_data['note'],
        'disp' => $is_update ? (isset($post_data['disp']) ? 1 : 0) : 1,
    ];

// --- 4. VALIDATION ET ACTION ---
    if (empty($data['tit']) || empty($data['auts']) || empty($data['desc']) || empty($data['cats'])) {
        $error = "Le Titre, l'Auteur, le Genre et la Description sont obligatoires.";
    }

    if (empty($error)) {
        if ($is_update) {
            $old_book_id = (int) $post_data['book_id'];
            $new_book_id = $new_id_input;

            // 1. Vérification de l'unicité du nouvel ID si différent de l'ancien
            if ($new_book_id !== $old_book_id && id_is_used(BOOKS_FILE, $new_book_id)) {
                $error = "Erreur: L'ID $new_book_id est déjà utilisé pour un autre livre.";
                $mode = 'edit'; // S'assurer qu'on reste en mode édition en cas d'erreur
            }
        
            if (empty($error)) { // Si pas d'erreur de validation (y compris l'ID en double)
                $new_cover_filename = ($cover_filename !== $post_data['current_cover']) ? $cover_filename : null;

                // MODIFICATION: L'appel à book_update passe l'ancien ID et le nouvel ID
                if (book_update($old_book_id, $new_book_id, $data, $new_cover_filename)) {
                    
                    // 2. Mise à jour de l'ID Maximum si le nouvel ID est supérieur
                    $current_last_id = id_get_last(BOOKS_FILE);
                    if ($new_book_id > $current_last_id) {
                        id_update_last(BOOKS_FILE, $new_book_id);
                    }
                    
                    $message = "Livre (ID: $new_book_id) modifié avec succès.";
                    $mode = 'list';
                    $xml_books = xml_load(BOOKS_FILE);

                    if (isset($post_data['return_sort'])) {
                        $sort_by = $post_data['return_sort'];
                    }
                    if (isset($post_data['return_dir'])) {
                        $sort_dir = $post_data['return_dir'];
                    }
                    if (isset($post_data['return_page'])) {
                        $current_page = (int) $post_data['return_page'];
                    }
                } else {
                    $error = "Erreur lors de la modification du livre.";
                    $mode = 'edit';
                }
            } else {
                // Si l'erreur a été définie (ex: ID en double), on reste en mode 'edit'
            }
            
        } else {
            // LOGIQUE D'AJOUT (Adaptée pour utiliser $new_id_input si fourni)

            $current_last_id = id_get_last(BOOKS_FILE);
            $suggested_next_id = $current_last_id + 1;

            // Utilise l'ID posté si valide, sinon l'ID suggéré
            $new_id = ($new_id_input !== 0) ? $new_id_input : $suggested_next_id;

            if (id_is_used(BOOKS_FILE, $new_id)) {
                $error = "L'ID $new_id est déjà utilisé.";
                $mode = 'add';
            } else {
                $data['id'] = $new_id;

                if (book_add($data, $cover_filename, $new_id)) {
                    id_update_last(BOOKS_FILE, $new_id);
                    $message = "Livre " . $data['tit'] . " (ID: $new_id) ajouté avec succès.";
                    $mode = 'list';
                    if (isset($post_data['return_sort'])) {
                        $sort_by = $post_data['return_sort'];
                    }
                    if (isset($post_data['return_dir'])) {
                        $sort_dir = $post_data['return_dir'];
                    }
                    if (isset($post_data['return_page'])) {
                        $current_page = (int) $post_data['return_page'];
                    }
                    if (isset($post_data['return_cov'])) {
                        $filter_cover = $post_data['return_cov'];
                    }
                    $xml_books = xml_load(BOOKS_FILE);
                    $sort_by = 'id';
                    $sort_dir = 'desc';
                    $current_page = 1;
                } else {
                    $error = "Erreur lors de l'ajout du livre.";
                    $mode = 'add';
                }
            }
        }
    }
}

// ------------------------------------------------------------------
// LOGIQUE DE SUPPRESSION (GET) (Identique)
// ------------------------------------------------------------------
if ($mode === 'delete' && $book_id > 0) {
    if (book_delete($book_id)) {
        $message = "Livre (ID: $book_id) supprimé avec succès.";
        $mode = 'list';
        $xml_books = xml_load(BOOKS_FILE);
    } else {
        $error = "Impossible de supprimer le livre (ID: $book_id). Il est actuellement emprunté.";
        $mode = 'list';
    }
}


// ------------------------------------------------------------------
// PRÉPARATION DES DONNÉES POUR L'ÉDITION/AJOUT
// ------------------------------------------------------------------
$book_data = [];
$display_data = [];
$current_cover = '';
$next_id_suggestion = ''; // Nouveau : ID suggéré

if ($mode === 'add') {
    $display_data = !empty($error) ? $post_data : [];
    $current_last_id = id_get_last(BOOKS_FILE);
    $next_id_suggestion = $current_last_id + 1;
} elseif ($mode === 'edit' && $book_id > 0) {
    // Si échec POST, utiliser les données POST
    if (!empty($error)) {
        $display_data = $post_data;
        // S'assurer que le nouvel ID posté est affiché en cas d'erreur
        $display_data['id'] = $new_id_input; 
    } else {
        // Sinon, récupérer les données de la base
        foreach ($xml_books->book as $book) {
            if ((int) $book['id'] === $book_id) {
                $pub_date_storage = (string) $book['pub_date'];
                $pub_date_display = $pub_date_storage;

                // CONVERSION POUR L'AFFICHAGE (AAAA/MM/JJ -> JJ/MM/AAAA)
                if (!empty($pub_date_storage) && preg_match('/^(\d{4})\/(\d{2})\/(\d{2})$/', $pub_date_storage, $matches)) {
                    $pub_date_display = $matches[3] . '/' . $matches[2] . '/' . $matches[1];
                }

                $book_data = [
                    'id' => (int) $book['id'],
                    'tit' => (string) $book['tit'],
                    'ser' => isset($book['ser']) ? (string) $book['ser'] : '',
                    'auts' => (string) $book['auts'],
                    'pub' => (string) $book['pub'],
                    'pub_date' => $pub_date_display, // Format JJ/MM/AAAA pour le formulaire
                    'isbn' => (string) $book['isbn'],
                    'cats' => (string) $book['cats'],
                    'desc' => isset($book->desc) ? (string) $book->desc : '',
                    'cov' => (string) $book['cov'],
                    'note' => (int) $book['note'],
                    'disp' => (int) $book['disp'],
                ];
                $display_data = $book_data;
                break;
            }
        }
        if (empty($book_data) && empty($error)) {
            $error = "Livre ID $book_id introuvable.";
            $mode = 'list';
        }
    }
}

if ($mode === 'edit' || $mode === 'add') {
    $current_cover = (isset($display_data['cov']) && $display_data['cov'] !== '') ? $display_data['cov'] : '';
    // ------------------------------------------------------------------
    // 📚 Extraction des Genres, Auteurs et Editeurs Uniques pour la Datalist
    // ------------------------------------------------------------------
    $all_genres = [];
    $all_authors = [];
    $all_pubs = [];
    $all_series = [];

    // On itère sur l'objet SimpleXML déjà chargé
    if (isset($xml_books->book)) {
        foreach ($xml_books->book as $book) {
            // 1. Extraction des genres
            // Le champ 'cats' correspond au genre
            $genres_string = (string) $book['cats'];
            if (!empty($genres_string)) {
                $genres_list = explode('+', $genres_string);

                foreach ($genres_list as $genre) {
                    $clean_genre = trim($genre);
                    if (!empty($clean_genre)) {
                        $all_genres[] = $clean_genre;
                    }
                }
            }
            // 2. Extraction des auteurs
            $authors_string = (string) $book['auts'];
            if (!empty($authors_string)) {
                $authors_list = explode('+', $authors_string);
                foreach ($authors_list as $author) {
                    $clean_author = trim($author);
                    if (!empty($clean_author)) {
                        $all_authors[] = $clean_author;
                    }
                }
            }
            // 3. Extraction des editeurs
            $pub_string = (string) $book['pub'];
            if (!empty($pub_string)) {
                $all_pubs[] = $pub_string;
            }
            // 4. Extraction des noms de séries
            $series_string = isset($book['ser']) ? (string) $book['ser'] : '';
            if (!empty($series_string) && strpos($series_string, '|') !== false) {
                list(, $series_name) = explode('|', $series_string, 2);
                $clean_series_name = trim($series_name);
                if (!empty($clean_series_name)) {
                    $all_series[] = $clean_series_name;
                }
            }
        }
    }

    // Création de la liste unique et triée
    $all_unique_genres = array_unique($all_genres);
    sort($all_unique_genres, SORT_STRING | SORT_FLAG_CASE);
    $all_unique_authors = array_unique($all_authors); // Nouveau
    sort($all_unique_authors, SORT_STRING | SORT_FLAG_CASE);
    $all_unique_pubs = array_unique($all_pubs);
    sort($all_unique_pubs, SORT_STRING | SORT_FLAG_CASE);
    $all_unique_series = array_unique($all_series); // Nouveau
    sort($all_unique_series, SORT_STRING | SORT_FLAG_CASE);
}


// ------------------------------------------------------------------
// LOGIQUE DE TRI ET DE PAGINATION
// ------------------------------------------------------------------
if ($mode === 'list') {
    $total_books = $xml_books->book->count();
    $books_per_page = defined('ITEMS_PER_PAGE') ? ITEMS_PER_PAGE : 10;

    // ... (Logique de tri et pagination) ...
    $books_array = [];
    foreach ($xml_books->book as $book) {
        $cover = (string) $book['cov'];

        $include_book = false;

        switch ($filter_cover) {
            case '1': // Sans couverture
                if ($cover === DEFAULT_COVER || $cover === '') {
                    $include_book = true;
                }
                break;

            case '2': // Couverture locale
                if (!filter_var($cover, FILTER_VALIDATE_URL)) {
                    $include_book = true;
                }
                break;

            case '0': // Tous
            default:
                $include_book = true;
                break;
        }

        if ($include_book) {
            $books_array[] = [
                'id' => (int) $book['id'],
                'tit' => (string) $book['tit'],
                'auts' => (string) $book['auts'],
                'note' => (int) $book['note'],
                'disp' => (int) $book['disp'],
            ];
        }
    }

    $total_books = count($books_array);

    usort($books_array, function ($a, $b) use ($sort_by, $sort_dir) {
        $valA = isset($a[$sort_by]) ? $a[$sort_by] : '';
        $valB = isset($b[$sort_by]) ? $b[$sort_by] : '';

        if (!is_numeric($valA)) {
            $valA = strtolower((string) $valA);
        }
        if (!is_numeric($valB)) {
            $valB = strtolower((string) $valB);
        }

        if ($valA == $valB)
            return 0;

        $comparison = ($valA < $valB) ? -1 : 1;
        return ($sort_dir === 'asc') ? $comparison : -$comparison;
    });

    if ($current_page < 1) {
        $current_page = 1;
    }

    $total_pages = ceil($total_books / $books_per_page);
    if ($total_pages === 0) {
        $total_pages = 1;
    }
    if ($current_page > $total_pages) {
        $current_page = $total_pages;
    }

    $offset = ($current_page - 1) * $books_per_page;
    $books_to_display = array_slice($books_array, $offset, $books_per_page);

    function get_sort_link_books($col, $current_sort, $current_dir, $current_page) {
        global $filter_cover;

        $new_dir = 'asc';
        $arrow = '';
        if ($current_sort === $col) {
            $new_dir = ($current_dir === 'asc') ? 'desc' : 'asc';
            $arrow = ($current_dir === 'asc') ? ' ▼' : ' ▲';
        }

        $filter_param = ($filter_cover !== '0') ? '&cov=' . urlencode($filter_cover) : '';
        $base_url = "books.php?page=$current_page&sort=$col&dir=$new_dir$filter_param";

        $display_name = ucfirst($col);
        if ($col === 'auts') {
            $display_name = 'Auteurs';
        }
        if ($col === 'tit') {
            $display_name = 'Titre';
        }

        return '<a href="' . $base_url . '">' . $display_name . $arrow . '</a>';
    }

}

// --- Inclusion des Vues ---
if ($mode === 'list') {
    include 'books_list.php';
} elseif ($mode === 'add' || $mode === 'edit') {
    include 'books_edit.php';
}
?>