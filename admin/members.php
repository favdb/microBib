<?php
require_once 'admin.php';
require_once '../db/crud_loans.php';
require_once '../db/crud_members.php';
check_admin_access();

// --- Logique du Contrôleur ---

$mode = isset($_GET['mode']) ? $_GET['mode'] : 'list';
$member_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$message = '';
$error = '';
$post_data = [];

$xml_members = xml_load(MEMBERS_FILE);

// Logique pour l'AJOUT et la MODIFICATION (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_data = $_POST;
    $is_update = isset($post_data['member_id']) && (int) $post_data['member_id'] > 0;

    // Synchronisation des données POST (lname, fname...) vers les clés XML (nom, prenom...)
    $data = [
        'nom' => trim($post_data['lname']),
        'prenom' => trim($post_data['fname']),
        'email' => trim($post_data['email']),
        'tel' => trim($post_data['phone']),
        'addr_text' => trim($post_data['address']),
    ];

    // Validation minimale : SEUL LE PRÉNOM EST OBLIGATOIRE
    if (empty($data['prenom'])) {
        $error = "Le Prénom est obligatoire.";
    }

    if (empty($error)) {
        if ($is_update) {
            $member_id_to_update = (int) $post_data['member_id'];
            if (member_update($member_id_to_update, $data)) {
                $message = "Membre (ID: $member_id_to_update) modifié avec succès. ✅";
                $mode = 'list';
                $xml_members = xml_load(MEMBERS_FILE); // Rechargement après MODIFICATION
            } else {
                $error = "Erreur lors de la modification du membre. ❌";
                $mode = 'edit';
            }
        } else {
            // LOGIQUE D'AJOUT
            $new_id_input = isset($post_data['id']) ? trim($post_data['id']) : '';
            if ($new_id_input !== '' && is_numeric($new_id_input) && (int) $new_id_input > 0) {
                $new_id = (int) $new_id_input;
            } else {
                $new_id = id_get_next(MEMBERS_FILE);
            }

            if (id_is_used(MEMBERS_FILE, $new_id)) {
                $error = "L'ID $new_id est déjà utilisé.";
                $mode = 'add';
            } else {
                $data['id'] = $new_id;

                if (member_add($data)) {
                    id_update_last(MEMBERS_FILE, $new_id);
                    $message = "Membre (ID: $new_id) ajouté avec succès. ✅";
                    $mode = 'list';
                    $xml_members = xml_load(MEMBERS_FILE); // Rechargement après AJOUT
                } else {
                    $error = "Erreur lors de l'ajout du membre. ❌";
                    $mode = 'add';
                }
            }
        }
    }
}

// Logique pour la SUPPRESSION (via GET)
if ($mode === 'delete' && $member_id > 0) {
    if (member_has_active_loan($member_id)) {
        $error = "Impossible de supprimer le membre (ID: $member_id). Il a des prêts en cours. ❌";
        $mode = 'list';
    } elseif (member_delete($member_id)) {
        $message = "Membre (ID: $member_id) supprimé avec succès. ✅";
        $mode = 'list';
        $xml_members = xml_load(MEMBERS_FILE);
    } else {
        $error = "Erreur lors de la suppression du membre. ❌";
        $mode = 'list';
    }
}


// Récupération des données pour le mode 'edit'
$member_data = [];
if ($mode === 'edit' && $member_id > 0) {
    foreach ($xml_members->member as $member) {
        if ((int) $member['id'] === $member_id) {
            // ATTENTION : Les clés ici sont celles du FORMULAIRE (fname, lname, etc.)
            $member_data = [
                'id' => (int) $member['id'],
                'fname' => (string) $member['prenom'], // prenom -> fname
                'lname' => (string) $member['nom'], // nom -> lname
                'email' => (string) $member['email'],
                'phone' => (string) $member['tel'], // tel -> phone
                'address' => (string) $member->addr, // addr -> address
            ];
            break;
        }
    }
    if (empty($member_data)) {
        $error = "Membre ID $member_id introuvable. 🔎";
        $mode = 'list';
    }
}

// ------------------------------------------------------------------
// LOGIQUE DE TRI ET DE PAGINATION (Mode 'list' uniquement)
// ------------------------------------------------------------------

