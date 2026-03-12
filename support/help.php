<?php
/* ════════════════════════════════════════════════════════════
   aide.php — Centre d'aide Market Plier
   TODO : Ajouter session_start(), require db.php, auth check,
          traductions i18n, et traitement du formulaire de contact
   ════════════════════════════════════════════════════════════ */
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php /* TODO : include '../includes/theme_init.php'; */ ?>
  <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="../node_modules/@fortawesome/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../styles/settings.css">
  <link rel="stylesheet" href="../styles/aide.css">
  <link rel="stylesheet" href="../styles/theme.css">
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg" />
  <title>Centre d'aide — Market Plier</title>
</head>

<body>

  <!-- ═══ BARRE DU HAUT (identique à settings) ════════════════ -->
  <div class="settings-top-bar">
    <a href="../index.php" class="settings-logo">
      <img src="../assets/images/logo.svg" alt="Market Plier">
    </a>
    <a href="../index.php" class="settings-back-link">
      <i class="fas fa-arrow-left"></i> Retour à l'accueil
    </a>
    <button class="theme-toggle" data-theme-toggle title="Changer le thème">
      <i class="fa-solid fa-moon"></i>
      <i class="fa-solid fa-sun"></i>
    </button>
  </div>

  <!-- ═══ CONTENU PRINCIPAL ════════════════════════════════════ -->
  <main class="settings-container">
    <h1 class="settings-title">Centre d'aide</h1>

    <!-- ── Barre de recherche ── -->
    <div class="aide-search-wrapper">
      <i class="fas fa-search"></i>
      <input
        type="text"
        id="aideSearchInput"
        class="aide-search-input"
        placeholder="Rechercher un problème… ex : colis perdu, remboursement"
        autocomplete="off"
      >
    </div>

    <!-- ── Catégories rapides ── -->
    <div class="aide-categories" id="aideCategories">
      <a href="#section-colis" class="aide-category-btn" data-category="colis">
        <i class="fas fa-box"></i> Colis &amp; Livraison
      </a>
      <a href="#section-retours" class="aide-category-btn" data-category="retours">
        <i class="fas fa-undo-alt"></i> Retours &amp; Remboursements
      </a>
      <a href="#section-paiements" class="aide-category-btn" data-category="paiements">
        <i class="fas fa-credit-card"></i> Paiements &amp; Facturation
      </a>
      <a href="#section-litiges" class="aide-category-btn" data-category="litiges">
        <i class="fas fa-shield-alt"></i> Litiges &amp; Protection
      </a>
      <a href="#section-vendeur" class="aide-category-btn" data-category="vendeur">
        <i class="fas fa-store"></i> Espace vendeur
      </a>
      <a href="#section-contact" class="aide-category-btn" data-category="contact">
        <i class="fas fa-headset"></i> Contacter le support
      </a>
    </div>

    <!-- Message aucun résultat (affiché par JS) -->
    <div class="aide-no-results" id="aideNoResults">
      <i class="fas fa-search"></i>
      Aucun résultat pour cette recherche.
    </div>

    <!-- ════════════════════════════════════════════════════════
         SECTION 1 — Colis & Livraison
    ════════════════════════════════════════════════════════ -->
    <section class="settings-section" id="section-colis" data-aide-section="colis">
      <h2 class="settings-section-title">
        <i class="fas fa-box"></i> Colis &amp; Livraison
      </h2>

      <!-- Délais estimés -->
      <p class="settings-desc">Délais de livraison estimés selon le transporteur :</p>
      <div class="aide-delays-grid">
        <div class="aide-delay-card">
          <div class="aide-delay-icon"></div>
          <div>
            <div class="aide-delay-label">Standard</div>
            <div class="aide-delay-value">3 – 5 j</div>
          </div>
        </div>
        <div class="aide-delay-card">
          <div class="aide-delay-icon"></div>
          <div>
            <div class="aide-delay-label">Express</div>
            <div class="aide-delay-value">1 – 2 j</div>
          </div>
        </div>
        <div class="aide-delay-card">
          <div class="aide-delay-icon"></div>
          <div>
            <div class="aide-delay-label">International</div>
            <div class="aide-delay-value">7 – 14 j</div>
          </div>
        </div>
      </div>

      <div style="margin-top: 24px;">

        <div class="aide-faq-item" data-keywords="suivi colis commande numéro tracking">
          <button class="aide-faq-question">
            Comment suivre mon colis ?
            <i class="fas fa-chevron-down aide-faq-chevron"></i>
          </button>
          <div class="aide-faq-answer">
            Un e-mail avec le numéro de suivi vous est envoyé dès que le vendeur expédie votre commande.
            Rendez-vous sur la page <a href="#">Mes commandes</a>, puis cliquez sur
            <strong>« Suivre »</strong> pour voir la position de votre colis en temps réel.
            <ul>
              <li>Le suivi peut prendre jusqu'à <strong>24h</strong> pour s'activer après l'expédition.</li>
              <li>Vérifiez vos spams si vous n'avez pas reçu l'e-mail de confirmation.</li>
            </ul>
          </div>
        </div>

        <div class="aide-faq-item" data-keywords="colis perdu disparu introuvable volé">
          <button class="aide-faq-question">
            Mon colis est marqué « livré » mais je ne l'ai pas reçu.
            <i class="fas fa-chevron-down aide-faq-chevron"></i>
          </button>
          <div class="aide-faq-answer">
            Pas de panique. Commencez par :
            <ul>
              <li>Vérifier auprès de vos voisins ou de la conciergerie.</li>
              <li>Contrôler l'adresse de livraison dans votre commande.</li>
              <li>Consulter le site du transporteur avec votre numéro de suivi.</li>
            </ul>
            Si le colis est introuvable après 48h, <a href="#section-contact">contactez notre support</a>.
            Nous ouvrirons un litige auprès du transporteur et vous proposerons un remboursement ou un renvoi.
          </div>
        </div>

        <div class="aide-faq-item" data-keywords="colis retard livraison délai dépassé">
          <button class="aide-faq-question">
            Mon colis est en retard, que faire ?
            <i class="fas fa-chevron-down aide-faq-chevron"></i>
          </button>
          <div class="aide-faq-answer">
            Un retard peut être dû à une forte activité (fêtes, grèves) ou à un aléa transporteur.
            <ul>
              <li>Attendez <strong>2 jours ouvrés supplémentaires</strong> au-delà de la date estimée.</li>
              <li>Consultez le suivi : s'il est bloqué depuis plus de 5 jours, signalez le problème
                  via <a href="#">Mes commandes → Signaler un problème</a>.</li>
            </ul>
            Notre équipe prend en charge les retards dépassant <strong>10 jours ouvrés</strong> pour la livraison standard.
          </div>
        </div>

        <div class="aide-faq-item" data-keywords="colis abîmé endommagé cassé emballage">
          <button class="aide-faq-question">
            J'ai reçu un colis endommagé.
            <i class="fas fa-chevron-down aide-faq-chevron"></i>
          </button>
          <div class="aide-faq-answer">
            En cas de dommage à la livraison :
            <ul>
              <li>Prenez des <strong>photos claires</strong> du colis et du produit avant tout usage.</li>
              <li>Signalez le problème dans les <strong>48h</strong> depuis <a href="#">Mes commandes → Signaler un problème</a>.</li>
              <li>Ne retournez pas le colis sans avoir obtenu notre accord au préalable.</li>
            </ul>
            Selon la situation, nous traiterons un remboursement ou un renvoi sous 3 à 5 jours ouvrés.
          </div>
        </div>

        <div class="aide-faq-item" data-keywords="adresse livraison modifier changer erreur">
          <button class="aide-faq-question">
            Puis-je modifier l'adresse de livraison après commande ?
            <i class="fas fa-chevron-down aide-faq-chevron"></i>
          </button>
          <div class="aide-faq-answer">
            Une modification est possible <strong>uniquement si le vendeur n'a pas encore expédié</strong> la commande.
            Rendez-vous dans <a href="#">Mes commandes</a> et cliquez sur <strong>« Modifier l'adresse »</strong>.
            Si le colis est déjà en transit, contactez directement le transporteur avec votre numéro de suivi.
          </div>
        </div>

      </div>
    </section>

    <!-- ════════════════════════════════════════════════════════
         SECTION 2 — Retours & Remboursements
    ════════════════════════════════════════════════════════ -->
    <section class="settings-section" id="section-retours" data-aide-section="retours">
      <h2 class="settings-section-title">
        <i class="fas fa-undo-alt"></i> Retours &amp; Remboursements
      </h2>

      <div class="aide-faq-item" data-keywords="retour renvoyer retourner article produit">
        <button class="aide-faq-question">
          Comment retourner un article ?
          <i class="fas fa-chevron-down aide-faq-chevron"></i>
        </button>
        <div class="aide-faq-answer">
          Vous disposez de <strong>30 jours</strong> après réception pour initier un retour (hors articles signalés non retournables).
          <ul>
            <li>Allez dans <a href="#">Mes commandes</a> → <strong>Retourner l'article</strong>.</li>
            <li>Sélectionnez le motif du retour et imprimez l'étiquette prépayée.</li>
            <li>Déposez le colis dans un point relais dans les 5 jours.</li>
          </ul>
          Le remboursement est déclenché dès réception et vérification par le vendeur.
        </div>
      </div>

      <div class="aide-faq-item" data-keywords="remboursement délai reçu argent virement">
        <button class="aide-faq-question">
          Quand vais-je recevoir mon remboursement ?
          <i class="fas fa-chevron-down aide-faq-chevron"></i>
        </button>
        <div class="aide-faq-answer">
          Une fois le retour validé par le vendeur, le remboursement est traité sous <strong>3 à 5 jours ouvrés</strong>.
          <ul>
            <li><strong>Carte bancaire :</strong> 3 à 5 jours ouvrés selon votre banque.</li>
            <li><strong>Solde Market Plier :</strong> immédiat.</li>
            <li><strong>Virement :</strong> jusqu'à 7 jours ouvrés.</li>
          </ul>
          Sans nouvelles après 10 jours, <a href="#section-contact">contactez le support</a>.
        </div>
      </div>

      <div class="aide-faq-item" data-keywords="mauvais article erreur produit reçu pas bon">
        <button class="aide-faq-question">
          J'ai reçu le mauvais article.
          <i class="fas fa-chevron-down aide-faq-chevron"></i>
        </button>
        <div class="aide-faq-answer">
          Signalez l'erreur dans les <strong>48h</strong> via <a href="#">Mes commandes → Signaler un problème</a>,
          en joignant une photo du produit reçu. Le vendeur prend en charge le retour et le renvoi du bon article
          sans frais supplémentaires pour vous.
        </div>
      </div>

      <div class="aide-faq-item" data-keywords="vendeur refuse retour bloque remboursement litige">
        <button class="aide-faq-question">
          Le vendeur refuse mon retour, que faire ?
          <i class="fas fa-chevron-down aide-faq-chevron"></i>
        </button>
        <div class="aide-faq-answer">
          Si le vendeur ne répond pas sous <strong>3 jours ouvrés</strong> ou refuse un retour légitime,
          vous pouvez <a href="#section-litiges">ouvrir un litige</a>.
          Notre équipe de médiation analysera la situation et pourra imposer le remboursement si votre demande est fondée.
        </div>
      </div>

    </section>

    <!-- ════════════════════════════════════════════════════════
         SECTION 3 — Paiements & Facturation
    ════════════════════════════════════════════════════════ -->
    <section class="settings-section" id="section-paiements" data-aide-section="paiements">
      <h2 class="settings-section-title">
        <i class="fas fa-credit-card"></i> Paiements &amp; Facturation
      </h2>

      <div class="aide-faq-item" data-keywords="paiement refusé carte bancaire erreur échoué">
        <button class="aide-faq-question">
          Mon paiement a été refusé.
          <i class="fas fa-chevron-down aide-faq-chevron"></i>
        </button>
        <div class="aide-faq-answer">
          Plusieurs raisons possibles :
          <ul>
            <li>Fonds insuffisants ou plafond atteint.</li>
            <li>Votre banque a bloqué la transaction (vérifiez votre application bancaire).</li>
            <li>Les informations de carte saisies sont incorrectes.</li>
          </ul>
          Essayez avec un autre moyen de paiement ou contactez votre banque. La commande n'est pas débitée en cas d'échec.
        </div>
      </div>

      <div class="aide-faq-item" data-keywords="facture télécharger reçu pdf commande">
        <button class="aide-faq-question">
          Comment obtenir une facture ?
          <i class="fas fa-chevron-down aide-faq-chevron"></i>
        </button>
        <div class="aide-faq-answer">
          Les factures sont disponibles dans <a href="#">Mes commandes → Détail → Télécharger la facture (PDF)</a>.
          Si la facture est manquante pour une commande ancienne, contactez-nous avec le numéro de commande.
        </div>
      </div>

      <div class="aide-faq-item" data-keywords="double débit deux fois prélevé paiement doublon">
        <button class="aide-faq-question">
          J'ai été débité deux fois pour la même commande.
          <i class="fas fa-chevron-down aide-faq-chevron"></i>
        </button>
        <div class="aide-faq-answer">
          Il s'agit souvent d'une <strong>empreinte bancaire temporaire</strong> qui disparaît sous 3 à 5 jours.
          Si le double débit est confirmé sur votre relevé après ce délai, <a href="#section-contact">contactez le support</a>
          en joignant une capture de votre relevé bancaire. Nous traitons les remboursements de ce type en priorité.
        </div>
      </div>

    </section>

    <!-- ════════════════════════════════════════════════════════
         SECTION 4 — Litiges & Protection acheteur
    ════════════════════════════════════════════════════════ -->
    <section class="settings-section" id="section-litiges" data-aide-section="litiges">
      <h2 class="settings-section-title">
        <i class="fas fa-shield-alt"></i> Litiges &amp; Protection acheteur
      </h2>

      <p class="settings-desc">
        Market Plier protège chaque acheteur. Si une commande ne correspond pas à la description ou n'arrive pas,
        vous êtes couvert.
      </p>

      <div class="aide-faq-item" data-keywords="ouvrir litige problème signaler vendeur">
        <button class="aide-faq-question">
          Comment ouvrir un litige ?
          <i class="fas fa-chevron-down aide-faq-chevron"></i>
        </button>
        <div class="aide-faq-answer">
          <ul>
            <li>Allez dans <a href="#">Mes commandes</a> → <strong>Signaler un problème</strong>.</li>
            <li>Choisissez la nature du problème (non reçu, non conforme, endommagé…).</li>
            <li>Joignez des photos ou preuves si disponible.</li>
          </ul>
          Le vendeur a <strong>3 jours ouvrés</strong> pour répondre. Passé ce délai, notre équipe intervient automatiquement.
        </div>
      </div>

      <div class="aide-faq-item" data-keywords="arnaque fraude vendeur malhonnête suspect">
        <button class="aide-faq-question">
          Je pense avoir affaire à un vendeur frauduleux.
          <i class="fas fa-chevron-down aide-faq-chevron"></i>
        </button>
        <div class="aide-faq-answer">
          Signalez immédiatement le vendeur via le bouton <strong>« Signaler ce vendeur »</strong> sur sa page profil.
          Notre équipe sécurité examine chaque signalement sous <strong>24h</strong>. En cas de fraude avérée,
          le compte est suspendu et vous êtes remboursé intégralement.
          <br><br>
           Ne communiquez jamais de paiement hors de la plateforme Market Plier.
        </div>
      </div>

      <div class="aide-faq-item" data-keywords="protection acheteur garantie couvert remboursé">
        <button class="aide-faq-question">
          Qu'est-ce que la Protection acheteur Market Plier ?
          <i class="fas fa-chevron-down aide-faq-chevron"></i>
        </button>
        <div class="aide-faq-answer">
          Toute commande payée sur Market Plier bénéficie automatiquement de notre protection :
          <ul>
            <li> Remboursement si le colis n'arrive pas.</li>
            <li> Remboursement si l'article ne correspond pas à la description.</li>
            <li> Médiation gratuite en cas de litige avec un vendeur.</li>
          </ul>
          La protection est valable <strong>30 jours</strong> après la date de livraison estimée.
        </div>
      </div>

    </section>

    <!-- ════════════════════════════════════════════════════════
         SECTION 5 — Espace vendeur
    ════════════════════════════════════════════════════════ -->
    <section class="settings-section" id="section-vendeur" data-aide-section="vendeur">
      <h2 class="settings-section-title">
        <i class="fas fa-store"></i> Espace vendeur
      </h2>

      <div class="aide-faq-item" data-keywords="vendre publier annonce article mettre en vente">
        <button class="aide-faq-question">
          Comment mettre un article en vente ?
          <i class="fas fa-chevron-down aide-faq-chevron"></i>
        </button>
        <div class="aide-faq-answer">
          Cliquez sur <strong>« Vendre »</strong> dans la barre de navigation, renseignez le titre, la description,
          le prix et au moins une photo. Votre annonce est publiée en quelques minutes après validation automatique.
        </div>
      </div>

      <div class="aide-faq-item" data-keywords="expédier commande acheteur préparer colis envoyer">
        <button class="aide-faq-question">
          J'ai vendu un article, comment l'expédier ?
          <i class="fas fa-chevron-down aide-faq-chevron"></i>
        </button>
        <div class="aide-faq-answer">
          Dès qu'une commande est passée, vous recevez une notification. Dans <a href="#">Mes ventes</a> :
          <ul>
            <li>Imprimez l'étiquette d'expédition prépayée.</li>
            <li>Emballez soigneusement l'article.</li>
            <li>Déposez le colis dans un point relais ou en bureau de poste sous <strong>3 jours ouvrés</strong>.</li>
          </ul>
          Passé ce délai, la commande peut être annulée automatiquement.
        </div>
      </div>

      <div class="aide-faq-item" data-keywords="virement revenus argent vendeur paiement reçu">
        <button class="aide-faq-question">
          Quand est-ce que je reçois le paiement de ma vente ?
          <i class="fas fa-chevron-down aide-faq-chevron"></i>
        </button>
        <div class="aide-faq-answer">
          Le paiement est libéré <strong>48h après la confirmation de réception</strong> par l'acheteur,
          ou automatiquement <strong>10 jours après la livraison estimée</strong> si l'acheteur ne signale aucun problème.
          Les virements sont traités chaque jour ouvré.
        </div>
      </div>

    </section>

    <!-- ════════════════════════════════════════════════════════
         SECTION 6 — Urgence
    ════════════════════════════════════════════════════════ -->
    <section class="settings-section aide-urgency-section">
      <h2 class="settings-section-title">
        <i class="fas fa-exclamation-circle"></i> Cas urgents
      </h2>
      <p class="settings-desc">
        Pour les situations nécessitant une intervention rapide (fraude, accès piraté, paiement non autorisé).
      </p>
      <div class="danger-actions">
        <a href="#section-contact" class="settings-btn settings-btn-danger">
          <i class="fas fa-lock"></i> Signaler une fraude
        </a>
        <a href="#section-contact" class="settings-btn settings-btn-danger-outline">
          <i class="fas fa-ban"></i> Compte piraté
        </a>
      </div>
    </section>

    <!-- ════════════════════════════════════════════════════════
         SECTION 7 — Contacter le support
    ════════════════════════════════════════════════════════ -->
    <section class="settings-section" id="section-contact" data-aide-section="contact">
      <h2 class="settings-section-title">
        <i class="fas fa-headset"></i> Contacter le support
      </h2>

      <div class="aide-contact-intro">
        <i class="fas fa-clock"></i>
        <span>Notre équipe répond généralement sous <strong>24h</strong> (du lundi au vendredi, 9h – 18h).</span>
      </div>

      <!-- TODO : Ajouter action="contact_handler.php" method="POST" + CSRF + validation PHP -->
      <form id="aideContactForm">

        <div class="settings-field">
          <label class="settings-label" for="contact-sujet">Sujet</label>
          <select class="settings-select" id="contact-sujet" name="sujet" required>
            <option value="" disabled selected>Choisissez une catégorie…</option>
            <option value="colis">Colis &amp; Livraison</option>
            <option value="retour">Retour &amp; Remboursement</option>
            <option value="paiement">Paiement &amp; Facturation</option>
            <option value="litige">Litige avec un vendeur/acheteur</option>
            <option value="compte">Problème de compte</option>
            <option value="fraude">Fraude ou sécurité</option>
            <option value="autre">Autre</option>
          </select>
        </div>

        <div class="settings-field">
          <label class="settings-label" for="contact-commande">Numéro de commande <span style="font-weight:400">(facultatif)</span></label>
          <input
            type="text"
            class="settings-input"
            id="contact-commande"
            name="commande"
            placeholder="ex : MP-2024-00123"
          >
        </div>

        <div class="settings-field">
          <label class="settings-label" for="contact-message">Décrivez votre problème</label>
          <textarea
            class="aide-textarea"
            id="contact-message"
            name="message"
            placeholder="Expliquez votre situation en détail. Plus vous êtes précis, plus nous pouvons vous aider rapidement…"
            required
          ></textarea>
          <span class="settings-hint">Minimum 30 caractères. Joignez des informations comme le numéro de suivi ou une capture d'écran si possible.</span>
        </div>

        <button type="submit" class="settings-btn settings-btn-primary">
          <i class="fas fa-paper-plane"></i> Envoyer ma demande
        </button>

      </form>

      <!-- Toast de confirmation (affiché par JS) -->
      <div class="settings-alert settings-alert-success" id="aideContactSuccess" style="display:none; margin-top: 16px;">
        <i class="fas fa-check-circle"></i>
        Votre demande a bien été envoyée. Nous vous répondrons dans les plus brefs délais.
      </div>

    </section>

  </main><!-- /.settings-container -->


  <!-- ═══ SCRIPTS ══════════════════════════════════════════════ -->
  <script src="../node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    /* ── Thème (identique à settings.php) ── */
    (function () {
      var themeLight = document.getElementById('theme-light');
      var themeDark  = document.getElementById('theme-dark');
      function update() {
        var c = document.documentElement.getAttribute('data-bs-theme') || 'light';
        if (themeLight) themeLight.classList.toggle('active', c === 'light');
        if (themeDark)  themeDark.classList.toggle('active',  c === 'dark');
      }
      if (themeLight) themeLight.addEventListener('click', function () {
        document.documentElement.setAttribute('data-bs-theme', 'light');
        localStorage.setItem('mp-theme', 'light');
        update();
      });
      if (themeDark) themeDark.addEventListener('click', function () {
        document.documentElement.setAttribute('data-bs-theme', 'dark');
        localStorage.setItem('mp-theme', 'dark');
        update();
      });
      update();
      new MutationObserver(update).observe(document.documentElement, {
        attributes: true, attributeFilter: ['data-bs-theme']
      });
    })();
  </script>

  <script>
    /* ── Accordéon FAQ ── */
    document.querySelectorAll('.aide-faq-question').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var answer  = btn.nextElementSibling;
        var isOpen  = btn.classList.contains('open');

        /* Fermer tous les autres items du même parent */
        var section = btn.closest('.settings-section');
        section.querySelectorAll('.aide-faq-question.open').forEach(function (other) {
          if (other !== btn) {
            other.classList.remove('open');
            other.nextElementSibling.classList.remove('open');
          }
        });

        /* Toggle l'item courant */
        btn.classList.toggle('open', !isOpen);
        answer.classList.toggle('open', !isOpen);
      });
    });
  </script>

  <script>
    /* ── Catégories rapides — scroll fluide + highlight ── */
    document.querySelectorAll('.aide-category-btn').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var target = document.querySelector(btn.getAttribute('href'));
        if (!target) return;
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });

        /* Highlight temporaire de la section */
        target.style.transition = 'box-shadow 0.3s ease';
        target.style.boxShadow  = '0 0 0 3px rgba(127,184,133,0.35)';
        setTimeout(function () { target.style.boxShadow = ''; }, 1200);
      });
    });
  </script>

  <script>
    /* ── Recherche FAQ ── */
    var searchInput  = document.getElementById('aideSearchInput');
    var noResults    = document.getElementById('aideNoResults');
    var allSections  = document.querySelectorAll('[data-aide-section]');
    var categories   = document.getElementById('aideCategories');

    searchInput.addEventListener('input', function () {
      var query = searchInput.value.trim().toLowerCase();

      if (!query) {
        /* Réafficher tout */
        allSections.forEach(function (s) { s.style.display = ''; });
        categories.style.display = '';
        noResults.style.display = 'none';
        document.querySelectorAll('.aide-faq-item').forEach(function (item) {
          item.style.display = '';
        });
        return;
      }

      /* Cacher les catégories en mode recherche */
      categories.style.display = 'none';

      var totalVisible = 0;

      allSections.forEach(function (section) {
        var items   = section.querySelectorAll('.aide-faq-item');
        var visible = 0;

        items.forEach(function (item) {
          var keywords = (item.getAttribute('data-keywords') || '').toLowerCase();
          var question = item.querySelector('.aide-faq-question').textContent.toLowerCase();
          var answer   = item.querySelector('.aide-faq-answer').textContent.toLowerCase();
          var match    = keywords.includes(query) || question.includes(query) || answer.includes(query);

          item.style.display = match ? '' : 'none';
          if (match) {
            visible++;
            /* Auto-ouvrir les réponses trouvées */
            item.querySelector('.aide-faq-question').classList.add('open');
            item.querySelector('.aide-faq-answer').classList.add('open');
          }
        });

        section.style.display = visible > 0 ? '' : 'none';
        totalVisible += visible;
      });

      noResults.style.display = totalVisible === 0 ? 'block' : 'none';
    });
  </script>

  <script>
    /* ── Formulaire de contact (démo front-end, remplacer par fetch PHP) ── */
    document.getElementById('aideContactForm').addEventListener('submit', function (e) {
      e.preventDefault();
      /* TODO : Remplacer par un fetch vers contact_handler.php avec CSRF */
      document.getElementById('aideContactSuccess').style.display = 'flex';
      this.reset();
      document.getElementById('aideContactSuccess').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
  </script>

</body>
</html>