<?php
/**
 * convert.php
 * Script unique pour la conversion de books.xml vers books.json
 * Doit être placé dans /bibliotheque/admin/
 */

// ---------------------------------------------------------------------
// 1. DÉFINITION DES CHEMINS
// ---------------------------------------------------------------------

// Le chemin est relatif à /bibliotheque/admin/
define('XML_SOURCE_PATH', __DIR__ . '/../data/books.xml'); 
// Le fichier JSON destination sera dans /bibliotheque/data/books.json
define('JSON_DEST_PATH', __DIR__ . '/../data/books.json'); 

// ---------------------------------------------------------------------
// 2. LOGIQUE DE CONVERSION
// ---------------------------------------------------------------------

echo "Début de la conversion de " . basename(XML_SOURCE_PATH) . "...\n";

// Vérification de l'existence du fichier XML
if (!file_exists(XML_SOURCE_PATH)) {
    die("Erreur : Fichier XML source non trouvé à " . XML_SOURCE_PATH . "\n");
}

// Chargement du fichier XML
$xml = simplexml_load_file(XML_SOURCE_PATH);

if ($xml === false) {
    die("Erreur : Impossible de charger le fichier XML. Vérifiez sa validité.\n");
}

// --- 3. INITIALISATION DE LA STRUCTURE JSON ---
$book_count = 0;
$data_json = [
    // Récupération de l'attribut 'last' de la racine <books>, converti en ENTIER
    //'last' => (int) $xml['last'], 
    'data' => []
];

// --- 4. ITÉRATION et TRANSFORMATION ---
foreach ($xml->book as $book_xml) {
    $book_json = [];
    
    // a) Conversion des attributs XML en clés JSON
    foreach ($book_xml->attributes() as $key => $value) {
        $string_value = (string) $value;
        $key_string = (string) $key;

        // Gestion des types de données (Entiers et Dates)
        switch ($key_string) {
            case 'id':
            case 'note':
                // Champs numériques (ID, année de publication, note/rating) convertis en ENTIER
                $book_json[$key_string] = is_numeric($string_value) ? (int) $string_value : 0;
                break;
            /*case 'date':
                // Champ date : Conversion au format ISO 8601 YYYY-MM-DD
                $book_json[$key_string] = preg_replace('/^(\d{4})(\d{2})(\d{2})$/', '$1-$2-$3', $string_value);
                break;*/
            default:
                // Tous les autres attributs restent des chaînes de caractères
                $book_json[$key_string] = $string_value;
                break;
        }
    }
    
    // Assurer l'existence de la clé 'tit' même si l'attribut XML est manquant
    if (!isset($book_json['tit'])) {
        $book_json['tit'] = '';
    }

    // b) Gestion du nœud enfant <desc> (description)
    $book_json['desc'] = isset($book_xml->desc) ? trim((string) $book_xml->desc) : '';

    // c) Ajout du livre converti
    $data_json['data'][] = $book_json;
    $book_count++;
}

// --- 5. SAUVEGARDE DU JSON ---

// Encodage avec JSON_UNESCAPED_UNICODE (accents) et JSON_PRETTY_PRINT (lisibilité)
$json_content = json_encode($data_json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

if ($json_content === false) {
    die("Erreur lors de l'encodage JSON : " . json_last_error_msg() . "\n");
}

// Sauvegarde du fichier JSON
if (file_put_contents(JSON_DEST_PATH, $json_content) !== false) {
    $size_kb = round(strlen($json_content) / 1024, 2);
    echo "✅ Succès : {$book_count} livres convertis et sauvegardés dans " . basename(JSON_DEST_PATH) . ".\n";
    echo "Taille du fichier JSON résultant : {$size_kb} Ko.\n";
    echo "Le fichier XML original est conservé.\n";
} else {
    echo "❌ Échec de la sauvegarde. Vérifiez les permissions d'écriture sur le répertoire data/.\n";
}

?>