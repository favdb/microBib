<?php
// admin/restore.php - Module de Restauration
// Ne doit pas être appelé directement sans avoir inclus admin.php

/**
 * Restaure les données et/ou les couvertures à partir d'un fichier ZIP téléchargé.
 * @param array $file_data Le tableau $_FILES['restore_file'].
 * @param string $scope 'all', 'data', ou 'covers'.
 * @return true|string Retourne true en cas de succès, ou un message d'erreur.
 */
function perform_restore($file_data, $scope) {
    
    // Vérifie si la classe ZipArchive est disponible
    if (!class_exists('ZipArchive')) {
        return "Erreur: La classe ZipArchive n'est pas disponible sur ce serveur.";
    }
    
    // 1. Vérification et déplacement du fichier temporaire
    if (!isset($file_data['tmp_name']) || $file_data['error'] !== UPLOAD_ERR_OK) {
        return "Erreur lors du téléchargement du fichier ZIP de restauration.";
    }

    $temp_zip_path = $file_data['tmp_name'];
    
    // 2. Préparation et vérification de l'archive
    $zip = new ZipArchive();
    if ($zip->open($temp_zip_path) !== TRUE) {
        return "Erreur: Impossible d'ouvrir le fichier ZIP fourni.";
    }

    $success = true;
    $error_msg = '';

    // --- 3. Restauration des Données XML (data/) ---
    if ($scope === 'all' || $scope === 'data') {
        $extract_data_path = ROOT_PATH . 'data/';
        
        // S'assurer que le dossier data/ existe
        if (!is_dir($extract_data_path)) {
            if (!@mkdir($extract_data_path, 0777, true)) {
                $success = false;
                $error_msg .= "Impossible de créer le répertoire 'data/'. ";
            }
        }
        
        // Extraire uniquement les fichiers qui sont dans le dossier 'data/' du zip.
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            
            // On cherche les fichiers dans 'data/...'
            if (strpos($filename, 'data/') === 0 && substr($filename, -4) === '.xml') {
                // PHP 5.6: La fonction extractTo permet d'extraire un seul fichier en le renommant
                // Ici, on utilise getStream pour une lecture plus sûre et manuelle
                
                // Extraire le fichier XML dans le répertoire data/ de l'application
                // Le nom du fichier est la partie après 'data/'
                $target_file = $extract_data_path . basename($filename);
                
                if (copy("zip://" . $temp_zip_path . "#" . $filename, $target_file) === false) {
                     $success = false;
                     $error_msg .= "Erreur lors de l'extraction de " . basename($filename) . ". ";
                }
            }
        }
        
        // S'assurer qu'au moins un fichier data a été extrait si le scope est 'data'
        if ($scope === 'data' && empty(glob($extract_data_path . '*.xml'))) {
             $success = false;
             $error_msg .= "Aucun fichier de données (.xml) trouvé ou extrait. ";
        }
    }

    // --- 4. Restauration des Couvertures (covers/) ---
    if ($scope === 'all' || $scope === 'covers') {
        $extract_covers_path = ROOT_PATH . 'covers/';

        // S'assurer que le dossier covers/ existe
        if (!is_dir($extract_covers_path)) {
            if (!@mkdir($extract_covers_path, 0777, true)) {
                $success = false;
                $error_msg .= "Impossible de créer le répertoire 'covers/'. ";
            }
        }

        // On utilise l'extraction simple pour le dossier des couvertures
        // Note: Cela extrait tous les fichiers du zip, potentiellement dans un sous-dossier 'covers'
        
        if ($zip->extractTo($extract_covers_path, array_map(function($file) {
                // Filtre les fichiers du zip qui sont dans 'covers/'
                return (strpos($file, 'covers/') === 0) ? $file : null;
            }, array_filter(range(0, $zip->numFiles-1), function($i) use ($zip) {
                return strpos($zip->getNameIndex($i), 'covers/') === 0 && !is_dir("zip://" . $temp_zip_path . "#" . $zip->getNameIndex($i));
            })))) {
                 // Succès d'extraction. La complexité est due à PHP 5.6 et ZipArchive
        } else {
             // Tentative d'extraction simplifiée (moins robuste pour les sous-dossiers)
             $zip->extractTo(ROOT_PATH, array_filter(range(0, $zip->numFiles-1), function($i) use ($zip) {
                 return strpos($zip->getNameIndex($i), 'covers/') === 0;
             }));

             // Si le dossier covers n'est pas rempli après l'extraction (si le zip n'avait pas le dossier racine)
             if (empty(glob($extract_covers_path . '*')) && $scope === 'covers') {
                 $success = false;
                 $error_msg .= "Aucun fichier de couverture n'a pu être extrait. ";
             }
        }
    }
    
    $zip->close();
    
    // Supprimer le fichier ZIP temporaire (qui n'était que le fichier uploadé)
    // Pas nécessaire car il sera supprimé automatiquement de /tmp par PHP.

    if ($success) {
        return true;
    } else {
        return "Restauration terminée, mais avec des erreurs : " . $error_msg;
    }
}