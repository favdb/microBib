<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des Livres - Admin</title>
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <header>
        <h1>Gestion des Livres</h1>
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

        <h2>Liste des Livres (<?php echo $total_books; ?>)</h2>
        
        <!-- Boutons d'action et filtres sur une seule ligne -->
        <div style="margin-bottom: 20px;">
            <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-bottom: 10px;">
                <!-- Bouton Ajouter -->
                <a href="books.php?mode=add" class="button">➕ Ajouter un nouveau livre</a>
                
                <span style="color: #999; margin: 0 5px;">|</span>
                <strong>Filtres :</strong>
                
                <!-- Bouton "Tous" -->
                <a href="books.php?page=1&sort=<?= htmlspecialchars($sort_by) ?>&dir=<?= htmlspecialchars($sort_dir) ?>&cov=0" 
                   class="button" 
                   style="background-color: <?= $filter_cover === '0' ? '#5cb85c' : '#6c757d' ?>;">
                    <?= $filter_cover === '0' ? '✓' : '' ?> 📚 Tous
                </a>
                
                <!-- Bouton "Sans couverture" -->
                <a href="books.php?page=1&sort=<?= htmlspecialchars($sort_by) ?>&dir=<?= htmlspecialchars($sort_dir) ?>&cov=1" 
                   class="button" 
                   style="background-color: <?= $filter_cover === '1' ? '#d9534f' : '#6c757d' ?>;">
                    <?= $filter_cover === '1' ? '✓' : '' ?> 🖼️ Sans couverture
                </a>
                
                <!-- Bouton "Couvertures locales" -->
                <a href="books.php?page=1&sort=<?= htmlspecialchars($sort_by) ?>&dir=<?= htmlspecialchars($sort_dir) ?>&cov=2" 
                   class="button" 
                   style="background-color: <?= $filter_cover === '2' ? '#f0ad4e' : '#6c757d' ?>;">
                    <?= $filter_cover === '2' ? '✓' : '' ?> 💾 Locales
                </a>
            </div>
            
            <!-- Message du filtre actif -->
            <?php if ($filter_cover === '1'): ?>
                <p style="color: #d9534f; font-style: italic; margin: 0;">
                    🔍 Affichage : Livres sans couverture (vide ou par défaut)
                </p>
            <?php elseif ($filter_cover === '2'): ?>
                <p style="color: #f0ad4e; font-style: italic; margin: 0;">
                    🔍 Affichage : Livres avec couvertures stockées localement (pas d'URL externe)
                </p>
            <?php endif; ?>
        </div>
        
        <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th><?php echo get_sort_link_books('id', $sort_by, $sort_dir, $current_page); ?></th>
                    <th><?php echo get_sort_link_books('tit', $sort_by, $sort_dir, $current_page); ?></th>
                    <th><?php echo get_sort_link_books('auts', $sort_by, $sort_dir, $current_page); ?></th>
                    <th>Note</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php 
            foreach ($books_to_display as $book): 
            ?>
                <tr>
                    <td><?php echo str_pad((int)$book['id'], 4, '0', STR_PAD_LEFT); ?></td>
                    <td><?php echo htmlspecialchars((string)$book['tit']); ?></td>
                    <td><?php echo htmlspecialchars((string)$book['auts']); ?></td>
                    <td><?php echo (int)$book['note'] > 0 ? str_repeat('★', (int)$book['note']) : ' '; ?></td>
                    
                    <td title="<?php echo (int)$book['disp'] === 1 ? 'Disponible' : 'Emprunté'; ?>">
                        <?php echo (int)$book['disp'] === 1 ? '🟢' : '🔴'; ?>
                    </td>
                    
                    <td>
                        <?php 
                        // Conservation du filtre dans les liens
                        $filter_param = ($filter_cover !== '0') ? '&cov=' . urlencode($filter_cover) : '';
                        ?>
                        <a href="books.php?mode=edit&id=<?php echo (int)$book['id']; ?>&sort=<?php echo urlencode($sort_by); ?>&dir=<?php echo urlencode($sort_dir); ?>&page=<?php echo $current_page; ?><?php echo $filter_param; ?>" 
                            title="Modifier/Noter">
                             ✏️
                         </a>
                         |
                         <a href="books.php?mode=delete&id=<?php echo (int)$book['id']; ?><?php echo $filter_param; ?>" 
                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce livre ? Impossible si emprunté.')"
                            class="action-delete"
                            title="Supprimer">
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
                // Conservation du filtre dans la pagination
                $filter_param = ($filter_cover !== '0') ? '&cov=' . urlencode($filter_cover) : '';
                $base_pagination_url = "books.php?sort=$sort_by&dir=$sort_dir$filter_param&page=";
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
        <p style="margin-top: 30px;"><a href="index.php">← Retour au Tableau de Bord</a></p>
    </main>
</body>
</html>