$total_members = $xml_members->member->count();
$members_per_page = defined('ITEMS_PER_PAGE') ? ITEMS_PER_PAGE : 10;
$current_page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($current_page < 1) {
    $current_page = 1;
}

$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'id'; // Critère par défaut: ID
$sort_dir = isset($_GET['dir']) && $_GET['dir'] === 'desc' ? 'desc' : 'asc';

// Convertir SimpleXMLElement en tableau pour le tri
$members_array = [];
foreach ($xml_members->member as $member) {
    $members_array[] = [
        'id' => (int) $member['id'],
        'nom' => (string) $member['nom'],
        'prenom' => (string) $member['prenom'],
        'email' => (string) $member['email'],
        'tel' => (string) $member['tel'],
    ];
}

// Fonction de comparaison pour le tri
usort($members_array, function ($a, $b) use ($sort_by, $sort_dir) {
    $valA = isset($a[$sort_by]) ? $a[$sort_by] : '';
    $valB = isset($b[$sort_by]) ? $b[$sort_by] : '';

    // Tri spécial pour Nom et Prénom combinés (si demandé)
    if ($sort_by === 'name') {
        $valA = strtolower($a['nom'] . ' ' . $a['prenom']);
        $valB = strtolower($b['nom'] . ' ' . $b['prenom']);
    } else {
        // Pour les autres champs (ID, Email, Téléphone), on convertit en chaîne basse-case pour un tri uniforme
        $valA = is_numeric($valA) ? $valA : strtolower((string) $valA);
        $valB = is_numeric($valB) ? $valB : strtolower((string) $valB);
    }

    if ($valA == $valB)
        return 0;

    $comparison = ($valA < $valB) ? -1 : 1;
    return ($sort_dir === 'asc') ? $comparison : -$comparison;
});

// Calcule la pagination après le tri
$total_pages = ceil($total_members / $members_per_page);
if ($total_pages === 0) {
    $total_pages = 1;
}
if ($current_page > $total_pages) {
    $current_page = $total_pages;
}

$offset = ($current_page - 1) * $members_per_page;

// Extraction des membres pour la page courante
$members_to_display = array_slice($members_array, $offset, $members_per_page);

// Fonction utilitaire pour générer le lien de tri
function get_sort_link($col, $current_sort, $current_dir, $current_page) {
    $new_dir = 'asc';
    $arrow = '';
    if ($current_sort === $col) {
        $new_dir = ($current_dir === 'asc') ? 'desc' : 'asc';
        $arrow = ($current_dir === 'asc') ? ' ▼' : ' ▲';
    }
    $base_url = "members.php?page=$current_page&sort=$col&dir=$new_dir";
    return '<a href="' . $base_url . '">' . ($col === 'name' ? 'Nom et Prénom' : strtoupper($col)) . $arrow . '</a>';
}

