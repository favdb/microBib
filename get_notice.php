<?php
// get_notice.php
// Ce script reçoit un ID via AJAX et retourne le HTML formaté de la notice de livre.

// 1. INCLUSIONS ET VÉRIFICATION DE LA REQUÊTE
require_once 'db/config.php';
require_once 'db/functions.php';
require_once 'db/crud_books.php'; 
require_once 'db/crud_loans.php'; 
require_once 'db/crud_members.php'; 

// Le script ne doit pas générer d'output avant le HTML final
if (isset($_GET['id'])) {
    $book_id = (int)$_GET['id'];
} else {
    // Cas d'erreur : ID manquant
    header('Content-Type: text/html; charset=utf-8');
    echo '<h3 style="color: red;">Erreur: ID de livre manquant.</h3>';
    exit;
}

// 2. RÉCUPÉRATION DES DONNÉES
$book = book_get_details_by_id($book_id);

if (empty($book)) {
    // Cas d'erreur : Livre non trouvé
    header('Content-Type: text/html; charset=utf-8');
    echo '<h3 style="color: red;">Erreur: Livre avec l\'ID ' . htmlspecialchars($book_id) . ' non trouvé.</h3>';
    exit;
}

// Détermination du statut
$is_loaned = book_is_loaned($book_id);
$loan_details = $is_loaned ? loan_get_active_by_book_id($book_id) : null;
$note = (int)$book['note'];
$note_html = str_repeat('★', $note) . str_repeat('☆', 5 - $note);

// Chemin de la couverture
// Récupère la valeur stockée (nom de fichier local OU URL)
$cover_value = isset($book['couverture']) ? trim((string)$book['couverture']) : '';
// 1. Vérifie si la valeur stockée est une URL distante
if (!empty($cover_value) && filter_var($cover_value, FILTER_VALIDATE_URL)) {
    // CAS 1: C'est une URL, on l'utilise directement
    $cover_path = htmlspecialchars($cover_value);
} 
// 2. Sinon, on vérifie si c'est un fichier local
elseif (!empty($cover_value) && file_exists('covers/' . $cover_value)) {
    // CAS 2: C'est un nom de fichier local
    $cover_path = 'covers/' . htmlspecialchars($cover_value); 
}
// 3. Cas par défaut
else {
    // CAS 3: Couverture manquante/invalide
    $cover_path = 'covers/default.jpg'; // Image par défaut
}

// Récupérer les paramètres de l'URL pour le lien de retour
// Ces paramètres sont passés par script.js via la requête AJAX
$current_mode = isset($_GET['mode']) ? $_GET['mode'] : 'auteur';
$current_filtre = isset($_GET['filtre']) ? $_GET['filtre'] : null;
$current_page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
    
// Construction de l'URL de retour (pour loan_return.php)
$loan_id_param = $loan_details ? (int)$loan_details['id'] : 0;
$return_url = 'loan_return.php?book_id=' . $book_id . 
              '&loan_id=' . $loan_id_param . 
              // Passage des paramètres de contexte pour la redirection après retour
              '&mode=' . urlencode($current_mode) . 
              '&filtre=' . urlencode($current_filtre) . 
              '&page=' . $current_page;
?>

