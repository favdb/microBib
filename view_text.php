<?php
// view_text.php (Affichage de la galerie en mode liste textuelle avec pagination)
// Variables utilisées (héritées d'index.php): 
// $livres_selectionnes, $total_pages, $page_actuelle, $mode, $filtre_actif.
// Vérifie si des livres sont disponibles pour l'affichage
if (empty($livres_selectionnes)):
    ?>
    <p>Aucun livre trouvé pour ce critère.</p>
<?php else: ?>

    <ul class="book-list">
        <?php
        foreach ($livres_selectionnes as $book):
            // Détermine la classe 'indisponible' si le livre n'est pas disponible
            $disponible = (isset($book['disponible']) && $book['disponible'] === 0) ? ' indisponible' : '';
            ?>
            <li class="book-item">
                <a href="javascript:void(0);" onclick="showNotice(<?php echo (int) $book['id']; ?>)">
                    <span class="book-title">
                        <?php echo htmlspecialchars($book['titre']); ?>
                    </span>
                    <span class="book-author">
                        <?php
                        $auteurs_raw = $book['auteurs'];
                        $auteurs_formatted = '';

                        // 1. On divise les auteurs s'il y en a plusieurs (ex: Auteur1; Auteur2)
                        $auteurs_list = explode('+', $auteurs_raw);

                        foreach ($auteurs_list as $auteur) {
                            $auteur = trim($auteur); // Enlève les espaces inutiles
                            // 2. Vérifie si le format est 'Nom, Prénom'
                            if (strpos($auteur, ',') !== false) {
                                // Sépare par la virgule
                                list($nom, $prenom) = array_map('trim', explode(',', $auteur, 2));
                                $auteur_display = "$prenom $nom"; // Inverse en 'Prénom Nom'
                            } else {
                                // Garde l'auteur tel quel si le format n'est pas 'Nom, Prénom'
                                $auteur_display = $auteur;
                            }

                            // Ajoute l'auteur formaté à la liste, séparé par une virgule pour l'affichage
                            if ($auteurs_formatted) {
                                $auteurs_formatted .= ', ';
                            }
                            $auteurs_formatted .= htmlspecialchars($auteur_display);
                        }

                        // Affiche le résultat entre parenthèses
                        echo ' (' . $auteurs_formatted . ')';
                        ?>
                    </span>
                </a>
                    <?php if ($disponible): ?>
                        <span style="background-color: red; color: white; font-weight: bold;"> Indisponible </span>
                    <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php
    // Bloc de pagination (identique à view_gallery.php)
    if (isset($total_pages) && $total_pages > 1):

        $base_url = 'index.php?mode=' . urlencode($mode) . '&views=' . urlencode($views);
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

<?php endif; ?>