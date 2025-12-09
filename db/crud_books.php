<?php

// admin/books_crud.php - Fonctions CRUD pour les Livres
require_once 'config.php';
require_once 'functions.php';

/**
 * Récupère les détails complets d'un livre par son ID.
 * @param int $book_id
 * @return array Le tableau des détails du livre, ou un tableau vide.
 */
function book_get_details_by_id($book_id) {
    $xml_books = xml_load(BOOKS_FILE);
    if (!$xml_books)
        return array();

    foreach ($xml_books->book as $book) {
        if ((int) $book['id'] === (int) $book_id) {
            return array(
                'id' => (int) $book['id'],
                'titre' => (string) $book['tit'],
                'auteurs' => (string) $book['auts'],
                'couverture' => (string) $book['cov'],
                'editeur' => (string) $book['pub'],
                'date_pub' => (string) $book['pub_date'],
                'isbn' => (string) $book['isbn'],
                'genres' => (string) $book['cats'],
                'description' => isset($book->desc) ? (string) $book->desc : 'Description non disponible.',
                'note' => (int) $book['note'],
                'disponible' => (int) $book['disp'],
                'serie' => isset($book['ser']) ? (string) $book['ser'] : '',
            );
        }
    }
    return array();
}

/**
 * Filtre les livres par un auteur spécifique.
 * @param string $author L'auteur à filtrer.
 * @return array Liste des livres correspondants (tableaux associatifs).
 */
function books_get_by_author($author) {
    $xml_books = xml_load(BOOKS_FILE);
    $filtered_books = [];
    $normalized_author = trim($author);

    foreach ($xml_books->book as $book) {
        // Utilisation du séparateur '+'
        $author_list = preg_split('/\+/', (string) $book['auts'], -1, PREG_SPLIT_NO_EMPTY);

        $match = false;
        foreach ($author_list as $book_author) {
            // La comparaison doit être faite sur la chaîne entière (Nom, Prénom)
            if (trim($book_author) === $normalized_author) {
                $match = true;
                break;
            }
        }

        if ($match) {
            $filtered_books[] = book_get_details_by_id((int) $book['id']);
        }
    }
    return $filtered_books;
}

/**
 * Récupère le titre d'un livre par son ID.
 * @param int $book_id
 * @return string Titre du livre, ou "Inconnu"
 */
function book_get_by_id($book_id) {
    $xml_books = xml_load(BOOKS_FILE);
    foreach ($xml_books->book as $book) {
        if ((int) $book['id'] === $book_id) {
            return $book;
        }
    }
    return 'Livre Inconnu (ID: ' . $book_id . ')';
}

/**
 * Récupère le titre d'un livre par son ID.
 * @param int $book_id
 * @return string Titre du livre, ou "Inconnu"
 */
function book_get_title_by_id($book_id) {
    $xml_books = xml_load(BOOKS_FILE);
    foreach ($xml_books->book as $book) {
        if ((int) $book['id'] === $book_id) {
            return htmlspecialchars((string) $book['tit']);
        }
    }
    return 'Livre Inconnu (ID: ' . $book_id . ')';
}

/**
 * Vérifie si un livre est actuellement emprunté (disp=0).
 * @param int $book_id
 * @return bool Vrai si le livre est indisponible, faux sinon.
 */
function book_is_loaned($book_id) {
    $xml_books = xml_load(BOOKS_FILE);
    if (!$xml_books) {
        return true;
    }

    foreach ($xml_books->book as $book) {
        if ((int) $book['id'] === (int) $book_id) {
            return (string) $book['disp'] === '0';
        }
    }
    return false;
}

/**
 * Nombre de livres disponibles (disp=1).
 * @param int $book_id
 * @return bool Vrai si le livre est indisponible, faux sinon.
 */
function books_available() {
    $xml_books = xml_load(BOOKS_FILE);
    if (!$xml_books) {
        return 0;
    }
    $available = 0;
    foreach ($xml_books->book as $book) {
        if ((string) $book['disp'] === '1') {
            $available++;
            ;
        }
    }
    return $available;
}

/**
 * Filtre les livres par un genre spécifique.
 * @param string $genre Le genre à filtrer.
 * @return array Liste des livres correspondants.
 */
function books_get_by_genre($genre) {
    $xml_books = xml_load(BOOKS_FILE);
    $filtered_books = array();
    $normalized_genre = trim($genre);

    foreach ($xml_books->book as $book) {
        $genre_list = preg_split('/\+/', (string) $book['cats'], -1, PREG_SPLIT_NO_EMPTY);

        $match = false;
        foreach ($genre_list as $book_genre) {
            if (trim($book_genre) === $normalized_genre) {
                $match = true;
                break;
            }
        }

        if ($match) {
            $filtered_books[] = book_get_details_by_id((int) $book['id']);
        }
    }
    return $filtered_books;
}

