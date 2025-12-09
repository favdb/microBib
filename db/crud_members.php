<?php
// admin/members_crud.php - Fonctions CRUD pour les Emprunteurs
require_once 'config.php';
require_once 'functions.php';
require_once 'crud_loans.php'; 

/**
 * Trouve un emprunteur par son ID (utilitaire pour l'affichage)
 * @param int $member_id
 * @return SimpleXMLElement|null L'objet member ou null
 */
function member_get_by_id($member_id) {
    $xml = xml_load(MEMBERS_FILE);
    foreach ($xml->member as $member) {
        if ((int)$member['id'] === $member_id) {
            return $member;
        }
    }
    return null;
}

/**
 * Ajoute un nouveau membre au XML
 * @param array $data Les données du membre
 * @return bool
 */
function member_add($data) {
    $xml = xml_load(MEMBERS_FILE);

    // L'ID doit être passé dans $data lors de l'ajout
    if (!isset($data['id'])) {
        return false; 
    }

    // Ajout du nouvel élément <member>
    $new_member = $xml->addChild('member');
    $new_member->addAttribute('id', $data['id']);
    // Synchronisation : Utilisation des noms d'attributs XML (nom, prenom, tel)
    $new_member->addAttribute('nom', htmlspecialchars($data['nom']));
    $new_member->addAttribute('prenom', htmlspecialchars($data['prenom']));
    $new_member->addAttribute('email', htmlspecialchars($data['email']));
    $new_member->addAttribute('tel', htmlspecialchars($data['tel'])); // tel
    $new_member->addAttribute('insc_date', date('Y/m/d')); // Date d'inscription
    
    // CRÉATION DU NŒUD ENFANT <addr> (adresse)
    $new_member->addChild('addr', htmlspecialchars($data['addr_text']));
    
    return xml_save($xml, MEMBERS_FILE);
}

/**
 * Met à jour un membre existant
 * @param int $member_id
 * @param array $data
 * @return bool
 */
function member_update($member_id, $data) {
    $xml = xml_load(MEMBERS_FILE);

    foreach ($xml->member as $member) {
        if ((int)$member['id'] === $member_id) {
            // Mise à jour des attributs principaux
            $member['nom'] = htmlspecialchars($data['nom']);      // Attribut nom
            $member['prenom'] = htmlspecialchars($data['prenom']); // Attribut prenom
            $member['email'] = htmlspecialchars($data['email']);
            $member['tel'] = htmlspecialchars($data['tel']);      // Attribut tel
            
            // Mise à jour du NŒUD ENFANT <addr>
            if (!isset($member->addr)) {
                $member->addChild('addr');
            }
            // Mise à jour du contenu texte du nœud <addr>
            $member->addr[0] = htmlspecialchars($data['addr_text']);
            
            return xml_save($xml, MEMBERS_FILE);
        }
    }
    return false; // Membre non trouvé
}

/**
 * Supprime un membre (impossible si prêt en cours)
 * @param int $member_id
 * @return bool
 */
function member_delete($member_id) {
    
    if (member_has_active_loan($member_id)) {
        return false; // Impossible de supprimer, membre a un prêt actif
    }
    
    $xml = xml_load(MEMBERS_FILE);
    $dom = dom_import_simplexml($xml)->ownerDocument; 

    foreach ($xml->member as $member) {
        if ((int)$member['id'] === $member_id) {
            $dom_node = dom_import_simplexml($member);
            $dom_node->parentNode->removeChild($dom_node);
            
            return xml_save($xml, MEMBERS_FILE);
        }
    }
    return false; // Membre non trouvé
}

/**
 * Récupère le nom et prénom d'un membre par son ID.
 * @param int $member_id
 * @return string Nom et prénom, ou "Inconnu"
 */
function member_get_name_by_id($member_id) {
    $xml_members = xml_load(MEMBERS_FILE);
    foreach ($xml_members->member as $member) {
        if ((int)$member['id'] === $member_id) {
            return htmlspecialchars((string)$member['nom']) . ' ' . htmlspecialchars((string)$member['prenom']);
        }
    }
    return 'Membre Inconnu (ID: ' . $member_id . ')';
}

/**
 * [NOUVEAU] Récupère les détails d'un membre par son ID.
 * Utilisé par loan_request.php pour valider l'existence du membre et récupérer ses infos.
 * @param int $member_id
 * @param SimpleXMLElement $xml_members L'objet XML des membres (facultatif, chargé si null).
 * @return array Tableau associatif des détails du membre, ou tableau vide si non trouvé.
 */
function member_get_details_by_id($member_id, $xml_members = null) {
    if (is_null($xml_members)) {
        $xml_members = xml_load(MEMBERS_FILE);
    }
    
    foreach ($xml_members->member as $member) {
        if ((int)$member['id'] === (int)$member_id) {
            return [
                'id'        => (int)$member['id'],
                'nom'       => (string)$member['nom'],
                'prenom'    => (string)$member['prenom'],
                'email'     => (string)$member['email'],
                'tel'       => (string)$member['tel'],
                // Récupération du nœud enfant 'addr'
                'addr_text' => isset($member->addr[0]) ? (string)$member->addr[0] : '', 
                'insc_date' => (string)$member['insc_date'],
            ];
        }
    }
    return [];
}
