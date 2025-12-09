<?php
require_once '../db/functions.php';
session_start_secure();

// Détruit toutes les variables de session
$_SESSION = array();

// Si la session utilise des cookies, il faut aussi détruire le cookie de session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Détruit la session
session_destroy();

// Redirige vers la page de connexion
header('Location: ../index.php');
exit;
?>