/**
 * Filtre les livres par une série spécifique.
 * @param string $serie L'auteur à filtrer.
 * @return array Liste des livres correspondants (tableaux associatifs).
 */
function books_get_by_serie($serie) {
    $xml_books = xml_load(BOOKS_FILE);
    $filtered_books = [];
    $normalized = trim($serie);

    foreach ($xml_books->book as $book) {
        // Utilisation du séparateur '+'
        $serie_list = preg_split('/\|/', (string) $book['ser'], -1, PREG_SPLIT_NO_EMPTY);

        $match = false;
        foreach ($serie_list as $val) {
            // La comparaison doit être faite sur la chaîne entière
            if (trim($val) === $normalized) {
                $match = true;
                break;
            }
        }

        if ($match) {
            $filtered_books[] = book_get_details_by_id((int) $book['id']);
        }
    }
    return $filtered_books;
}

/**
 * Ajoute un nouveau livre au XML
 * @param array $data Les données du livre
 * @param string|null $cover_filename Le nom du fichier de couverture
 * @param int $book_id L'ID du livre à utiliser (fourni par le contrôleur)
 * @return bool
 */
function book_add($data, $cover_filename, $book_id) {
    $xml = xml_load(BOOKS_FILE);
    if (!isset($data['id'])) {
        return false;
    }
    $next_id = (int) $data['id'];

    // Définir la couverture
    $cov = $cover_filename ?: DEFAULT_COVER;

    // Ajout du nouvel élément <book>
    $new_book = $xml->addChild('book');
    $new_book->addAttribute('id', $book_id); // Utilisation de l'ID fourni
    $new_book->addAttribute('tit', htmlspecialchars($data['tit']));
    $new_book->addAttribute('isbn', htmlspecialchars($data['isbn']));
    $new_book->addAttribute('pub', htmlspecialchars($data['pub']));
    $new_book->addAttribute('pub_date', htmlspecialchars($data['pub_date']));
    $new_book->addAttribute('auts', htmlspecialchars($data['auts']));
    $new_book->addAttribute('cats', htmlspecialchars($data['cats']));
    $new_book->addAttribute('cov', htmlspecialchars($cov));
    $new_book->addAttribute('note', (int) $data['note']);
    $new_book->addAttribute('disp', 1); // Toujours disponible à l'ajout
    if (!empty($data['ser'])) {
        $new_book->addAttribute('ser', htmlspecialchars($data['ser'])); // Ajout de l'attribut ser
    }
    // Ajout de la description dans un nœud enfant
    $new_book->addChild('desc', htmlspecialchars($data['desc']));

    // Sauvegarde du fichier XML
    return xml_save($xml, BOOKS_FILE);
}

/**
 * Met à jour un livre existant
 * @param int $old_book_id L'ancien ID du livre.
 * @param int $new_book_id Le nouvel ID du livre (peut être identique à l'ancien).
 * @param array $data
 * @param string|null $new_cover_filename Le nom du nouveau fichier de couverture (si upload)
 * @return bool
 */
function book_update($old_book_id, $new_book_id, $data, $new_cover_filename = null) { 
    $xml = xml_load(BOOKS_FILE);

    foreach ($xml->book as $book) {
        if ((int) $book['id'] === $old_book_id) { 
            
            // Mise à jour de l'ID si différent
            if ($old_book_id !== $new_book_id) {
                $book['id'] = $new_book_id;
            }
            
            // Mise à jour des autres attributs
            $book['tit'] = htmlspecialchars($data['tit']);
            $book['isbn'] = htmlspecialchars($data['isbn']);
            // ... (Le reste de la mise à jour des attributs est inchangé)
            $book['pub'] = htmlspecialchars($data['pub']);
            $book['pub_date'] = htmlspecialchars($data['pub_date']);
            $book['auts'] = htmlspecialchars($data['auts']);
            $book['cats'] = htmlspecialchars($data['cats']);
            $book['note'] = (int) $data['note'];
            if (!empty($data['ser'])) {
                $book['ser'] = htmlspecialchars($data['ser']);
            } else {
                // Si la série est vidée, on retire l'attribut (ou on le vide)
                unset($book['ser']);
            }

            // Mise à jour de la disponibilité (si elle vient du formulaire)
            if (isset($data['disp'])) {
                $book['disp'] = (int) $data['disp'];
            }

            // Mise à jour de la couverture si un nouveau fichier a été uploadé
            if ($new_cover_filename !== null) {
                // Supprimer l'ancienne couverture si elle n'est pas 'default.jpg'
                $old_cover = (string) $book['cov'];
                if ($old_cover !== DEFAULT_COVER && file_exists(COVERS_STORAGE_PATH . $old_cover)) {
                    unlink(COVERS_STORAGE_PATH . $old_cover);
                }
                $book['cov'] = htmlspecialchars($new_cover_filename);
            }

            // Mise à jour du nœud enfant 'desc'
            $book->desc = htmlspecialchars($data['desc']);

            return xml_save($xml, BOOKS_FILE);
        }
    }
    return false; // Livre non trouvé
}

