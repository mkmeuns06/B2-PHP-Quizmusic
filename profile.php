<?php

session_start();

require_once 'classes/Database.php';
require_once 'classes/Badge.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = Database::getConnexion();
$userId = (int)$_SESSION['user_id'];

$messageSucces = null;
$messageErreur = null;
if (isset($_SESSION['message_succes'])) {
    $messageSucces = $_SESSION['message_succes'];
    unset($_SESSION['message_succes']);
}
if (isset($_SESSION['message_erreur'])) {
    $messageErreur = $_SESSION['message_erreur'];
    unset($_SESSION['message_erreur']);
}

// CSRF simple
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['csrf_token'];

// Traitement de la modification du pseudo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'maj_pseudo') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrfToken, $token)) {
        $_SESSION['message_erreur'] = "Action non autorisée";
        header('Location: profile.php');
        exit;
    }

    $nouveauPseudo = trim($_POST['nouveau_pseudo'] ?? '');

    // Validation basique
    if ($nouveauPseudo === '') {
        $_SESSION['message_erreur'] = 'Le pseudo ne peut pas être vide.';
        header('Location: profile.php');
        exit;
    }
    if (mb_strlen($nouveauPseudo) < 3 || mb_strlen($nouveauPseudo) > 30) {
        $_SESSION['message_erreur'] = 'Le pseudo doit contenir entre 3 et 30 caractères.';
        header('Location: profile.php');
        exit;
    }

    try {
        $check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE pseudo = ? AND id <> ?');
        $check->execute([$nouveauPseudo, $userId]);
        $existe = (int)$check->fetchColumn();
        if ($existe > 0) {
            $_SESSION['message_erreur'] = 'Ce pseudo est déjà utilisé.';
            header('Location: profile.php');
            exit;
        }

        $upd = $pdo->prepare('UPDATE users SET pseudo = ? WHERE id = ?');
        $upd->execute([$nouveauPseudo, $userId]);

        $_SESSION['user_pseudo'] = $nouveauPseudo;
        $_SESSION['message_succes'] = 'Votre pseudo a été mis à jour.';
        header('Location: profile.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['message_erreur'] = "Une erreur est survenue lors de la mise à jour.";
        header('Location: profile.php');
        exit;
    }
}

$stmt = $pdo->prepare('SELECT id, pseudo, email, created_at FROM users WHERE id = ?');
$stmt->execute([$userId]);
$utilisateur = $stmt->fetch();

if (!$utilisateur) {
    header('Location: logout.php');
    exit;
}

$dateInscription = null;
if (!empty($utilisateur['created_at'])) {
    $dateInscription = $utilisateur['created_at'];
}

$stmt = $pdo->prepare('SELECT COUNT(*) FROM scores WHERE user_id = ?');
$stmt->execute([$userId]);
$totalParties = (int)$stmt->fetchColumn();

$sqlThemePref = "SELECT q.titre, q.emoji, q.id, COUNT(*) AS nb FROM scores s INNER JOIN questionnaires q ON q.id = s.questionnaire_id WHERE s.user_id = ? GROUP BY q.id, q.titre, q.emoji ORDER BY nb DESC LIMIT 1";
$prefStmt = $pdo->prepare($sqlThemePref);
$prefStmt->execute([$userId]);
$themePref = $prefStmt->fetch();

$badgesObtenus = Badge::getUserBadges($pdo, $userId);

