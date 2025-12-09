<?php
// Ce fichier est inclus par books.php. Toutes les variables sont disponibles.

$padded_book_id = str_pad($book_id, 4, '0', STR_PAD_LEFT);
$form_title = ($mode === 'add') ? 'Ajouter un Livre' : 'Modifier le Livre';
$form_action = ($mode === 'add') ? 'books.php?mode=add' : 'books.php?mode=edit&id=' . $book_id;
$next_id_suggestion = ($mode === 'add' && !isset($post_data['id'])) ? id_get_next(BOOKS_FILE) : '';
$return_url = 'books.php?mode=list&page=' . $current_page . '&sort=' . $sort_by . '&dir' . $sort_dir . '&cov=' . $filter_cover;
?>
<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title><?php echo $form_title; ?> - Admin</title>
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

            <a href="<?php echo $return_url; ?>" class="action-button">← Retour à la liste</a>

            <h2><?php echo $form_title; ?></h2>

            <form method="POST" action="<?php echo $form_action; ?>" enctype="multipart/form-data">

                <input type="hidden" name="return_sort" value="<?php echo htmlspecialchars($sort_by); ?>">
                <input type="hidden" name="return_dir" value="<?php echo htmlspecialchars($sort_dir); ?>">
                <input type="hidden" name="return_page" value="<?php echo htmlspecialchars($current_page); ?>">
                <input type="hidden" name="return_cov" value="<?php echo htmlspecialchars($filter_cover); ?>">
                <input type="hidden" name="book_id" value="<?php echo $book_id; ?>">
                <input type="hidden" name="current_cover" value="<?php echo htmlspecialchars(isset($display_data['cover']) ? $display_data['cover'] : ''); ?>"> 

                <div class="form-row">
                    <label for="id">ID du Livre :</label>
                    <div class="input-container">
                        <input type="text" id="id" name="id" 
                               value="<?php echo htmlspecialchars(isset($display_data['id']) ? $display_data['id'] : $next_id_suggestion); ?>" 
                               required 
                               min="1"
                               pattern="\d{1,4}" 
                               title="Veuillez saisir un numéro d'ID (max 4 chiffres)."
                               style="width: 80px; text-align: right; font-weight: bold; font-family: monospace; 
                                      padding: 6px 12px; border: 1px solid #ccc; border-radius: 4px; 
                                      /* Pour s'assurer qu'il s'aligne correctement, supprimez les spinners */
                                      -moz-appearance: textfield; /* Firefox */"
                               onfocus="this.select()"
                               >
                    </div>
                </div>
                <div class="form-row">
                    <label for="tit">Titre du livre :</label>
                    <div class="input-container">
                        <input type="text" id="tit" name="tit" value="<?php echo htmlspecialchars($display_data['tit']); ?>" required="">
                    </div>
                </div>

                <div class="form-row">
                    <label for="auts">Auteur(s) :</label>
                    <div class="input-container">
                        <input type="text" id="auts" name="auts" value="<?php echo htmlspecialchars($display_data['auts']); ?>" required="" list="existing-authors-list" placeholder="Nom, Prénom + Nom, Prénom...">
                    </div>
                </div>
                <datalist id="existing-authors-list">
                    <?php // Insérez ici la boucle PHP pour les auteurs existants si elle n'est pas déjà présente. ?>
                </datalist>

                <div class="form-row">
                    <label for="ser_name">Série :</label>
                    <div class="input-container serie-inputs">
                        <input type="text" id="ser_num" name="ser_num" value="<?php echo htmlspecialchars($display_data['ser_num']); ?>" size="5" placeholder="Ex: 1 ou A" title="Numéro dans la série (Ex: 1, A, Tome I)">
                        <span style="font-size: 1.2em; font-weight: bold; padding-bottom: 2px;">/</span>
                        <input type="text" id="ser_name" name="ser_name" value="<?php echo htmlspecialchars($display_data['ser_name']); ?>" list="existing-series-list" placeholder="Nom de la Saga (Ex: Harry Potter)">
                    </div>
                </div>
                <datalist id="existing-series-list">
                    <?php 
                    // La variable $all_unique_series est disponible via books.php
                    if (isset($all_unique_series) && is_array($all_unique_series)):
                        foreach ($all_unique_series as $series_name_option): 
                    ?>
                            <option value="<?php echo htmlspecialchars($series_name_option); ?>">
                    <?php 
                        endforeach; 
                    endif;
                    ?>
                </datalist>

                <div class="form-row">
                    <label for="isbn">EAN/ISBN :</label>
                    <div class="input-container">
                        <input type="text" id="isbn" name="isbn" value="<?php echo htmlspecialchars($display_data['isbn']); ?>" placeholder="EAN ou ISBN">
                    </div>
                </div>

                <div class="form-row">
                    <label for="pub">Éditeur :</label>
                    <div class="input-container">
                        <input type="text" id="pub" name="pub" value="<?php echo htmlspecialchars($display_data['pub']); ?>" required="" list="existing-pubs-list" placeholder="Ex: France loisirs">
                    </div>
                </div>
                <datalist id="existing-pubs-list">
                    <?php 
                    if (isset($all_unique_series) && is_array($all_unique_series)):
                        foreach ($all_unique_series as $series_name_option): 
                    ?>
                            <option value="<?php echo htmlspecialchars($series_name_option); ?>">
                    <?php 
                        endforeach; 
                    endif;
                    ?>
                </datalist>

                <div class="form-row">
                    <label for="pub_date">Publié le :</label>
                    <div class="input-container">
                        <input type="text" id="pub_date" name="pub_date" placeholder="JJ/MM/AAAA" value="<?php echo htmlspecialchars($display_data['pub_date']); ?>">
                    </div>
                </div>

                <div class="form-row form-row-top-align">
                    <label for="desc">Description/Synopsis :</label>
                    <div class="input-container">
                        <textarea id="desc" name="desc" rows="5" required=""><?php echo htmlspecialchars($display_data['desc']); ?></textarea>
                    </div>
                </div>

                <div class="form-row">
                    <label for="cats">Genre(s) :</label>
                    <div class="input-container">
                        <input type="text" id="cats" name="cats" value="<?php echo htmlspecialchars($display_data['cats']); ?>" required="" list="existing-genres-list" placeholder="Ex: Fantaisie+Science-Fiction">
                    </div>
                </div>
                <datalist id="existing-genres-list">
                    <?php 
                    if (isset($all_unique_genres) && is_array($all_unique_genres)):
                        foreach ($all_unique_genres as $genre): 
                    ?>
                            <option value="<?php echo htmlspecialchars($genre); ?>">
                    <?php 
                        endforeach; 
                    endif;
                    ?>
                </datalist>

                <div class="form-row form-row-top-align">
                    <label>Couverture :</label>
                    <div class="input-container cover-management">

                        <?php
                        $current_cover = isset($display_data['cover']) ? $display_data['cover'] : '';
                        if ($mode === 'edit' && !empty($current_cover)):
                            ?>
                            <div class="current-cover-info">
                                <p style="margin-top: 0;"><b>Fichier actuel :</b> 
                                    <span class="cover-preview-container">
                                        <span class="cover-filename-link">
                                            <?php echo htmlspecialchars($current_cover); ?>
                                        </span>
                                        <div class="cover-tooltip">
                                            <img src="<?php echo COVERS_URL . urlencode($current_cover); ?>" alt="Aperçu de la Couverture" style="max-height: 210px;">
                                        </div>
                                    </span>
                                    <label style="display: inline-flex; align-items: center; font-weight: normal; margin-left: 15px;">
                                        <input type="checkbox" name="delete_cover" value="1" style="width:auto; margin-right: 5px;"> Supprimer l'image actuelle
                                    </label>
                                </p>
                                <p class="small-note">Pour remplacer le fichier actuel, utilisez l'une des options ci-dessous.</p>
                            </div>
                            <hr> <?php endif; ?>

                        <label for="cover">1. Télécharger un nouveau fichier (.jpg, .png, .gif) :</label>
                        <input type="file" id="cover" name="cover" accept="image/jpeg, image/png, image/gif">
                        <p class="small-note">⚠️<i> Les images seront automatiquement redimensionnées à 210px de hauteur et converties en JPEG. Le format <b>WebP n'est pas supporté</b>.</i></p>

                        <p style="text-align: left; font-weight: bold; margin: 5px 0;">OU</p>

                        <label for="cover_url">2. Saisir l'URL de l'image (Ex: https://...) :</label>
                        <input type="text" id="cover_url" name="cover_url" 
                               value="<?php echo htmlspecialchars(isset($post_data['cover_url']) ? $post_data['cover_url'] : ''); ?>" 
                               placeholder="Coller l'URL de l'image ici">

                    </div>
                </div>

                <div class="form-row">
                    <label for="note">Note (Manuelle) :</label>
                    <div class="input-container">
                        <select id="note" name="note">
                            <?php
                            $selected_note = isset($display_data['note']) ? (int) $display_data['note'] : 0;
                            for ($i = 0; $i <= 5; $i++):
                                ?>
                                <option value="<?php echo $i; ?>" <?php echo ($selected_note === $i) ? 'selected' : ''; ?>>
                                    <?php echo $i; ?> étoile(s)
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <?php if ($mode === 'edit'): ?>
                    <div class="form-row">
                        <label>Disponibilité :</label>
                        <div class="input-container">
                            <label style="display:block; font-weight:normal; margin-bottom: 10px;">
                                <input type="checkbox" id="disp" name="disp" value="1" 
                                <?php echo (isset($display_data['disp']) && (int) $display_data['disp'] === 1) ? 'checked' : ''; ?>
                                       style="width:auto; margin:0 5px 0 0;"
                                       > Livre disponible à l'emprunt
                            </label>
                            <p class="small-note">Attention : Changer manuellement la disponibilité peut affecter l'intégrité des prêts en cours.</p>
                        </div>
                    </div>
                <?php endif; ?>


                <div style="margin-top: 20px;">
                    <button type="submit" class="button"><?php echo ($mode === 'add') ? 'Ajouter le Livre' : 'Enregistrer les Modifications'; ?></button>
                </div>
            </form>

        </main>
    </body>
</html>