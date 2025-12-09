<?php 
// view_list.php 
// Variables utilisées:
//  $mode (auteur, genre ou serie),
//  $liste_filtres,
//  $titre_filtres, 
//  $filtre_actif, 
//  $initiale_active, 
//  $initiales_disponibles (ajoutées/modifiées par index.php)

// ----------------------------------------------------
// 1. GESTION DE L'INDEX ALPHABÉTIQUE (Mode Auteur uniquement)
// ----------------------------------------------------
if ($mode === 'auteur'): 
?>
<div class="index-alphabetique">
    <?php 
    // Détermine si le filtre "Tout" est actif (aucune initiale sélectionnée)
    $lien_tout_actif = (is_null($initiale_active) || $initiale_active === 'TOUT') ? 'active-initiale' : '';
    // Le lien pour "Tout" renvoie au mode auteur sans paramètre 'init'
    ?>
    <a href="index.php?mode=auteur<?php echo '&views='.$views; ?>" class="<?php echo $lien_tout_actif; ?>">#</a> 
    
    <?php 
    // Boucle pour afficher toutes les lettres de l'alphabet
    foreach (range('A', 'Z') as $lettre) {
        $est_disponible = in_array($lettre, $initiales_disponibles);
        $est_active = ($lettre === $initiale_active) ? 'active-initiale' : '';

        if ($est_disponible) {
            // Lettre cliquable (ajoute le paramètre 'init' à l'URL)
            echo '<a href="index.php?mode=auteur'.'&views='.$views.'&init=' . $lettre . '" class="' . $est_active . '">' . $lettre . '</a>';
        } else {
            // Lettre non cliquable si aucun auteur ne commence par cette lettre
            echo '<span class="desactive">' . $lettre . '</span>';
        }
    }
    ?>
</div>

<?php 
// 2. FILTRAGE DE LA LISTE DES AUTEURS PAR INITIALE (Avant l'affichage de la liste)
if (!is_null($initiale_active) && $initiale_active !== 'TOUT') {
    $liste_filtres_initiale = [];
    foreach ($liste_filtres as $nom => $compte) {
        // Le substr est crucial pour comparer la première lettre. Utiliser mb_ pour l'UTF-8.
        if (mb_strtoupper(mb_substr($nom, 0, 1, 'UTF-8')) === $initiale_active) {
            $liste_filtres_initiale[$nom] = $compte;
        }
    }
    // Remplacer le tableau original par le tableau filtré
    $liste_filtres = $liste_filtres_initiale;
}

endif; // Fin du bloc if ($mode === 'auteur')

// ----------------------------------------------------
// 3. AFFICHAGE DE LA LISTE FILTRÉE
// ----------------------------------------------------

if (empty($liste_filtres)): 
    if ($mode === 'auteur' && !is_null($initiale_active) && $initiale_active !== 'TOUT') {
        // Message spécifique si aucun auteur n'est trouvé pour l'initiale
        echo '<p style="text-align: center;">Aucun auteur ne commence par ' . htmlspecialchars($initiale_active) . '.</p>';
    } else {
        // Message par défaut
?>
    <p>Aucun <?php echo strtolower($titre_filtres); ?> trouvé.</p>
<?php 
    }
else: 
?>
    <div class="filter-list">
        <ul>
        <?php foreach ($liste_filtres as $item => $count): 
            $is_active = ($item === $filtre_actif);
            // URL encodée pour le filtre
            $encoded_item = urlencode($item);
            
            // Formatage de l'auteur pour l'affichage
            $item_display = htmlspecialchars($item);
            if ($mode === 'auteur') {
                // Utiliser la fonction de formatage pour afficher "Prénom Nom"
                $item_display = htmlspecialchars(book_author_format($item)); 
            }
            
            // CONSERVATION DE L'INITIALE DANS L'URL DES LIENS AUTEUR
            $href = "index.php?mode=" . htmlspecialchars($mode).'&views='.$views . "&filtre=" . $encoded_item;
            if ($mode === 'auteur' && !is_null($initiale_active)) {
                 $href .= "&init=" . $initiale_active;
            }
        ?>
            <li>
                <a href="<?php echo $href; ?>" class="<?php echo $is_active ? 'active-filter' : ''; ?>">
                    <?php echo $item_display; ?> 
                    <span class="item-count">(<?php echo (int)$count; ?>)</span>
                </a>
            </li>
        <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<style>
.index-alphabetique {
    margin-bottom: 2px;
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    border-bottom: 1px solid #ddd;
    padding-bottom: 2px;
}
.index-alphabetique a, .index-alphabetique span {
    padding: 1px 1px;
    margin: 1px;
    text-decoration: none;
    font-weight: bold;
    color: #007bff;
    border-radius: 3px;
    font-size: 0.9em;
    border: 1px solid transparent; /* Pour aligner les spans et les a */
}
.index-alphabetique a:hover {
    border-color: #007bff;
}
.index-alphabetique .active-initiale {
    background-color: #007bff;
    color: white;
    border-color: #007bff;
}
.index-alphabetique .desactive {
    color: #ccc;
    cursor: default;
}
</style>