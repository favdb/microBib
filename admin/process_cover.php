<?php

/**
 * Traitement des images de couverture : Validation, Redimensionnement et Conversion.
 * * Nécessite l'extension GD.
 */

require_once '../db/config.php'; // Pour COVERS_STORAGE_PATH, COVER_HEIGHT_MAX, JPEG_QUALITY
require_once '../db/functions.php'; // Pour tools_normalize_title_for_cover

// Constantes pour le redimensionnement (si non définies dans config, mais elles le sont)
$max_height = defined('COVER_HEIGHT_MAX') ? COVER_HEIGHT_MAX : 210;
$jpeg_quality = defined('JPEG_QUALITY') ? JPEG_QUALITY : 80;


/**
 * Helper : Redimensionne et sauvegarde l'image.
 * @param resource $image_resource La ressource GD de l'image.
 * @param string $mime_type Le type MIME.
 * @param string $base_filename Le titre normalisé du livre.
 * @return string|false Le nom de fichier sauvegardé ou false en cas d'erreur.
 */
function cover_resize_and_save_image($image_resource, $mime_type, $base_filename) {
    if (!$image_resource) {
        return false;
    }
    
    $width = imagesx($image_resource);
    $height = imagesy($image_resource);
    $new_height = defined('COVER_HEIGHT_MAX') ? COVER_HEIGHT_MAX : 210;
    
    if ($height <= $new_height) {
        $new_width = $width;
    } else {
        $ratio = $width / $height;
        $new_width = round($new_height * $ratio);
    }
    
    $new_image = imagecreatetruecolor($new_width, $new_height);
    
    if ($mime_type === 'image/png' || $mime_type === 'image/gif') {
        imagefill($new_image, 0, 0, imagecolorallocate($new_image, 255, 255, 255));
    }
    
    imagecopyresampled($new_image, $image_resource, 
                       0, 0, 0, 0, 
                       $new_width, $new_height, $width, $height);

    $extension = '.jpeg';
    $final_base_name = $base_filename;
    $i = 0;
    
    // 1. Initialisation avec le nom de base (ex: le-titre-du-livre.jpeg)
    $filename = $final_base_name . $extension;
    $filepath = COVERS_STORAGE_PATH . $filename;
    
    // 2. Boucle pour trouver un nom unique
    while (file_exists($filepath)) {
        $i++;
        
        // Formatage du suffixe (ex: _01, _02, ...)
        $suffix = '_' . str_pad($i, 2, '0', STR_PAD_LEFT);
        
        $filename = $final_base_name . $suffix . $extension;
        $filepath = COVERS_STORAGE_PATH . $filename;

        // Limite de sécurité pour éviter une boucle infinie (si plus de 99 fichiers existent)
        if ($i > 99) { 
            // Fallback: utilisation d'un nom avec timestamp
            $filename = $final_base_name . '-' . time() . $extension;
            $filepath = COVERS_STORAGE_PATH . $filename;
            break; 
        }
    }

    // 3. Enregistrement du fichier
    $success = imagejpeg($new_image, $filepath, defined('JPEG_QUALITY') ? JPEG_QUALITY : 80);

    // Clean up
    imagedestroy($image_resource);
    imagedestroy($new_image);

    return $success ? $filename : false;
}


/**
 * Processus d'une image de couverture (upload ou URL).
 * @param string $temp_path Le chemin temporaire du fichier.
 * @param string $mime_type Le type MIME réel.
 * @param string $upload_type Le type de source ('upload' ou 'url').
 * @param string $book_title Le titre du livre pour le nommage du fichier.
 * @return string|false Le nom du fichier ou une chaîne d'erreur.
 */
function cover_process_image($temp_path, $mime_type, $upload_type, $book_title) {
    
    $error_prefix = "Erreur de la couverture ($upload_type) : ";

    // 1. Validation du type MIME et chargement (avec gestion WebP si supporté)
    switch ($mime_type) {
        case 'image/jpeg':
        case 'image/jpg':
            $image_resource = imagecreatefromjpeg($temp_path);
            break;
        case 'image/png':
            $image_resource = imagecreatefrompng($temp_path);
            break;
        case 'image/gif':
            $image_resource = imagecreatefromgif($temp_path);
            break;
        case 'image/webp':
            if (!function_exists('imagecreatefromwebp')) {
                 return $error_prefix . "Le format WebP n'est pas supporté par votre installation PHP (extension GD manquante).";
            }
            $image_resource = imagecreatefromwebp($temp_path);
            break;
        default:
            return $error_prefix . "Format de fichier non supporté ($mime_type). Seuls JPG, PNG, GIF et WebP sont acceptés.";
    }

    if (!$image_resource) {
        return $error_prefix . "Impossible de charger l'image source. Le fichier est corrompu ou illisible.";
    }
    
    // 2. Normalisation du titre
    $base_filename = tools_normalize_title_for_cover($book_title);
    
    // 3. Redimensionnement et Sauvegarde
    $saved_filename = cover_resize_and_save_image($image_resource, $mime_type, $base_filename);
    
    if ($saved_filename === false) {
        return $error_prefix . "Échec de la sauvegarde de l'image. Vérifiez les droits d'écriture sur " . COVERS_STORAGE_PATH;
    }

    return $saved_filename;
}

/**
 * Normalise une chaîne de titre pour l'utiliser comme nom de fichier de couverture.
 * @param string $title Le titre du livre.
 * @return string Le titre normalisé.
 */
function tools_normalize_title_for_cover($title) {
    $normalized = mb_strtolower((string)$title, 'UTF-8');
    
    // Remplacement des accents
    $normalized = strtr($normalized, 
        'àáâãäåçèéêëìíîïñòóôõöùúûüýÿ', 
        'aaaaaaceeeeiiiinooooouuuuyy'
    );
    
    // Remplacement des espaces, tirets multiples et autres séparateurs par un seul tiret
    $normalized = preg_replace('/[\s_-]+/', '-', $normalized);
    
    // Suppression des caractères non alphanumériques (sauf les tirets)
    $normalized = preg_replace('/[^a-z0-9-]/', '', $normalized);
    
    // Suppression des tirets au début et à la fin
    $normalized = trim($normalized, '-');
    
    if (empty($normalized)) {
        return 'unknown-book';
    }

    return $normalized;
}
