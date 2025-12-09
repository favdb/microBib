<?php
// admin/save.php - Module de Sauvegarde
// Ne doit pas être appelé directement sans avoir inclus admin.php

/**
 * Crée une archive ZIP contenant les données demandées et la télécharge.
 * @param string $scope 'all', 'data', ou 'covers'.
 * @return bool|string Retourne true en cas de succès, ou un message d'erreur.
 */
function perform_backup($scope) {
    
    // Vérifie si la classe ZipArchive est disponible (nécessaire en PHP 5.6)
    if (!class_exists('ZipArchive')) {
        return "Erreur: La classe ZipArchive n'est pas disponible sur ce serveur.";
    }
    
    // 1. Définir les chemins et créer le dossier temporaire
    
    // ROOT_PATH est supposé défini via l'inclusion de 'admin.php' qui inclut 'config.php'
    if (!defined('ROOT_PATH')) {
         return "Erreur: Constante ROOT_PATH non définie. Vérifiez les inclusions.";
    }
    
    $temp_dir = ROOT_PATH . 'temp/';
    if (!is_dir($temp_dir)) {
        // Tentative de création avec droits d'écriture
        if (!@mkdir($temp_dir, 0777, true) && !is_dir($temp_dir)) {
            return "Erreur: Impossible de créer le répertoire temporaire pour la sauvegarde: " . $temp_dir;
        }
    }
    
    // Chemin et nom de fichier temporaire pour la sauvegarde
    $temp_filename = $temp_dir . 'bibliotheque_backup_' . date('Ymd_His') . '.zip';
    
    
    // 2. Création de l'archive ZIP
    
    $zip = new ZipArchive();
    
    if ($zip->open($temp_filename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        return "Erreur: Impossible d'ouvrir ou de créer le fichier ZIP: " . basename($temp_filename);
    }

    // --- 2.1. Gestion des Données (dossier data/) ---
    if ($scope === 'all' || $scope === 'data') {
        $data_path = ROOT_PATH . 'data/';
        // Récupérer tous les fichiers .xml dans le dossier data/
        $files = glob($data_path . '*.xml');
        
        foreach ($files as $file) {
            // Assurez-vous que le fichier est bien un fichier
            if (is_file($file)) {
                // Ajouter le fichier dans un dossier 'data/' dans le zip
                $zip->addFile($file, 'data/' . basename($file));
            }
        }
    }

    // --- 2.2. Gestion des Couvertures (dossier covers/) ---
    if ($scope === 'all' || $scope === 'covers') {
        $covers_path = ROOT_PATH . 'covers/';
        // Récupérer tous les fichiers du dossier covers (sauf les dossiers/fichiers cachés)
        $files = glob($covers_path . '*');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                // Ajouter le fichier dans un dossier 'covers/' dans le zip
                $zip->addFile($file, 'covers/' . basename($file));
            }
        }
    }
    
    $zip->close();

    // --- 3. Téléchargement du fichier ---
    if (file_exists($temp_filename)) {
        
        // Définir les en-têtes de téléchargement
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($temp_filename) . '"');
        header('Content-Length: ' . filesize($temp_filename));
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Nettoyer le buffer de sortie (important en PHP 5.6)
        if (ob_get_level()) ob_end_clean(); 
        
        readfile($temp_filename);
        
        // Nettoyer le fichier temporaire après le téléchargement
        @unlink($temp_filename); // Utiliser @ pour éviter une erreur si l'unlink échoue
        
        exit; // Arrêter l'exécution après l'envoi du fichier
    } else {
        return "Erreur: Le fichier ZIP n'a pas été créé ou n'est pas accessible pour le téléchargement.";
    }
}