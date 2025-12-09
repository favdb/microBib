<?php
// admin/gestion.php - Gestion des sauvegardes, restaurations et imports
require_once 'admin.php';
// Les modules de fonction (save.php, restore.php, import.php) seront inclus ici au besoin.

check_admin_access();

// Détermine le mode d'affichage (backup, restore, import) ou le mode de traitement (run)
$mode = isset($_REQUEST['mode']) ? strtolower($_REQUEST['mode']) : 'backup';
$message = '';
$error = '';

// Si la requête est POST, on est en mode 'run'
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($mode === 'backup_run') {
        // INCLUSION ET EXÉCUTION DU MODULE DE SAUVEGARDE
        require_once 'save.php';

        $scope = isset($_POST['scope']) ? $_POST['scope'] : 'all';

        // perform_backup retourne un message d'erreur ou TRUE (dans ce cas, le script a déjà fait exit)
        $result = perform_backup($scope);

        if ($result !== true) {
            // Si la fonction retourne un message d'erreur
            $error = $result;
            // Repasser en mode 'backup' pour réafficher le formulaire
            $mode = 'backup';
        }
        // Si perform_backup a réussi, il a déjà appelé exit;
    } elseif ($mode === 'restore_run') {
        // INCLUSION ET EXÉCUTION DU MODULE DE RESTAURATION
        require_once 'restore.php';

        $scope = isset($_POST['scope']) ? $_POST['scope'] : 'all';
        $file_data = isset($_FILES['restore_file']) ? $_FILES['restore_file'] : null;

        $result = perform_restore($file_data, $scope);

        if ($result === true) {
            $message = "Restauration réussie ! Les données sélectionnées ont été mises à jour.";
        } else {
            $error = $result;
        }
        // Réinitialiser le mode d'affichage à 'restore' pour afficher le message
        $mode = 'restore';
    } elseif ($mode === 'import_run') {
        // INCLUSION ET EXÉCUTION DU MODULE D'IMPORT
        require_once 'import.php'; 

        $xml_file_data = isset($_FILES['xml_file']) ? $_FILES['xml_file'] : null;
        $cover_zip_data = isset($_FILES['cover_zip']) ? $_FILES['cover_zip'] : null;
        $format = isset($_POST['format']) ? $_POST['format'] : '';

        // perform_import retourne soit un message d'erreur (string), soit le nombre de livres ajoutés (int)
        $result = perform_import($xml_file_data, $cover_zip_data, $format);

        // Vérifier si le résultat est le nombre de livres (un entier)
        if (is_int($result)) {
            $message = "Importation réussie ! ";
            // Gérer le pluriel
            $message .= $result . " livre" . ($result > 1 ? "s" : "") . " ajouté" . ($result > 1 ? "s" : "") . ".";
        } else {
            $error = $result; // Le message d'erreur est le retour de perform_import
        }
        $mode = 'import'; // Afficher le formulaire après le résultat (succès ou erreur)
    }
}

$bib_name = 'Gestion: ' . ucfirst($mode);

// --- Début du rendu HTML ---
?>
<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title><?php echo htmlspecialchars($bib_name); ?></title>
        <link rel="stylesheet" href="../css/admin.css">
        <link rel="icon" type="image/x-icon" href="../favicon.png">
    </head>
    <body>
        <header>
            <h1>⚙️ Outils de Gestion</h1>
            <nav class="admin-nav">
                <a href="index.php">← Retour au Tableau de Bord</a>&nbsp;
                <a href="gestion.php?mode=backup" class="<?php echo (in_array($mode, ['backup', 'backup_run'])) ? 'active' : ''; ?>">💾 Sauvegarde</a>&nbsp;
                <a href="gestion.php?mode=restore" class="<?php echo (in_array($mode, ['restore', 'restore_run'])) ? 'active' : ''; ?>">🔄 Restauration</a>&nbsp;
                <!--
                <a href="gestion.php?mode=import" class="<?php echo (in_array($mode, ['import', 'import_run'])) ? 'active' : ''; ?>">📥 Import</a>&nbsp;
                -->
                <a href="logout.php" class="logout-link">🚪 Se déconnecter</a>
                </nav>
        </header>

        <main class="content-container">

            <?php if (!empty($message)): ?>
                <p class="success-message"><?php echo $message; ?></p>
            <?php elseif (!empty($error)): ?>
                <p class="error-message"><?php echo $error; ?></p>
            <?php endif; ?>

            <?php
            // --- AFFICHAGE DES FORMULAIRES DE CONFIGURATION ---

            if ($mode === 'backup') {
                ?>
                <h2>Configuration de la Sauvegarde</h2>
                <form action="gestion.php?mode=backup_run" method="POST" class="form-standard">
                    <p>Sélectionnez les éléments à inclure dans le fichier de sauvegarde (.zip).</p>

                    <div class="radio-group">
                        <label>
                            <input type="radio" name="scope" value="all" checked> Tout (Données et Couvertures)
                        </label>
                        <label>
                            <input type="radio" name="scope" value="data"> Seulement les Données
                        </label>
                        <label>
                            <input type="radio" name="scope" value="covers"> Seulement les Couvertures
                        </label>
                    </div>

                    <button type="submit" class="btn-primary">Lancer la Sauvegarde</button>
                </form>
                <?php
            } elseif ($mode === 'restore') {
                ?>
                <h2>Configuration de la Restauration</h2>
                <p>Attention : La restauration **remplacera** les données existantes. Faites une sauvegarde avant de restaurer !</p>
                <form action="gestion.php?mode=restore_run" method="POST" enctype="multipart/form-data" class="form-standard">

                    <label for="restore_file">Fichier de Sauvegarde (.zip) :</label>
                    <input type="file" name="restore_file" id="restore_file" accept=".zip" required>

                    <div class="radio-group">
                        <label>
                            <input type="radio" name="scope" value="all" checked> Remplacer tout (Données et Couvertures)
                        </label>
                        <label>
                            <input type="radio" name="scope" value="data"> Remplacer seulement les Données
                        </label>
                        <label>
                            <input type="radio" name="scope" value="covers"> Remplacer seulement les Couvertures
                        </label>
                    </div>

                    <button type="submit" class="btn-primary">Lancer la Restauration</button>
                </form>
    <?php
} elseif ($mode === 'import') {
    ?>
                <h2>Configuration de l'Import de Données</h2>
                <p>Cette opération <b>ajoute</b> de nouvelles données sans écraser les anciennes. Les ID seront ajustés.</p>
                <form action="gestion.php?mode=import_run" method="POST" enctype="multipart/form-data" class="form-standard">

                    <label for="xml_file">Fichier de Données à Importer (XML) :</label>
                    <input type="file" name="xml_file" id="xml_file" accept=".xml" required>

                    <label for="cover_zip">Fichier ZIP des Couvertures (Optionnel) :</label>
                    <input type="file" name="cover_zip" id="cover_zip" accept=".zip">

                    <label for="format">Format du Fichier XML :</label>
                    <select name="format" id="format" required>
                        <option value="gcstar">GCstar XML</option>
                        <option value="calibre">Calibre XML</option>
                        <option value="custom">Format Personnalisé (Bibliothèque)</option>
                    </select>

                    <button type="submit" class="btn-primary">Lancer l'Import</button>
                </form>
    <?php
} else {
    // Mode Inconnu, rediriger vers la sauvegarde
    header('Location: gestion.php?mode=backup');
    exit;
}
?>

            <p style="margin-top: 30px;"><a href="index.php">← Retour au Tableau de Bord</a></p>

        </main>
    </body>
</html>