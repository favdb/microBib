/**
 * Fichier JS pour l'interface publique (script.js)
 * Gère l'affichage de la notice de livre via une fenêtre modale et AJAX.
 */

// Référence à la modale et à son contenu (Assurez-vous que ces IDs existent dans index.php)
var modal = document.getElementById('book-notice-modal');
var modalContent = document.getElementById('modal-body-content');
var idSearchModal = document.getElementById('id-search-modal');
var idSearchForm = document.getElementById('id-search-form'); // [CORRECT] Référence au formulaire

/**
 * Récupère la valeur d'un paramètre spécifique dans l'URL actuelle (window.location.search).
 * @param {string} name Le nom du paramètre (ex: 'mode', 'filtre', 'p').
 * @returns {string|null} La valeur du paramètre ou null s'il n'est pas trouvé.
 */
function getUrlParameter(name) {
    name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
    var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
    var results = regex.exec(location.search);
    return results === null ? null : decodeURIComponent(results[1].replace(/\+/g, ' '));
}

/**
 * Affiche la notice de livre dans la modale en utilisant AJAX pour charger le contenu.
 * @param {number} bookId L'ID du livre à afficher.
 */
function showNotice(bookId) {
    if (!modal || !modalContent) {
        console.error("Erreur: Les éléments de la modale ne sont pas trouvés.");
        return;
    }
    
    // Afficher la modale immédiatement avec un message de chargement
    modal.style.display = 'flex';
    modalContent.innerHTML = 'Chargement de la notice du livre ' + bookId + '...';

    // 1. Récupération des paramètres de l'URL actuelle pour les passer à get_notice.php
    // Ces valeurs sont nécessaires pour le lien de retour du prêt (loan_return.php)
    var mode = getUrlParameter('mode') || 'auteur'; // Default: auteur
    var filtre = getUrlParameter('filtre') || '';    // Default: empty string
    var page = getUrlParameter('p') || '1';          // Default: 1
    
    // 2. Construction de l'URL AJAX complète
    var url = 'get_notice.php?id=' + bookId + 
              '&mode=' + encodeURIComponent(mode) +
              '&filtre=' + encodeURIComponent(filtre) +
              '&p=' + encodeURIComponent(page);
              
    // Création de l'objet XMLHttpRequest
    var xhr = new XMLHttpRequest();
    
    // Définition de la fonction de callback
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) { // Requête terminée
            if (xhr.status === 200) { // Succès
                // Insérer le HTML reçu dans le corps de la modale
                modalContent.innerHTML = xhr.responseText;
            } else { // Erreur
                modalContent.innerHTML = '<p class=\"error-message\">Erreur lors du chargement de la notice (Code: ' + xhr.status + ').</p>';
                console.error('Erreur AJAX:', xhr.status, xhr.statusText);
            }
        }
    };

    // Préparation et envoi de la requête GET au script de notice
    xhr.open('GET', url, true); // true pour asynchrone
    xhr.send();
}

/**
 * Ferme la ou les fenêtres modales.
 * @param {Event} event L'événement de clic (facultatif).
 */
function closeModal(event) {
    // Si un événement est passé, déterminer si le clic était sur le bouton de fermeture ou le fond
    var shouldClose = true;
    if (event) {
        // Si le clic est sur le contenu réel de la modale, on annule la fermeture
        if (event.target.closest('.modal-content')) {
            shouldClose = false;
        }
        // Si le clic est explicitement sur l'icône de fermeture, on autorise la fermeture
        if (event.target.classList.contains('modal-close')) {
             shouldClose = true;
        }
    }

    if (shouldClose) {
        if (modal) modal.style.display = 'none';
        if (idSearchModal) idSearchModal.style.display = 'none';
    }
}

/**
 * Charge la notice d'un livre choisi aléatoirement.
 */
function showRandomNotice() {
    if (!modal || !modalContent) return;

    modal.style.display = 'flex';
    modalContent.innerHTML = 'Recherche d\'un livre au hasard...';

    var xhr = new XMLHttpRequest();
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) { // Requête terminée
            if (xhr.status === 200) { // Succès
                var bookId = parseInt(xhr.responseText.trim());
                if (!isNaN(bookId) && bookId > 0) {
                    // 3. Afficher la notice avec l'ID trouvé (qui va désormais passer les paramètres URL)
                    showNotice(bookId);
                } else {
                    modalContent.innerHTML = '<p class=\"error-message\">Erreur : Aucun livre trouvé ou ID invalide retourné (' + xhr.responseText + ').</p>';
                }
            } else {
                modalContent.innerHTML = '<p class=\"error-message\">Erreur lors de la recherche aléatoire (Code: ' + xhr.status + '). Vérifiez que random_book.php existe.</p>';
            }
        }
    };

    // L'URL DOIT CORRESPONDRE AU NOM DU NOUVEAU FICHIER PHP
    xhr.open('GET', 'random_book.php', true);
    xhr.send();
}

