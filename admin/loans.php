<?php
require_once 'admin.php'; 
require_once '../db/crud_loans.php'; 
require_once '../db/crud_books.php'; 
require_once '../db/crud_members.php'; 
require_once '../db/functions.php'; // Ajouté pour date_toJJMMAAA et autres
check_admin_access();

// --- Logique du Contrôleur ---

$mode = isset($_GET['mode']) ? $_GET['mode'] : 'list';
$loan_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$error = '';

// Chargement des données (Utilisation de json_load qui retourne un tableau PHP)
$loans = json_load(LOANS_FILE); 
// Note : Les helpers CRUD (book_get_title_by_id, etc.) chargent les autres fichiers JSON au besoin.

// ------------------------------------------------------------------
// LOGIQUE DE TRI ET DE PAGINATION
// ------------------------------------------------------------------

// MODIFICATION CLÉ 1: Compter les éléments dans le tableau PHP
$total_loans = count($loans); 
$items_per_page = defined('ITEMS_PER_PAGE') ? ITEMS_PER_PAGE : 10;

$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'date'; // Critère par défaut: date
$sort_dir = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'asc' : 'desc'; // Par défaut: plus récent en premier ('desc')

// Construction du tableau pour le tri et la jointure (Enrichissement des données)
$loans_array = array();
foreach ($loans as $loan) {
    // Les clés 'book' et 'member' sont accessibles directement via la notation tableau
    $loan_date = (string)$loan['date'];
    $book_id = (int)$loan['book'];
    $member_id = (int)$loan['member'];
    
    // Jointure des informations via les helpers (dépendances JSON)
    $book_title = book_get_title_by_id($book_id);
    $member_name = member_get_name_by_id($member_id);
    
    // Détermination du statut : 'return_date' est la clé ajoutée par loan_return
    $return_date = isset($loan['return_date']) ? (string)$loan['return_date'] : null;
    $status = $return_date ? 'Terminé' : 'Actif';
    
    $loans_array[] = [
        'id'          => (int)$loan['id'],
        'book_id'     => $book_id,
        'member_id'   => $member_id,
        'book_title'  => $book_title ?: 'Livre ID: ' . $book_id . ' (Introuvable)', 
        'member_name' => $member_name ?: 'Membre ID: ' . $member_id . ' (Introuvable)',
        'date'        => $loan_date,
        'return_date' => $return_date,
        'status'      => $status,
    ];
}

// LOGIQUE DE TRI
usort($loans_array, function($a, $b) use ($sort_by, $sort_dir) {
    if (!isset($a[$sort_by]) || !isset($b[$sort_by])) {
        return 0;
    }
    
    $valA = $a[$sort_by];
    $valB = $b[$sort_by];

    // Gérer les comparaisons de chaînes (titre, nom)
    if ($sort_by === 'book_title' || $sort_by === 'member_name' || $sort_by === 'status') {
        $comparison = strcasecmp($valA, $valB); 
    } 
    // Gérer les dates (AAAA/MM/JJ)
    elseif ($sort_by === 'date' || $sort_by === 'return_date') {
        $comparison = ($valA < $valB) ? -1 : 1;
        if ($valA === $valB) { $comparison = 0; }
    }
    else {
        // IDs et autres nombres
        $comparison = ((int)$valA < (int)$valB) ? -1 : 1;
        if ((int)$valA === (int)$valB) { $comparison = 0; }
    }

    if ($comparison === 0) { return 0; }
    // Inversion si besoin pour le tri descendant (desc)
    return ($sort_dir === 'asc') ? $comparison : -$comparison;
});


// LOGIQUE DE PAGINATION
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

if ($current_page < 1) {
    $current_page = 1;
}

$total_pages = ceil($total_loans / $items_per_page);
if ($total_pages === 0) {
    $total_pages = 1;
}
if ($current_page > $total_pages) {
    $current_page = $total_pages;
}

$offset = ($current_page - 1) * $items_per_page;
$loans_to_display = array_slice($loans_array, $offset, $items_per_page);

