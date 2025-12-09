<?php
// db/loans_crud.php - Fonctions CRUD pour les Prêts
require_once 'config.php';
require_once 'functions.php';
require_once 'crud_books.php';
require_once 'crud_members.php';

/**
 * Récupère les détails d'un prêt spécifique par son ID.
 * @param int $loan_id L'ID du prêt à rechercher.
 * @return array|null Les détails du prêt, ou null si non trouvé.
 */
function loan_get_details_by_id($loan_id) {
    $xml_loans = xml_load(LOANS_FILE); 

    if (!$xml_loans) {
        return null;
    }

    foreach ($xml_loans->loan as $loan_node) {
        if ((int)$loan_node['id'] === $loan_id) {
            return array(
                'id'         => (int)$loan_node['id'],
                'book_id'    => (int)$loan_node['book_id'],
                'member'     => (int)$loan_node['member'],
                'date'       => (string)$loan_node['date'],
                'return_date'=> (string)$loan_node['return_date'] ? (string)$loan_node['return_date'] : null,
                'status'     => (string)$loan_node['status']
            );
        }
    }

    return null;
}

/**
 * Enregistre un nouveau prêt
 * @param int $book_id
 * @param int $member_id
 * @return bool
 */
function loan_add($book_id, $member_id) {
    // 1. Vérifications
    $book = book_get_by_id($book_id);
    $member = member_get_by_id($member_id);

    if (!$book || !$member) {
        return false;
    }
    if ((int)$book['disp'] !== 1) { 
        return false;
    }

    $xml_loans = xml_load(LOANS_FILE);
    // CORRECTION : Appel avec TRUE pour incrémenter et sauvegarder l'ID de prêt (Réservation)
    $next_id = id_get_next(LOANS_FILE); 
    $current_date = date('Y/m/d');

    // 2. Ajout dans loans.xml
    $new_loan = $xml_loans->addChild('loan');
    $new_loan->addAttribute('id', $next_id);
    $new_loan->addAttribute('book', $book_id);
    $new_loan->addAttribute('member', $member_id);
    $new_loan->addAttribute('date', $current_date);
    
    $success_loan = xml_save($xml_loans, LOANS_FILE);

    // 3. Mise à jour dans books.xml
    // CORRECTION : Appel de la fonction bien placée dans books_crud.php
    $success_book = book_set_disp($book_id, 0); // 0 = Emprunté

    return $success_loan && $success_book;
}

/**
 * Enregistre le retour d'un livre et met à jour la note du livre.
 * @param int $loan_id L'ID de l'entrée dans loans.xml
 * @param int|null $note_given Nouvelle note donnée par l'emprunteur (optionnel, 0-5)
 * @return bool
 */
function loan_return($loan_id, $note_given = null) {
    $xml_loans = xml_load(LOANS_FILE);
    $book_id = null;
    $loan_node = null;

    // 1. Trouver le prêt et l'ID du livre
    $dom = dom_import_simplexml($xml_loans)->ownerDocument;

    foreach ($xml_loans->loan as $loan) {
        if ((int)$loan['id'] === $loan_id) {
            $book_id = (int)$loan['book'];
            $loan_node = $loan;
            break;
        }
    }

    if (!$book_id) {
        return false; // Prêt non trouvé
    }
    
    // 2. Mise à jour de books.xml (Disp. à 1)
    // CORRECTION : Appel de la fonction bien placée dans books_crud.php
    $success_book = book_set_disp($book_id, 1); // 1 = Disponible

    // 3. Suppression de l'entrée dans loans.xml
    if ($loan_node) {
        $dom_node = dom_import_simplexml($loan_node);
        $dom_node->parentNode->removeChild($dom_node);
        // CORRECTION : Utilisation de ROOT_PATH pour un chemin absolu sécurisé
        $success_loan = $dom->save(ROOT_PATH . LOANS_FILE) !== false; 
    } else {
        $success_loan = false;
    }
    
    // 4. Mise à jour de la notation du livre
    $success_note = true;
    if ($note_given !== null && $note_given >= 0 && $note_given <= 5) {
        $xml_books = xml_load(BOOKS_FILE);
        foreach ($xml_books->book as $book) {
            if ((int)$book['id'] === $book_id) {
                $book['note'] = (int)$note_given;
                $success_note = xml_save($xml_books, BOOKS_FILE);
                break;
            }
        }
    }

    return $success_loan && $success_book && $success_note;
}

/**
 * Vérifie si le membre a AU MOINS un prêt actif.
 * Nécessaire pour la suppression du membre dans members_crud.php.
 * NOTE: Cette vérification repose sur le fait que les prêts retournés sont supprimés.
 * @param int $member_id
 * @param SimpleXMLElement $xml_loans L'objet XML des prêts (facultatif).
 * @return bool Vrai si un prêt actif est trouvé (l'entrée existe).
 */
function member_has_active_loan($member_id, $xml_loans = null) {
    if (is_null($xml_loans)) {
        $xml_loans = xml_load(LOANS_FILE);
    }
    
    foreach ($xml_loans->loan as $loan) {
        if ((int)$loan['member'] === $member_id) {
            return true;
        }
    }
    return false;
}

/**
 * Vérifie si un membre a atteint sa limite de prêts actifs.
 * Utilisé par loan_request.php.
 * @param int $member_id
 * @param SimpleXMLElement $xml_loans L'objet XML des prêts (facultatif).
 * @param int $max_loans La limite maximale de prêts actifs (5 par défaut).
 * @return bool Vrai si la limite est atteinte ou dépassée.
 */
function member_has_too_many_loans($member_id, $xml_loans = null, $max_loans = 5) {
    if (is_null($xml_loans)) {
        $xml_loans = xml_load(LOANS_FILE);
    }
    
    $active_loans_count = 0;
    
    foreach ($xml_loans->loan as $loan) {
        // Le prêt est actif si l'entrée existe.
        if ((int)$loan['member'] === $member_id) {
            $active_loans_count++;
        }
    }
    
    return $active_loans_count >= $max_loans;
}

/**
 * Récupère le prêt actif pour un livre donné.
 * Dans cette implémentation, on suppose qu'un livre n'a qu'un seul prêt actif (non retourné) possible.
 * @param int $book_id
 * @return SimpleXMLElement|null L'objet prêt (loan) s'il est actif, sinon null.
 */
function loan_get_active_by_book_id($book_id) {
    $xml = xml_load(LOANS_FILE);
    
    foreach ($xml->loan as $loan) {
        // La condition d'un prêt actif est simplement qu'il existe dans le fichier loans.xml
        if ((int)$loan['book'] === $book_id) {
            return $loan; // Prêt actif trouvé
        }
    }
    return null; // Pas de prêt actif pour ce livre
}

?>