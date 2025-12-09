<?php
// view_gallery.php (Affichage de la galerie avec pagination)
// L'inclusion de ce fichier est faite dans index.php,
// Variables utilisées (héritées d'index.php): 
// $livres_selectionnes (contient déjà les livres de la page courante), 
// $total_pages, $page_actuelle, $mode, $filtre_actif.
// Vérifie si des livres sont disponibles pour l'affichage
if (empty($livres_selectionnes)):
    ?>
    <p>Aucun livre trouvé pour ce critère.</p>
<?php else: ?>

    <div class="cover-gallery">
        <?php
        foreach ($livres_selectionnes as $book):
            $cover_value = isset($book['couverture']) ? trim((string) $book['couverture']) : '';
            $is_default_cover = false;
            // 1. Vérifie si la valeur stockée est une URL
            if (!empty($cover_value) && filter_var($cover_value, FILTER_VALIDATE_URL)) {
                // CAS 1: C'est une URL, on l'utilise directement
                $cover_path = htmlspecialchars($cover_value);
            }
            // 2. Sinon, on reprend la logique initiale (fichier local)
            elseif (!empty($cover_value) && file_exists('covers/' . $cover_value)) {
                // CAS 2: C'est un nom de fichier local
                $cover_path = 'covers/' . htmlspecialchars($cover_value);
            }
            // 3. Cas par défaut
            else {
                $cover_path = 'covers/default.jpg'; // Image par défaut si manquante
                $is_default_cover = true;
            }
            if ($cover_path === 'covers/default.jpg') {
                $is_default_cover = true;
            } else {
                $is_default_cover = false;
            }
            if (isset($book['disponible']) && $book['disponible'] === 0) {
                $item_class = ' indisponible';
            } else {
                $item_class = '';
            }
            ?>
            <div class="cover-item<?php echo $item_class; ?>">
                <a href="javascript:void(0);" onclick="showNotice(<?php echo (int) $book['id']; ?>)">
                    <img src="<?php echo $cover_path; ?>"
                         alt="<?php echo htmlspecialchars($book['titre']); ?> - <?php echo htmlspecialchars($book['auteurs']); ?>">

                    <?php if ($is_default_cover): // Ajout du titre si c'est la couverture par défaut  ?>
                        <span class="default-cover-title"><?php echo htmlspecialchars($book['titre']); ?></span>
                    <?php endif; ?>

                    <span class="tooltip-pub">
                        <?php echo htmlspecialchars($book['titre']); ?><br>
                        par <?php echo book_author_format($book['auteurs']); ?>
                    </span>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <?php
    // Vérifie que les variables de pagination ont été définies dans index.php
    if (isset($total_pages) && $total_pages > 1):

        // URL de base pour les liens de pagination
        // Les variables $mode et $filtre_actif sont disponibles car elles sont déclarées dans index.php
        $base_url = 'index.php?mode=' . urlencode($mode) . '&views=' . urlencode($views);
        // Ajoute le filtre uniquement s'il est actif
        if ($filtre_actif) {
            $base_url .= '&filtre=' . urlencode($filtre_actif);
        }
        ?>
        <div class="pagination-nav bottom-nav">

            <?php if ($page_actuelle > 1): ?>
                <a href="<?php echo $base_url; ?>&p=<?php echo $page_actuelle - 1; ?>" class="page-link">← Précédent</a>
            <?php else: ?>
                <span class="page-link disabled">← Précédent</span>
            <?php endif; ?>

            <span class="page-info">
                Page <?php echo $page_actuelle; ?> / <?php echo $total_pages; ?>
            </span>

            <?php if ($page_actuelle < $total_pages): ?>
                <a href="<?php echo $base_url; ?>&p=<?php echo $page_actuelle + 1; ?>" class="page-link">Suivant →</a>
            <?php else: ?>
                <span class="page-link disabled">Suivant →</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

<?php
endif;
// Fin de la vérification initiale (if empty($livres_selectionnes))
?>