<?php
/**
 * Aide : Disposition générale + Tableau de bord d’accueil (Français)
 */
?>
<div class="p-3">
  <h5><i class="bi bi-info-circle me-2"></i>CBMSv21 – Aide générale</h5>
  <p>
    Cette page fournit un aperçu général de la <strong>disposition de l’application CBMSv21</strong> 
    et explique comment utiliser ses principales fonctionnalités de navigation.  
    Elle inclut également une aide pour le <strong>tableau de bord d’accueil</strong>, 
    qui est le premier écran affiché après la connexion.
  </p>

  <h6><i class="bi bi-layout-text-window-reverse me-2"></i>Barre de navigation supérieure</h6>
  <ul>
    <li><strong><i class="bi bi-list me-1"></i> Menu</strong> – Ouvre le panneau latéral de navigation à gauche.</li>
    <li><strong><i class="bi bi-calendar3 me-1"></i> Exercice budgétaire</strong> – 
        Sélectionnez l’exercice budgétaire actif utilisé pour la saisie et le reporting des données.</li>
    <li><strong><i class="bi bi-layers me-1"></i> Version</strong> – 
        Choisissez la version des données dans l’exercice sélectionné (par ex. : Brouillon, Approuvé, Final).</li>
    <li><strong><i class="bi bi-diagram-3 me-1"></i> Périmètre de données</strong> – 
        Choisissez le périmètre de données (par ex. : ministère ou entité) sur lequel travailler.</li>
    <li><strong>🌐 Langue</strong> – Permet de changer la langue de l’interface (Anglais, Français, Espagnol, etc.).</li>
    <li><strong><i class="bi bi-person-circle me-1"></i> Utilisateur</strong> – 
        Affiche votre nom d’utilisateur et propose des liens rapides vers Compte, Déconnexion et Aide.</li>
  </ul>

  <h6><i class="bi bi-list me-2"></i>Menu latéral (Navigation)</h6>
  <p>
    Le menu latéral (offcanvas) donne accès aux modules et fonctions du système.
    Les éléments affichés dépendent de vos <strong>rôles et permissions</strong>.
  </p>

  <h6><i class="bi bi-bell me-2"></i>Messages flash</h6>
  <p>
    Les messages (succès, avertissement, erreur, info) apparaissent en haut de la zone de contenu.
    Les messages d’information se ferment automatiquement après quelques secondes.
  </p>

  <h6><i class="bi bi-file-earmark-text me-2"></i>Zone de contenu principale</h6>
  <p>
    Cette zone affiche le contenu de la page sur laquelle vous travaillez actuellement, 
    comme la liste des utilisateurs, les tarifs, les paramètres système ou le workflow.
  </p>

  <h6><i class="bi bi-house-door me-2"></i>Tableau de bord d’accueil</h6>
  <ul>
    <li><strong>Message de bienvenue</strong> – Affiche un message de bienvenue à l’utilisateur connecté (ou « Invité » si non connecté).</li>
    <li><strong>Mes tâches ouvertes</strong> – Affiche un résumé des tâches qui vous sont attribuées et encore ouvertes.</li>
    <li>La liste des tâches est intégrée dans une <code>iframe</code> pour plus de commodité. 
        Utilisez le bouton <strong>Tout afficher</strong> dans l’en-tête de la carte pour ouvrir la liste complète des workflows avec filtres appliqués.</li>
  </ul>

  <h6><i class="bi bi-question-circle me-2"></i>Bouton Aide</h6>
  <p>
    Cliquer sur le bouton <strong>Aide</strong> (en haut à droite de la barre de navigation) ouvre une 
    fenêtre contextuelle avec l’aide.  
    Si un fichier d’aide spécifique existe pour l’écran affiché (par ex. : Liste des utilisateurs, Formulaire utilisateur),
    il sera montré. Sinon, cette aide par défaut apparaît.
  </p>

  <hr>
  <p class="text-muted small">
    <i class="bi bi-lightbulb me-1"></i>
    Conseil : Utilisez le bouton Aide sur chaque page pour afficher des instructions adaptées à cet écran.
  </p>
</div>
