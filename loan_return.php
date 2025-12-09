<?php
// loan_return.php - Script de confirmation et de traitement du retour public

// 1. INCLUSIONS
require_once 'db/config.php';
require_once 'db/functions.php';
// Fonctions CRUD nécessaires
require_once 'db/crud_books.php';
require_once 'db/crud_loans.php';
require_once 'db/crud_members.php';

// Démarrer la session (pour les messages flash)
session_start_secure();

$error = '';
// $message est géré via $_SESSION['flash_message'] dans le processus POST

// 2. RÉCUPÉRATION ET VÉRIFICATION DES IDS
$book_id = isset($_REQUEST['book_id']) ? (int)$_REQUEST['book_id'] : 0;
$loan_id = isset($_REQUEST['loan_id']) ? (int)$_REQUEST['loan_id'] : 0; 

// NOUVEAU : Récupérer les paramètres de l'URL de provenance (GET) ou du formulaire (POST)
// Ces valeurs sont utilisées pour la redirection
$return_mode = isset($_REQUEST['mode']) ? $_REQUEST['mode'] : 'auteur';
$views = isset($_GET['views']) ? strtolower($_GET['views']) : 'img';
$return_filtre = isset($_REQUEST['filtre']) ? $_REQUEST['filtre'] : null;
$return_page = isset($_REQUEST['page']) ? (int)$_REQUEST['page'] : 1;

// Redirection vers l'index avec le filtre exact
$redirect_url = 'index.php?mode=' . urlencode($return_mode) . '&views=' . $views;
if (!empty($return_filtre)) {
    $redirect_url .= '&filtre=' . urlencode($return_filtre);
}
if ($return_page > 1) { 
     $redirect_url .= '&p=' . $return_page;
}

$book = null;
$loan = null;

if ($book_id <= 0) {
    $error = "ID de livre manquant ou invalide.";
} else {
    $book = book_get_details_by_id($book_id);
    $loan = loan_get_active_by_book_id($book_id);

    if (empty($book)) {
        $error = "Livre non trouvé dans le catalogue.";
    } elseif (empty($loan)) {
        $error = "Ce livre n'est pas actuellement enregistré comme étant emprunté.";
    } elseif ($loan_id === 0) {
        // Mettre à jour loan_id si non fourni mais qu'un prêt actif existe
        $loan_id = (int)$loan['id'];
    }
}


// 3. TRAITEMENT DE LA SOUMISSION DU FORMULAIRE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_id'], $_POST['loan_id'], $_POST['member_id'])) {
    
    // 3.1. Vérification de l'emprunteur
    $member_id_form = (int)$_POST['member_id'];
    $loan_id_post = (int)$_POST['loan_id'];
    
    // Récupération du prêt actif pour double vérification
    $loan_to_return = loan_get_details_by_id($loan_id_post);

    if (empty($loan_to_return) || (int)$loan_to_return['member'] !== $member_id_form) {
        $error = "Numéro de membre incorrect ou prêt introuvable. Veuillez réessayer.";
    } elseif (!book_is_loaned((int)$_POST['book_id'])) {
        $error = "Erreur: Ce livre est déjà marqué comme disponible. Le retour n'est pas nécessaire.";
    } else {
        // 3.2. Enregistrement du retour
        if (loan_return($loan_id_post)) {//ajouter le traitement de la notation
            
            // Stocker le message de succès en session
            $_SESSION['flash_message'] = "Le retour du livre **" . htmlspecialchars($book['titre']) . "** a été enregistré avec succès.";
            
            // Redirection vers l'index avec le filtre exact
            header('Location: ' . $redirect_url);
            exit;
            
        } else {
            $error = "Une erreur s'est produite lors de l'enregistrement du retour. Veuillez vérifier les logs.";
        }
    }
}

// 4. RENDU HTML
$page_title = 'Retour de Livre';
include'header.php';
?>
<body class="loan-form-page">

    <div class="loan-form-container">
        
        <?php if ($book === null || $loan === null || !empty($error)): ?>
            <p class="error-message"><?php echo $error; ?></p>
            <p><a href="index.php">Retourner au catalogue</a></p>
        <?php elseif ($book && $loan): 
            $member_id_pret = (int)$loan['member'];
            $member_name = member_get_name_by_id($member_id_pret);
        ?>

            <div class="loan-details-box">
                <h2>Retour du Livre</h2>
                <h3><?php echo htmlspecialchars($book['titre']); ?></h3>
            </div>
            
            <p style="color: red; font-weight: bold;">ATTENTION : Veuillez confirmer votre identité pour finaliser le retour.</p>

            <form action="loan_return.php" method="POST">
                <input type="hidden" name="book_id" value="<?php echo $book_id; ?>">
                <input type="hidden" name="loan_id" value="<?php echo $loan_id; ?>">
                
                <input type="hidden" name="mode" value="<?php echo htmlspecialchars($return_mode); ?>">
                <input type="hidden" name="views" value="<?php echo htmlspecialchars($views); ?>">
                <input type="hidden" name="filtre" value="<?php echo htmlspecialchars($return_filtre); ?>">
                <input type="hidden" name="page" value="<?php echo $return_page; ?>">

                <div class="form-row-inline">
                    <label for="member_id">Saisissez votre Numéro de Membre :</label>
                    <input type="text" id="member_id" name="member_id" required 
                           size="5" maxlength="5" style="width: 5ch !important;"
                           value="<?php echo isset($_POST['member_id']) ? (int)$_POST['member_id'] : ''; ?>">
                </div>
                
                <p class="small-note">Ceci confirme que vous êtes bien l'emprunteur et que le livre est de retour.</p>
                <div class="form-actions-group">
                    <a href="<?php echo $return_page; ?>" class="cancel-button">Annuler</a>
                    <button type="submit" class="action-button">Confirmer le retour</button>
                </div>
            </form>

        <?php endif; ?>

    </div>

</body>
</html>