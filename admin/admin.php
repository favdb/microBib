<?php
// admin/admin.php - Fonctions de base et d'accès pour l'administrateur
require_once '../db/functions.php';

/**
 * Vérifie si l'administrateur est connecté. Redirige vers la page de login si ce n'est pas le cas.
 */
function check_admin_access() {
    // start_secure_session() est défini dans db/functions.php
    session_start_secure(); 

    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: login.php');
        exit;
    }
    // Si connecté, l'exécution du script continue
}

?>