// Fonction utilitaire pour générer les liens de tri
function get_sort_link_loans($col, $current_sort, $current_dir, $current_page) {
    $new_dir = 'asc';
    $arrow = '';
    if ($current_sort === $col) {
        $new_dir = ($current_dir === 'asc') ? 'desc' : 'asc';
        $arrow = ($current_dir === 'asc') ? ' ▼' : ' ▲';
    }

    $base_url = "loans.php?page=$current_page&sort=$col&dir=$new_dir";

    $display_name = ucfirst($col);
    if ($col === 'book_title') { $display_name = 'Livre'; }
    if ($col === 'member_name') { $display_name = 'Emprunteur'; }
    if ($col === 'date') { $display_name = 'Date Prêt'; }
    if ($col === 'return_date') { $display_name = 'Date Retour'; }
    if ($col === 'status') { $display_name = 'Statut'; }


    return "<a href=\"$base_url\">$display_name$arrow</a>";
}
// --- Fin Logique ---
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des Prêts - Admin</title>
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <header>
        <h1>Prêts en Cours</h1>
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
        <?php if (!empty($message)): ?>
            <p class="success-message"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <?php if ($mode === 'list'): ?>
            <h2>Liste des Prêts (<?php echo $total_loans; ?>)</h2>
            
            <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th><?php echo get_sort_link_loans('id', $sort_by, $sort_dir, $current_page); ?></th>
                        <th>Livre [ID]</th>
                        <th>Emprunteur [ID]</th>
                        <th><?php echo get_sort_link_loans('loan_date', $sort_by, $sort_dir, $current_page); ?></th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php 
                // AFFICHAGE DES PRÊTS PAGINÉS ET TRIÉS
                foreach ($loans_to_display as $loan): 
                    $is_active = $loan['is_active'];
                    // Logique pour déterminer la date de retour prévue (à définir)
                    $due_date = "N/A"; // Date théorique (ex: $loan['loan_date'] + 30 jours)
                ?>
                    <tr class="<?php echo $is_active ? 'loan-active' : 'loan-returned'; ?>">
                        <td><?php echo (int)$loan['id']; ?></td>
                        <td style="text-align:center;">
                            <?php
                                echo htmlspecialchars((string)$loan['book_title']).' ['
                                        .str_pad((int)$loan['book_id'], 4, '0', STR_PAD_LEFT)
                                        .']'; 
                            ?></td>
                        <td style="text-align:center;">
                            <?php 
                                echo htmlspecialchars((string)$loan['member_name']).' ['
                                        .str_pad((int)$loan['member_id'], 4, '0', STR_PAD_LEFT)
                                        .']'; 
                            ?></td>
                        <td style="text-align:center;"><?php echo htmlspecialchars(date_toJJMMAAA((string)$loan['date'])); ?></td>
                        <td>
                            <?php if ($is_active): ?>
                                <span class="badge badge-warning" title="Prêt en cours">⏳ En cours</span>
                            <?php else: ?>
                                <span class="badge badge-success" title="Retourné le <?php echo htmlspecialchars($loan['return_date']); ?>">✅ Rendu</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($is_active): ?>
                                <a href="loans.php?mode=return&id=<?php echo (int)$loan['id']; ?>" 
                                   onclick="return confirm('Confirmer le retour de ce livre ?')"
                                   class="action-return"
                                   title="Marquer comme rendu">
                                    ↩️ Retour
                                </a>
                            <?php else: ?>
                                <a href="loans.php?mode=delete&id=<?php echo (int)$loan['id']; ?>" 
                                   onclick="return confirm('Supprimer définitivement ce prêt de l\'historique ?')"
                                   class="action-delete"
                                   title="Supprimer l'historique">
                                    🗑️
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>

            <div class="pagination">
                <?php if ($total_pages > 1): ?>
                    <span>Page <?php echo $current_page; ?> sur <?php echo $total_pages; ?></span>
                    
                    <?php 
                    $base_pagination_url = "loans.php?sort=$sort_by&dir=$sort_dir&page=";
                    ?>
                    
                    <?php if ($current_page > 1): ?>
                        <a href="<?php echo $base_pagination_url . ($current_page - 1); ?>">&laquo; Précédent</a>
                    <?php endif; ?>

                    <?php 
                    $start_link = max(1, $current_page - 2);
                    $end_link = min($total_pages, $current_page + 2);
                    
                    if ($start_link > 1) { echo '<a href="' . $base_pagination_url . '1">1</a> ... '; }
                    
                    for ($i = $start_link; $i <= $end_link; $i++): 
                    ?>
                        <a href="<?php echo $base_pagination_url . $i; ?>" class="<?php echo ($i === $current_page) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php 
                    if ($end_link < $total_pages) { echo ' ... <a href="' . $base_pagination_url . $total_pages . '">' . $total_pages . '</a>'; }
                    ?>

                    <?php if ($current_page < $total_pages): ?>
                        <a href="<?php echo $base_pagination_url . ($current_page + 1); ?>">Suivant &raquo;</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>


        <?php endif; ?>

        <p style="margin-top: 30px;"><a href="index.php">← Retour au Tableau de Bord</a></p>

    </main>
</body>
</html>