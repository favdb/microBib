<?php
// loan_request.php - Formulaire de demande d'emprunt public
// 1. INCLUSIONS
require_once 'db/config.php';
require_once 'db/functions.php';
// Fonctions CRUD nécessaires
require_once 'db/crud_books.php';
require_once 'db/crud_loans.php';
require_once 'db/crud_members.php';

// Démarrer la session (nécessaire pour les messages flash et les redirections)
session_start_secure();

$error = '';
$message = '';

// 2. RÉCUPÉRATION ET VÉRIFICATION DE L'ID DU LIVRE
$book_id = isset($_REQUEST['book_id']) ? (int) $_REQUEST['book_id'] : 0;
// Récupération des paramètres de contexte pour la redirection
$return_mode = isset($_REQUEST['mode']) ? $_REQUEST['mode'] : 'auteur';
$views = isset($_GET['views']) ? strtolower($_GET['views']) : 'img';
$return_filtre = isset($_REQUEST['filtre']) ? $_REQUEST['filtre'] : null;
$return_page = isset($_REQUEST['page']) ? (int) $_REQUEST['page'] : 1;
// Redirection vers l'index avec le filtre exact
$redirect_url = 'index.php?mode=' . urlencode($return_mode) . '&views=' . $views;
if (!empty($return_filtre)) {
    $redirect_url .= '&filtre=' . urlencode($return_filtre);
}
if ($return_page > 1) {
    $redirect_url .= '&p=' . $return_page;
}


if ($book_id <= 0) {
    $error = "ID de livre manquant ou invalide.";
    $book = null;
} else {
    $book = book_get_details_by_id($book_id);

    if (empty($book)) {
        $error = "Livre non trouvé dans le catalogue.";
    } elseif (book_is_loaned($book_id)) {
        $error = "Ce livre est actuellement emprunté et n'est pas disponible.";
    }
}

// 3. TRAITEMENT DU FORMULAIRE (SOUMISSION POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $book_id > 0 && empty($error)) {

    $member_id = isset($_POST['member_id']) ? (int) $_POST['member_id'] : 0;

    if ($member_id <= 0) {
        $error = "Veuillez saisir un numéro de Membre valide.";
    } else {
        // Validation : Vérification que le membre existe réellement
        // Cette fonction (get_member_by_id) doit être présente dans loans_crud.php
        $member_exists = member_get_by_id($member_id);

        if (!$member_exists) {
            $error = "Le Numéro de Membre saisi n'existe pas.";
        } else {
            // Enregistrement du prêt
            if (loan_add($book_id, $member_id)) {
                $message = "Demande d'emprunt pour '" . htmlspecialchars($book['titre']) . "' enregistrée avec succès.";

                // Stocker le message de succès en session
                $_SESSION['flash_message'] = $message;

                // Redirection vers l'index avec le filtre exact
                header('Location: ' . $redirect_url);
                exit;
            } else {
                $error = "Une erreur est survenue lors de l'enregistrement de l'emprunt.";
            }
        }
    }
}

$page_title = 'Demande d\'Emprunt';
include'header.php';
?>
<body>

    <header class="public-header">
        <div class="header-left">
            <span class="library-icon">📚</span>
            <span class="library-name">Catalogue en Ligne</span>
        </div>
        <div class="header-right">
            <a href="index.php" class="header-btn">Retour au Catalogue</a>
            <a href="admin/index.php" class="header-admin-link" title="Connexion Administrateur">
                <span class="admin-icon">⚙️</span>
            </a>
        </div>
    </header>

    <main class="loan-form-container">
        <h1>Demande d'Emprunt</h1>

        <?php if (!empty($message)): ?>
            <p class="success-message"><?php echo $message; ?></p>
            <p><a href="index.php">Retourner au catalogue</a></p>
        <?php elseif (!empty($error)): ?>
            <p class="error-message"><?php echo $error; ?></p>
            <p><a href="index.php">Retourner au catalogue</a></p>
        <?php elseif ($book): ?>

            <div class="loan-details-box">
                <h2>Livre Sélectionné</h2>
                <h3><?php echo htmlspecialchars($book['titre']); ?></h3>
                <p>Par : <?php echo htmlspecialchars($book['auteurs']); ?></p>
            </div>

            <form action="loan_request.php" method="POST">
                <input type="hidden" name="book_id" value="<?php echo $book_id; ?>">
                <input type="hidden" name="mode" value="<?php echo htmlspecialchars($return_mode); ?>">
                <input type="hidden" name="views" value="<?php echo htmlspecialchars($views); ?>">
                <input type="hidden" name="filtre" value="<?php echo htmlspecialchars($return_filtre); ?>">
                <input type="hidden" name="page" value="<?php echo $return_page; ?>">

                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <label for="member_id">Saisissez votre Numéro de Membre :</label>
                    <input type="text" id="member_id" name="member_id" required 
                           size="5" maxlength="5" style="width: 5ch !important;"
                           value="<?php echo isset($_POST['member_id']) ? (int) $_POST['member_id'] : ''; ?>">
                </div>

                <p class="small-note">Numéro unique qui vous a été attribué.</p>

                <div class="form-actions-group">
                    <a href="<?php echo $redirect_url; ?>" class="cancel-button">Annuler</a>
                    <button type="submit" class="action-button">Confirmer l'Emprunt</button>
                </div>
            </form>

        <?php endif; ?>
    </main>

</body>
</html>