<div class="notice-container">
    
    <div class="notice-header-bar">
        <span class="book-id-tag">
            <?php echo str_pad((int)$book['id'], 4, '0', STR_PAD_LEFT); ?>
        </span>
        
        <?php if (!$is_loaned): ?>
        <?php
            $return_url = 'loan_request.php?book_id=' . $book_id . 
                          // Passage des paramètres de contexte pour la redirection après retour
                          '&mode=' . urlencode($current_mode) . 
                          '&filtre=' . urlencode($current_filtre) . 
                          '&page=' . $current_page;
        ?>
            <a href="<?php echo $return_url; ?>"
               class="action-button statut-disponible"
               title="Signaler que vous souhaitez emprunter ce livre">
                EMPRUNTER CE LIVRE
            </a>
        <?php else: 
            // Livre emprunté : Statut et bouton de signalement groupés à droite
            $loan_id = $loan_details ? (int)$loan_details['id'] : 0;
            $member_name = $loan_details ? member_get_name_by_id((int)$loan_details['member']) : 'Membre Inconnu';
            $loan_date = $loan_details ? (string)$loan_details['date'] : 'Date inconnue';
            // Ces paramètres sont passés par script.js via la requête AJAX
            $current_mode = isset($_GET['mode']) ? $_GET['mode'] : 'auteur';
            $current_filtre = isset($_GET['filtre']) ? $_GET['filtre'] : null;
            $current_page = isset($_GET['p']) ? (int)$_GET['p'] : 1;

            // Construction de l'URL de retour (pour loan_return.php)
            $loan_id_param = $loan_details ? (int)$loan_details['id'] : 0;
            $return_url = 'loan_return.php?book_id=' . $book_id . 
                          '&loan_id=' . $loan_id_param . 
                          // Passage des paramètres de contexte pour la redirection après retour
                          '&mode=' . urlencode($current_mode) . 
                          '&filtre=' . urlencode($current_filtre) . 
                          '&page=' . $current_page;
            ?>
            
            <div class="indisponible-status-group">
                <span class="action-button statut-indisponible">
                    Indisponible
                </span>
                
                <a href="<?php echo $return_url; ?>" class="action-button statut-return-signal" title="Bouton Administrateur: Signaler manuellement le retour">
                    RETOUR
                </a>
            </div>

        <?php endif; ?>
    </div>

    <h2><?php echo htmlspecialchars($book['titre']); ?></h2>
    <p>par <strong><?php echo book_authors($book); ?></strong></p>

    <div class="notice-main-content">
        <div class="notice-cover">
            <img src="<?php echo $cover_path; ?>" 
                 alt="<?php echo htmlspecialchars($book['titre']); ?> (<?php echo $cover_path; ?>)">
        </div>
        
        <div class="notice-details">
            <?php if ($is_loaned && $loan_details): ?>
                <p class="loan-details-info">Prêté à : <strong><?php echo htmlspecialchars($member_name); ?></strong></p>
                <p class="loan-details-info">Prêté le : <strong><?php echo htmlspecialchars($loan_date); ?></strong></p>
                <hr style="border: 0; border-top: 1px dotted #ccc;">
            <?php endif; ?>
            <?php
                // Affichage des informations de la série
                $series_raw = isset($book['serie']) ? (string)$book['serie'] : '';
                if (!empty($series_raw) && strpos($series_raw, '|') !== false) {
                    // Sépare "num|nom" en deux parties, en nettoyant les espaces
                    list($series_num, $series_name) = array_map('trim', explode('|', $series_raw, 2));

                    if (!empty($series_name)) {
                        $series_display = '<strong>Série :</strong> ' . htmlspecialchars($series_name);
                        if (!empty($series_num)) {
                            $series_display .= ' (Tome ' . htmlspecialchars($series_num) . ')';
                        }
                        echo '<p>' . $series_display . '<br></p>';
                    }
                }
            ?>

            <p><strong>Genres :</strong> <?php echo htmlspecialchars($book['genres']); ?><br>
            <strong>Éditeur :</strong> <?php echo htmlspecialchars($book['editeur']); ?><br>
            <strong>Date de Publication :</strong> <?php echo htmlspecialchars($book['date_pub']); ?><br>
            <p class="notice-note"><strong>Note :</strong> 
                <span class="stars"><?php echo $note_html; ?></span> (<?php echo $note; ?>/5)
            </p>
            <p><strong>Résumé / Description :</strong><br>
            <?php echo nl2br(htmlspecialchars($book['description'])); ?></p>
        </div>
    </div>
    
    
    <?php if ($is_loaned): ?>
        <p class="alert-loaned" style="margin-top: 20px;">
            Livre emprunté, vous pouvez utiliser le bouton "RETOUR" ci-dessus pour signaler le retour.
        </p>
    <?php endif; ?>
</div>