<?php
// index.php - Point d'entrée de l'interface publique

require_once 'db/config.php';
require_once 'db/functions.php';
require_once 'db/crud_books.php';
require_once 'db/crud_members.php';
require_once 'db/crud_loans.php';
session_start_secure();

// GESTION DU MESSAGE FLASH DE SUCCÈS OU ERREUR
$flash_message = null;
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']); // Supprimer le message après l'avoir récupéré
}


// GESTION DYNAMIQUE DE LIVRES_PAR_PAGE via SESSION ET AJAX
// Traiter la requête POST envoyée par script.js pour mettre à jour la pagination
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_pagination_size'])) {
    $new_size = (int) $_POST['set_pagination_size'];

    // On s'assure que la taille est raisonnable
    if ($new_size > 0 && $new_size < 100) {
        $_SESSION['livres_par_page'] = $new_size;
        echo 'Success:' . $new_size;
        exit;
    }
}

if (isset($_SESSION['livres_par_page'])) {
    define('LIVRES_PAR_PAGE', $_SESSION['livres_par_page']);
} else {
    define('LIVRES_PAR_PAGE', 20);
}


// INITIALISATION
$xml_books = xml_load(BOOKS_FILE);
$bib_name = htmlspecialchars(get_bib_name());
$mode = isset($_GET['mode']) ? strtolower($_GET['mode']) : 'genre';
$views = isset($_GET['views']) ? strtolower($_GET['views']) : 'img';
$filtre_actif = isset($_GET['filtre']) ? urldecode($_GET['filtre']) : null;
$initiale_active = isset($_GET['init']) ? strtoupper($_GET['init']) : null;
$initiales_disponibles = [];
// Variables de pagination
$total_pages = 1;
$page_actuelle = 1;

// LOGIQUE PRINCIPALE (PRÉPARATION DES DONNÉES)
$liste_filtres = array();
$titre_filtres = '';
$livres_selectionnes = array();

// Détermine la liste de filtres auteur/genre à afficher
if ($mode === 'genre') {
    $liste_filtres = counts_genres();
    $titre_filtres = 'Genres';
} elseif ($mode === 'serie') {
    $liste_filtres = counts_series();
    $titre_filtres = 'Séries';
} else {
    $mode = 'auteur';
    $liste_filtres = counts_authors();
    $titre_filtres = 'Auteurs';
    foreach (array_keys($liste_filtres) as $auteur) {
        $initiales_disponibles[] = strtoupper(mb_substr($auteur, 0, 1));
    }
    // Assurez-vous d'avoir uniquement les initiales uniques
    $initiales_disponibles = array_unique($initiales_disponibles);
    sort($initiales_disponibles);
}


// SÉLECTION DES LIVRES
if ($filtre_actif) {
    if ($mode === 'auteur') {
        $livres_selectionnes = books_get_by_author($filtre_actif);
    } elseif ($mode === 'serie') {
        $livres_selectionnes = books_get_by_serie($filtre_actif);
    } else {
        $livres_selectionnes = books_get_by_genre($filtre_actif);
    }
    //tri des livres par ordre alphabétique
    $livres_selectionnes = books_sort_by_title($livres_selectionnes);
}