/**
 * Supprime un livre (impossible si emprunt en cours)
 * @param int $book_id
 * @return bool
 */
function book_delete($book_id) {
    $xml_loans = xml_load(LOANS_FILE);

    // Vérifier si le livre est prêté
    foreach ($xml_loans->loan as $loan) {
        if ((int) $loan['book'] === $book_id) {
            // Impossible de supprimer, livre emprunté
            return false;
        }
    }

    $xml = xml_load(BOOKS_FILE);
    $dom = dom_import_simplexml($xml)->ownerDocument;

    // Recherche du livre et suppression
    foreach ($xml->book as $book) {
        if ((int) $book['id'] === $book_id) {
            $cover_to_delete = (string) $book['cov'];
            if ($cover_to_delete !== DEFAULT_COVER && file_exists(COVERS_STORAGE_PATH . $cover_to_delete)) {
                //suppression du fichier de couverture
                unlink(COVERS_STORAGE_PATH . $cover_to_delete);
            }

            $dom_node = dom_import_simplexml($book);
            $dom_node->parentNode->removeChild($dom_node);

            // Recharger le DOM pour la sauvegarde (nécessaire après une suppression via DOM)
            return $dom->save(ROOT_PATH . BOOKS_FILE) !== false;
        }
    }
    // Livre non trouvé
    return false;
}

/**
 * Met à jour l'attribut 'disp' (disponibilité) d'un livre.
 * @param int $book_id L'ID du livre.
 * @param int $disp_status 1 (Disponible) ou 0 (Emprunté).
 * @return bool Vrai si la mise à jour réussit.
 */
function book_set_disp($book_id, $disp_status) {
    $xml = xml_load(BOOKS_FILE);
    $disp_status = (int) $disp_status; // Assure que c'est un entier

    foreach ($xml->book as $book) {
        if ((int) $book['id'] === $book_id) {
            $book['disp'] = $disp_status;
            return xml_save($xml, BOOKS_FILE);
        }
    }
    return false; // Livre non trouvé
}

function book_authors($book) {
    $auteurs_raw = $book['auteurs'];
    $auteurs_formatted = '';

    // On divise les auteurs s'il y en a plusieurs (ex: Auteur1 + Auteur2)
    $auteurs_list = explode('+', $auteurs_raw);

    foreach ($auteurs_list as $auteur) {
        $auteur_display = book_author_format($auteur);

        // Ajoute l'auteur formaté à la liste, séparé par une virgule pour l'affichage
        if ($auteurs_formatted) {
            $auteurs_formatted .= ', ';
        }
        $auteurs_formatted .= htmlspecialchars($auteur_display);
    }

    // Affiche le résultat entre parenthèses
    return $auteurs_formatted;
}

function book_author_format($auteur_value) {
    $auteur = trim($auteur_value); // Enlève les espaces inutiles
    // Vérifie si le format est 'Nom, Prénom'
    if (strpos($auteur, ',') !== false) {
        // Sépare par la virgule
        list($nom, $prenom) = array_map('trim', explode(',', $auteur, 2));
        $auteur_display = "$prenom $nom"; // Inverse en 'Prénom Nom'
    } else {
        // Garde l'auteur tel quel si le format n'est pas 'Nom, Prénom'
        $auteur_display = $auteur;
    }
    return $auteur_display;
}

/**
 * Retire les articles du début d'un titre pour le tri alphabétique.
 * @param string $titre Le titre à normaliser
 * @return string Le titre sans article initial
 */
function book_normalize_title_for_sort($titre) {
    $titre = trim($titre);
    $titre_lower = mb_strtolower($titre, 'UTF-8');
    
    // Liste des articles français à ignorer
    $articles = [
        "à " => 2,
        "l'" => 2,
        "d'" => 2,
        "le " => 3,
        "la " => 3,
        "un " => 3,
        "du " => 3,
        "aux " => 4,
        "les " => 4,
        "une " => 4,
        "des " => 4
    ];
    
    // Vérifie si le titre commence par un article
    foreach ($articles as $article => $length) {
        if (mb_substr($titre_lower, 0, $length, 'UTF-8') === $article) {
            // Retire l'article et retourne le reste
            return mb_substr($titre, $length, null, 'UTF-8');
        }
    }
    
    return $titre;
}

/**
 * Trie un tableau de livres par ordre alphabétique de titre (sans articles).
 * @param array $livres Tableau de livres à trier
 * @return array Tableau trié
 */
function books_sort_by_title($livres) {
    usort($livres, function($a, $b) {
        $titre_a = book_normalize_title_for_sort($a['titre']);
        $titre_b = book_normalize_title_for_sort($b['titre']);
        
        return strcasecmp($titre_a, $titre_b);
    });
    
    return $livres;
}

?>