<?php
// admin/login.php - Authentification Administrateur
require_once '../db/functions.php';
require_once 'admin.php'; 

// Démarrer la session avant tout output
session_start_secure(); 

// Si l'utilisateur est déjà connecté, on le redirige immédiatement
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération des données du formulaire
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Chargement du fichier XML des administrateurs
    $xml_admin = xml_load(ADMIN_FILE);
    
    if (!$xml_admin) {
        $error_message = "Erreur : Impossible de charger le fichier des administrateurs.";
    } else {
        $is_authenticated = false;

        // Parcourir TOUS les administrateurs dans le fichier XML
        foreach ($xml_admin->admin as $admin_node) {
            $stored_username = (string)$admin_node['user'];
            $stored_password = (string)$admin_node['pass'];
                
            // Vérification des identifiants
            if ($username === $stored_username && $password === $stored_password) {
                $is_authenticated = true;
                break; // Arrêter la recherche dès qu'on trouve une correspondance
            }
        }
        
        if ($is_authenticated) {
            // ÉTAPE CRUCIALE : Régénérer l'ID de session APRÈS l'authentification
            session_regenerate_id(true); 
            
            // Stocker les données de connexion dans la session
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            $_SESSION['login_time'] = time();
            
            // Redirection vers le tableau de bord
            header('Location: index.php');
            exit;
        } else {
            $error_message = "Nom d'utilisateur '$username' ou mot de passe incorrect.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Administrateur - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <div class="login-container">
        
        <?php if (!empty($error_message)): ?>
            <div class="error-message">
                <strong>⚠️ Erreur :</strong> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="login.php">
            <h2 style="text-align: center;">Administration<br><?php echo get_bib_name(); ?></h2>
            <div class="form-group">
                <label for="username">Nom d'utilisateur :</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                    required 
                    autofocus
                    autocomplete="username"
                >
            </div>
            
            <div class="form-group">
                <label for="password">Mot de passe :</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required
                    autocomplete="current-password"
                >
            </div>
            
            <button type="submit" class="btn-primary">Se connecter</button>
        <div class="login-footer">
            <p><a href="../index.php">← Retour à la bibliothèque</a></p>
        </div>
        </form>
        
    </div>
</body>
</html>