$inscriptionAff = '-';
if ($dateInscription) {
    try {
        $d = new DateTime($dateInscription);
        $inscriptionAff = $d->format('d/m/Y à H:i');
    } catch (Exception $e) {
        $inscriptionAff = htmlspecialchars($dateInscription);
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon profil - QuizMusic</title>

    <!-- CSS intégré directement -->
    <style>
        /* ============================
           Styles généraux
        ============================ */
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            margin: 0;
            padding: 0;
        }
        a {
            color: #e0e7ff;
            text-decoration: none;
            transition: all 0.2s;
        }
        a:hover { text-decoration: underline; }
        h1, h2, h3 { margin: 0; }
        .container { max-width: 900px; margin: auto; padding: 20px; }
        .section { background: rgba(255,255,255,0.1); padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; }
        .section h2 { margin-bottom: 10px; font-size: 1.5em; }
        .flex { display: flex; align-items: center; gap: 10px; }
        .flex-between { display: flex; justify-content: space-between; align-items: center; }
        .btn {
            padding: 8px 15px;
            background: #6366f1;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn:hover { background: #4f46e5; }
        .btn-cancel { background: gray; }

        /* ============================
           Messages
        ============================ */
        .alert-success { background: #4ade80; color: #064e3b; padding: 8px 12px; border-radius: 8px; margin-bottom: 15px; }
        .alert-error { background: #f87171; color: #7f1d1d; padding: 8px 12px; border-radius: 8px; margin-bottom: 15px; }

        /* ============================
           Badges
        ============================ */
        .badge-list { list-style: none; padding: 0; margin: 0; }
        .badge-list li {
            background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
            color: #1f2937;
            padding: 10px 15px;
            border-radius: 12px;
            margin-bottom: 8px;
            animation: badgeReveal 1.2s ease-out;
        }
        @keyframes badgeReveal {
            0% { transform: scale(0) rotate(-45deg); opacity: 0; }
            50% { transform: scale(1.2) rotate(0deg); opacity: 0.8; }
            100% { transform: scale(1) rotate(0deg); opacity: 1; }
        }

        /* ============================
           Formulaires
        ============================ */
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"] {
            width: 100%;
            padding: 8px;
            border-radius: 8px;
            border: none;
            margin-bottom: 10px;
        }

        /* ============================
           Navigation
        ============================ */
        nav { display: flex; justify-content: space-between; margin-bottom: 20px; }
        nav a { background: rgba(255,255,255,0.2); padding: 6px 12px; border-radius: 8px; transition: all 0.2s; }
        nav a:hover { background: rgba(255,255,255,0.3); }

        /* ============================
           Responsive
        ============================ */
        @media (max-width: 768px) {
            .flex { flex-direction: column; align-items: flex-start; }
            nav { flex-direction: column; gap: 10px; }
        }
    </style>
</head>
<body>
<div class="container">

    <nav>
        <a href="index.php">Retour</a>
        <a href="logout.php">Déconnexion</a>
    </nav>

    <header class="section">
        <h1>Mon profil</h1>
        <p>Gérez vos informations et consultez vos statistiques</p>
    </header>

    <?php if ($messageSucces): ?>
        <div class="alert-success"><?php echo htmlspecialchars($messageSucces); ?></div>
    <?php endif; ?>
    <?php if ($messageErreur): ?>
        <div class="alert-error"><?php echo htmlspecialchars($messageErreur); ?></div>
    <?php endif; ?>

    <section class="section">
        <h2>Informations</h2>
        <div>Pseudo : <?php echo htmlspecialchars($utilisateur['pseudo']); ?></div>
        <div>Email : <?php echo htmlspecialchars($utilisateur['email']); ?></div>
        <div>Date d'inscription : <?php echo $inscriptionAff; ?></div>
    </section>

    <section class="section">
        <h2>Statistiques</h2>
        <div class="flex-between">
            <div>
                <div><?php echo $totalParties; ?></div>
                <div>Parties jouées</div>
            </div>
            <div>
                <div><?php echo $themePref ? htmlspecialchars($themePref['titre']) : 'Aucun thème préféré'; ?></div>
                <div><?php echo $themePref ? ('Le plus joué (' . (int)$themePref['nb'] . ' fois)') : ''; ?></div>
            </div>
        </div>
    </section>

    <section class="section">
        <h2>Mes badges</h2>
        <?php if (!empty($badgesObtenus)): ?>
            <ul class="badge-list">
                <?php foreach ($badgesObtenus as $b): ?>
                    <li>
                        <strong><?php echo htmlspecialchars($b['libelle']); ?></strong>
                        - <?php echo htmlspecialchars($b['description']); ?>
                        <em>(<?php echo (new DateTime($b['earned_at']))->format('d/m/Y H:i'); ?>)</em>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>Aucun badge obtenu pour le moment.</p>
        <?php endif; ?>
    </section>

    <section class="section">
        <h2>Modifier mon pseudo</h2>
        <form method="post">
            <input type="hidden" name="action" value="maj_pseudo">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

            <label for="nouveau_pseudo">Nouveau pseudo</label>
            <input id="nouveau_pseudo" name="nouveau_pseudo" type="text" required minlength="3" maxlength="30"
                   value="<?php echo htmlspecialchars($utilisateur['pseudo']); ?>">

            <button type="submit" class="btn">Enregistrer</button>
            <a href="index.php" class="btn btn-cancel">Annuler</a>
        </form>
    </section>

    <div class="section">
        <a href="historique.php" class="btn">Voir mon historique</a>
    </div>
</div>
</body>
</html>