// CALCUL ET APPLICATION DE LA PAGINATION
if ($filtre_actif && !empty($livres_selectionnes)) {
    $total_livres_trouves = count($livres_selectionnes);
    // Calcul du nombre total de pages
    $total_pages = ceil($total_livres_trouves / LIVRES_PAR_PAGE);
    // Récupération de la page actuelle demandée (par défaut 1)
    $page_actuelle = isset($_GET['p']) ? max(1, min($total_pages, (int) $_GET['p'])) : 1;
    // Calcul de l'offset pour array_slice()
    $offset = ($page_actuelle - 1) * LIVRES_PAR_PAGE;
    // Les livres à afficher (uniquement ceux de la page courante)
    $livres_a_afficher = array_slice($livres_selectionnes, $offset, LIVRES_PAR_PAGE);
    // Assigne le tableau paginé pour l'inclusion de la vue
    $livres_selectionnes = $livres_a_afficher;
} else {
    $total_livres_trouves = 0;
    $total_pages = 1;
    $page_actuelle = 1;
}
include'header.php';
?>
<body data-pagination-size="<?php echo LIVRES_PAR_PAGE; ?>">
    <header class="public-header">
        <h1>📚 <?php echo $bib_name; ?></h1>
        <p><?php echo $xml_books->book->count(); ?> livres (<?php echo books_available(); ?> disponibles)</p>
        <div class="header-controls">
            <?php
            // Détermine l'état actuel et l'état suivant
            $current_view = $views;
            if ($current_view === 'img') {
                $next_view = 'text';
                $button_text = 'Liste 📄';
            } else {
                $next_view = 'img';
                $button_text = 'Images 🖼️';
            }
            $toggle_url = 'index.php?mode=' . urlencode($mode) . '&views=' . $next_view;
            if ($filtre_actif) {
                $toggle_url .= '&filtre=' . urlencode($filtre_actif);
            }
            ?>
            <div class="mode-buttons">
                <a href="index.php?mode=genre<?php echo '&views=' . $views; ?>" class="button-filter <?php echo ($mode === 'genre' && !$filtre_actif) ? 'active' : ''; ?>">Genres</a>
                | 
                <a href="index.php?mode=auteur<?php echo '&views=' . $views; ?>" class="button-filter <?php echo ($mode === 'auteur' && !$filtre_actif) ? 'active' : ''; ?>">Auteurs</a>
                | 
                <a href="index.php?mode=serie<?php echo '&views=' . $views; ?>" class="button-filter <?php echo ($mode === 'serie' && !$filtre_actif) ? 'active' : ''; ?>">Séries</a>
                <a href="<?php echo $toggle_url; ?>" class="button-filter"><?php echo $button_text; ?></a>
            </div>

            <button type="button" id="random-book-btn" class="button-action" onclick="showRandomNotice()">
               au Hasard 🎲
            </button>
            <button type="button" id="show-id-modal-btn" class="button-action" onclick="showIdSearchModal()">
               ID 🆔
            </button>
            <a href="admin/index.php" class="button-action admin-link">
                <span style="font-size: 1.2em;">⚙</span>
            </a>
        </div>
    </header>
    <?php if ($flash_message): ?>
        <div class="alert-success">
            <?php echo $flash_message; ?>
        </div>
    <?php endif; ?>
    <div class="main-container">
        <aside class="sidebar">
            <h3><?php echo htmlspecialchars($titre_filtres); ?></h3>
            <?php
            // INCLUSION DE LA LISTE FILTRÉE
            include 'view_list.php';
            ?>
        </aside>
        <main class="content">
            <?php if ($filtre_actif): ?>                
                <h2><?php 
                    if ($mode==='auteur') {
                         echo book_author_format($filtre_actif); 
                    } else {
                        echo htmlspecialchars($filtre_actif); 
                    }
                    ?> (<?php echo $total_livres_trouves; ?>)</h2>
                <?php
                // inclusion de la galerie ou de la liste de livres
                if ($views === 'img') {
                    include 'view_gallery.php';
                } else { // $views === 'text'
                    include'view_text.php';
                }
                ?>                
            <?php else: ?>                
                <p>Veuillez sélectionner un <?php echo $mode; ?> pour afficher les livres correspondants.</p>                
            <?php endif; ?>
        </main>
    </div>
    <div id="id-search-modal" class="modal" onclick="closeModal(event)">
        <div class="modal-content id-search-content" onclick="event.stopPropagation()">
            <span class="modal-close" onclick="closeModal(event)">&times;</span>
            <h3>Rechercher un livre par ID</h3>
            <form id="id-search-form">
                <input type="number" id="book-id-input" name="book_id" placeholder="Entrez l'ID du livre (ex: 0042)" required min="1">
                <button type="submit" class="button-action">Afficher la Notice</button>
            </form>
            <p id="id-search-error" style="color: red; display: none; margin-top: 10px;"></p>
        </div>
    </div>
    <div id="book-notice-modal" class="modal" onclick="closeModal(event)">
        <div class="modal-content" onclick="event.stopPropagation()">
            <span class="modal-close" onclick="closeModal(event)">&times;</span>
            <div id="modal-body-content">
                Chargement...
            </div>
        </div>
    </div>
    <script src="js/script.js"></script>
</body>
</html>