// --- Fin Logique ---
?>
<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Gestion des Emprunteurs - Admin</title>
        <link rel="stylesheet" href="../css/admin.css">
    </head>
    <body>
        <header>
            <h1>Gestion des Emprunteurs</h1>
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
                <h2>Liste Complète des Emprunteurs (<?php echo $total_members; ?>)</h2>
                <p><a href="members.php?mode=add" class="button">Ajouter un nouvel emprunteur</a></p>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th><?php echo get_sort_link('id', $sort_by, $sort_dir, $current_page); ?></th>
                                <th><?php echo get_sort_link('name', $sort_by, $sort_dir, $current_page); ?></th>
                                <th>Email</th>
                                <th>Téléphone</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
    <?php
    // AFFICHAGE DES MEMBRES PAGINÉS ET TRIÉS
    foreach ($members_to_display as $member):
        ?>
                                <tr>
                                    <td><?php echo (int) $member['id']; ?></td>
                                    <td><?php echo htmlspecialchars((string) $member['nom']) . ' ' . htmlspecialchars((string) $member['prenom']); ?></td>
                                    <td><?php echo htmlspecialchars((string) $member['email']); ?></td>
                                    <td><?php echo htmlspecialchars((string) $member['tel']); ?></td>
                                    <td>
                                        <a href="members.php?mode=edit&id=<?php echo (int) $member['id']; ?>" 
                                           title="Modifier le membre">
                                            ✏️
                                        </a>
                                        |
                                        <a href="members.php?mode=delete&id=<?php echo (int) $member['id']; ?>" 
                                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce membre ? Impossible s\'il a des prêts en cours.')"
                                           class="action-delete"
                                           title="Supprimer définitivement">
                                            ❌
                                        </a>
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
                        $base_pagination_url = "members.php?sort=$sort_by&dir=$sort_dir&page=";
                        ?>

                        <?php if ($current_page > 1): ?>
                            <a href="<?php echo $base_pagination_url . ($current_page - 1); ?>">&laquo; Précédent</a>
                        <?php endif; ?>

                        <?php
                        $start_link = max(1, $current_page - 2);
                        $end_link = min($total_pages, $current_page + 2);

                        if ($start_link > 1) {
                            echo '<a href="' . $base_pagination_url . '1">1</a> ... ';
                        }

                        for ($i = $start_link; $i <= $end_link; $i++):
                            ?>
                            <a href="<?php echo $base_pagination_url . $i; ?>" class="<?php echo ($i === $current_page) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

        <?php
        if ($end_link < $total_pages) {
            echo ' ... <a href="' . $base_pagination_url . $total_pages . '">' . $total_pages . '</a>';
        }
        ?>

                    <?php if ($current_page < $total_pages): ?>
                            <a href="<?php echo $base_pagination_url . ($current_page + 1); ?>">Suivant &raquo;</a>
                    <?php endif; ?>
                <?php endif; ?>
                </div>


<?php
elseif ($mode === 'add' || $mode === 'edit'):

    $form_title = ($mode === 'add') ? 'Ajouter un Emprunteur' : 'Modifier l\'Emprunteur (ID: ' . $member_id . ')';
    $form_action = ($mode === 'add') ? 'members.php?mode=add' : 'members.php?mode=edit&id=' . $member_id;

    $display_data = ($mode === 'edit' && empty($error)) ? $member_data : $post_data;
    ?>

                <h2><?php echo $form_title; ?></h2>
                <a href="members.php?mode=list">← Retour à la liste</a>

                <form method="POST" action="<?php echo $form_action; ?>">

    <?php if ($mode === 'add'): ?>
                        <h3>ID de l'Emprunteur</h3>
        <?php $next_id_suggestion = id_get_next(MEMBERS_FILE); ?>
                        <label for="id">ID de l'Emprunteur (Laisser vide pour automatique : <?php echo $next_id_suggestion; ?>) :</label>
                        <input type="text" id="id" name="id" 
                               value="<?php echo htmlspecialchars(isset($post_data['id']) ? $post_data['id'] : ''); ?>" 
                               placeholder="<?php echo $next_id_suggestion; ?>">
                        <hr>
    <?php elseif ($mode === 'edit'): ?>
                        <input type="hidden" name="member_id" value="<?php echo $member_id; ?>">
    <?php endif; ?>

                    <label for="lname">Nom :</label>
                    <input type="text" id="lname" name="lname" value="<?php echo htmlspecialchars(isset($display_data['lname']) ? $display_data['lname'] : ''); ?>">

                    <label for="fname">Prénom :</label>
                    <input type="text" id="fname" name="fname" value="<?php echo htmlspecialchars(isset($display_data['fname']) ? $display_data['fname'] : ''); ?>" required>

                    <label for="email">Email :</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars(isset($display_data['email']) ? $display_data['email'] : ''); ?>">

                    <label for="phone">Téléphone :</label>
                    <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars(isset($display_data['phone']) ? $display_data['phone'] : ''); ?>">

                    <label for="address">Adresse (complète) :</label>
                    <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars(isset($display_data['address']) ? $display_data['address'] : ''); ?></textarea>

                    <button type="submit"><?php echo ($mode === 'add') ? 'Ajouter l\'Emprunteur' : 'Enregistrer les Modifications'; ?></button>
                </form>

<?php endif; ?>
            <p style="margin-top: 30px;"><a href="index.php">← Retour au Tableau de Bord</a></p>

        </main>
    </body>
</html>