/**
 * Ajoute un écouteur d'événement pour fermer la modale avec la touche Échap.
 * Et ajoute des écouteurs pour les boutons d'action.
 */
document.addEventListener('DOMContentLoaded', function() {
    // Écouteur pour la touche Échap
    document.addEventListener('keydown', function(event) {
        if (event.keyCode === 27 && (modal && modal.style.display === 'flex') || (idSearchModal && idSearchModal.style.display === 'flex')) { // Touche Échap (code 27)
            closeModal();
        }
    });

    // Écouteur pour le clic sur l'arrière-plan de la modale de notice (sur le div.modal)
    if (modal) {
        modal.addEventListener('click', closeModal);
    }
    // Écouteur pour le clic sur l'arrière-plan de la modale de recherche ID
    if (idSearchModal) {
        idSearchModal.addEventListener('click', closeModal);
    }
    
    // Écouteur pour le bouton "Au Hasard"
    var randomBtn = document.getElementById('random-book-btn');
    if (randomBtn) {
        randomBtn.addEventListener('click', showRandomNotice);
    }
    
    // Écouteur pour le bouton "Livre par ID" (Ouvre la modale de saisie)
    var idSearchBtn = document.getElementById('show-id-modal-btn');
    if (idSearchBtn) {
        idSearchBtn.addEventListener('click', showIdSearchModal);
    }

    // NOUVEL AJOUT : Empêche la fermeture de la modale lors de la saisie dans le champ ID
    var inputField = document.getElementById('book-id-input');
    if (inputField) {
        // Intercepter l'événement keydown/keypress/keyup et empêcher sa propagation au-delà de l'input
        inputField.addEventListener('keydown', function(event) {
            // Empêche l'événement de remonter aux conteneurs parents (comme la modale)
            event.stopPropagation();
        });
        // On peut aussi ajouter un écouteur pour la saisie elle-même (input event) par sécurité
        inputField.addEventListener('input', function(event) {
            event.stopPropagation();
        });
    }

    // ===============================================
    // NOUVEL ÉCOUTEUR CRITIQUE POUR LA SOUMISSION DU FORMULAIRE ID
    // ===============================================
    if (idSearchForm) {
        idSearchForm.addEventListener('submit', function(event) {
            event.preventDefault(); // <-- CECI EST ESSENTIEL pour éviter le rechargement de la page

            var inputField = document.getElementById('book-id-input');
            var errorDiv = document.getElementById('id-search-error');
            var bookId = parseInt(inputField.value.trim());

            if (isNaN(bookId) || bookId <= 0) {
                // Afficher l'erreur si l'ID n'est pas un nombre positif
                errorDiv.textContent = "Veuillez entrer un ID de livre valide (nombre entier positif).";
                errorDiv.style.display = 'block';
                return;
            }

            // 1. Fermer la modale de recherche
            if (idSearchModal) idSearchModal.style.display = 'none';
            
            // 2. Afficher la notice du livre
            showNotice(bookId); // Appel à la fonction qui gère l'AJAX
            
            // Masquer l'erreur après un succès
            if (errorDiv) errorDiv.style.display = 'none';
        });
    }
});

/**
 * Affiche la fenêtre modale de recherche par ID.
 */
function showIdSearchModal() {
    if (!idSearchModal) return;

    // Masquer la modale de notice au cas où elle serait ouverte
    if (modal) {
        modal.style.display = 'none';
    }

    // Afficher la modale de recherche
    idSearchModal.style.display = 'flex';
    
    // Réinitialiser le champ d'entrée et le message d'erreur
    var inputField = document.getElementById('book-id-input');
    var errorDiv = document.getElementById('id-search-error');
    if (inputField) inputField.value = '';
    if (errorDiv) errorDiv.style.display = 'none';

    // (Optionnel) Mettre le focus sur le champ de saisie
    if (inputField) inputField.focus();
}