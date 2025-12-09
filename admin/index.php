<?php
// Vérifie et sécurise l'accès admin
require_once 'admin.php'; 
require_once '../db/config.php';
require_once '../db/crud_books.php';
require_once '../db/crud_loans.php';
check_admin_access();

// Chargement des données
$bib_name = get_bib_name();
$xml_books = xml_load(BOOKS_FILE);
$xml_loans = xml_load(LOANS_FILE);

// Statistiques rapides
$total_books = $xml_books->book->count();
$available_books = books_available();
$current_loans = $xml_loans->loan->count();

// Liste des prêts en cours (première approche)
$loans_list = [];
foreach ($xml_loans->loan as $loan) {
    $loan_date = (string)$loan['date'];
    $book_id = (int)$loan['book'];
    $member_id = (int)$loan['member'];
    
    $book_title = book_get_title_by_id($book_id);
    $member_name = member_get_name_by_id($member_id);
    $loans_list[] = [
        'id' => (string)$loan['id'],
        'book_id' => $book_id,
        'member_id' => $member_id,
        'book_title' => $book_title ?: 'Livre ID: ' . $book_id . ' (Introuvable)', 
        'member_name' => $member_name ?: 'Membre ID: ' . $member_id . ' (Introuvable)',
        'date' => $loan_date,
    ];
}

// Traitement du changement de mode via URL (GET)
if (isset($_GET['set_mode'])) {
    $new_mode = htmlspecialchars($_GET['set_mode']);
    
    if (set_mode($new_mode)) { 
        // Redirection après succès pour éviter la resoumission du GET
        header('Location: index.php');
        exit;
    }
}
$current_mode = get_mode();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($bib_name); ?> Administration</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="icon" type="image/x-icon" href="../favicon.png">
</head>
<body>
    <header>
        <h1>⚙ Tableau de Bord <?php echo htmlspecialchars($bib_name); ?></h1>
        <nav class="admin-nav">
            <a href="../index.php">←📚 Retour à la bibliothèque</a>
            <a href="books.php">📖 Livres</a>&nbsp;
            <a href="members.php">👤 Emprunteurs</a>&nbsp;
            <a href="loans.php">📝 Prêts</a>&nbsp;
            <a href="gestion.php?mode=backup">⚙️ Gestion</a>&nbsp;
            <a href="logout.php" class="logout-link">🚪 Se déconnecter</a>
        </nav>
    </header>

    <main>
        
        <section class="admin-tools">
            <h2>Gestion de la Bibliothèque</h2>
            <div class="tool-links">
                <a href="gestion.php?mode=backup" class="btn-primary">💾 Sauvegarde</a>
                <a href="gestion.php?mode=restore" class="btn-primary">🔄 Restauration</a>
                <a href="gestion.php?mode=import" class="btn-primary">📥 Import de Données</a>
            </div>
        </section>
        
        <div class="mode-selector">
            <h3>Mode d'affichage des livres :</h3>
            <?php 
            // Détermine la classe active pour le style
            $cover_link_class = ($current_mode === 'cover') ? 'mode-active' : '';
            $text_link_class = ($current_mode === 'text') ? 'mode-active' : '';
            ?>
            <a href="?set_mode=cover" class="mode-link <?php echo $cover_link_class; ?>">
                Couverture
            </a>
            <a href="?set_mode=text" class="mode-link <?php echo $text_link_class; ?>">
                Texte
            </a>
        </div>

        <hr>

        <div class="stats-grid">
            <div class="stat-box">
                <h3>Nombre de Livres</h3>
                <p><?php echo $total_books; ?></p>
            </div>
            <div class="stat-box">
                <h3>Disponibles</h3>
                <p><?php echo books_available(); ?></p>
            </div>
            <div class="stat-box">
                <h3>Prêts en Cours</h3>
                <p><?php echo $current_loans; ?></p>
            </div>
        </div>
        
        <h2>Derniers Prêts en Cours (5 derniers)</h2>
        <?php if (empty($loans_list)): ?>
            <p>Aucun prêt en cours.</p>
        <?php else: ?>
            <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID Prêt</th>
                        <th>ID Livre</th>
                        <th>ID Emprunteur</th>
                        <th>Date Prêt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($loans_list, 0, 5) as $loan): // Affiche les 5 premiers ?>
                        <tr>
                            <td><?php echo htmlspecialchars($loan['id']); ?></td>
                            <td><?php
                                echo htmlspecialchars((string)$loan['book_title']).' ['
                                        .str_pad((int)$loan['book_id'], 4, '0', STR_PAD_LEFT)
                                        .']'; 
                            ?></td>
                            <td><?php 
                                echo htmlspecialchars((string)$loan['member_name']).' ['
                                        .str_pad((int)$loan['member_id'], 4, '0', STR_PAD_LEFT)
                                        .']'; 
                            ?></td>
                            <td style="text-align:center;"><?php echo htmlspecialchars(date_toJJMMAAA((string)$loan['date'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <p><a href="loans.php">Voir tous les prêts</a></p>
        <?php endif; ?>

    </main>
</body>
</html>