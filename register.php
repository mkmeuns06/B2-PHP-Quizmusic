
<?php
/**
 * Page d'inscription
 * Permet à un nouvel utilisateur de créer un compte
 *
 * 📚 OBJECTIF PÉDAGOGIQUE :
 * - Validation de formulaire multi-champs
 * - Vérification de la correspondance des mots de passe
 * - Gestion des erreurs de doublon (email/pseudo déjà utilisé)
 * - Utilisation de User::register() pour créer un compte
 */

session_start();
require_once 'classes/Database.php';
require_once 'classes/User.php';

$erreur = '';

// 📚 CONCEPT : Validation de formulaire d'inscription
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération des données du formulaire
    // trim() supprime les espaces au début et à la fin
    $pseudo = trim($_POST['pseudo'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // 📚 CONCEPT : Validation côté serveur (TOUJOURS nécessaire)
    // RÈGLE D'OR : Ne JAMAIS faire confiance aux validations HTML côté client !
    // Un utilisateur malveillant peut contourner le HTML facilement

    // Validation du pseudo
    if (empty($pseudo)) {
        $erreur = 'Le pseudo est obligatoire';
    } elseif (strlen($pseudo) < 3) {
        $erreur = 'Le pseudo doit contenir au moins 3 caractères';
    } elseif (strlen($pseudo) > 50) {
        $erreur = 'Le pseudo ne peut pas dépasser 50 caractères';
    }

    // Validation de l'email
    elseif (empty($email)) {
        $erreur = 'L\'email est obligatoire';
    }
    // 📚 CONCEPT : Validation d'email avec filter_var()
    // FILTER_VALIDATE_EMAIL vérifie le format de l'email
    // Retourne false si le format est invalide
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = 'L\'email n\'est pas valide';
    }

    // Validation du mot de passe
    elseif (empty($password)) {
        $erreur = 'Le mot de passe est obligatoire';
    } elseif (strlen($password) < 6) {
        $erreur = 'Le mot de passe doit contenir au moins 6 caractères';
    }

    // 📚 CONCEPT : Vérification de la correspondance des mots de passe
    // Sécurité : l'utilisateur doit taper deux fois son mot de passe
    // pour éviter les fautes de frappe
    elseif ($password !== $password_confirm) {
        $erreur = 'Les mots de passe ne correspondent pas';
    }

    // Si toutes les validations passent, on tente l'inscription
    else {
        // 📚 CONCEPT : Appel de la méthode statique register()
        $user = User::register($pseudo, $email, $password);

        if ($user) {
            // ✅ Inscription réussie
            // 📚 CONCEPT : Connexion automatique après inscription
            // Pour améliorer l'expérience utilisateur
            $_SESSION['user_id'] = $user->getId();
            $_SESSION['user_pseudo'] = $user->getPseudo();

            // 📚 CONCEPT : Message de succès en session (pattern Flash Message)
            // Un message qui ne s'affiche qu'une seule fois
            $_SESSION['message_succes'] = 'Bienvenue ' . htmlspecialchars($pseudo) . ' ! Votre compte a été créé avec succès.';

            // Redirection vers l'accueil
            header('Location: index.php');
            exit;

        } else {
            // ❌ Échec : doublon détecté
            // 📚 CONCEPT : Gestion des contraintes UNIQUE de la BDD
            // User::register() retourne null si le pseudo ou l'email existe déjà
            $erreur = 'Ce pseudo ou cet email est déjà utilisé. Veuillez en choisir un autre.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - QuizMusic 🎵</title>
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        /* Animation d'apparition du formulaire */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-slide-in {
            animation: slideIn 0.5s ease-out;
        }
    </style>
</head>

<body
    class="bg-gradient-to-br from-purple-900 via-blue-900 to-indigo-900 min-h-screen flex items-center justify-center p-4">

<div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-md animate-slide-in">

    <!-- En-tête -->
    <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">🎵 QuizMusic</h1>
        <p class="text-gray-600">Créez votre compte pour jouer</p>
    </div>

    <!-- Affichage des erreurs -->
    <?php if ($erreur): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4 animate-pulse">
            <div class="flex items-center">
                <span class="text-xl mr-2">⚠️</span>
                <span><?php echo htmlspecialchars($erreur); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Formulaire d'inscription -->
    <!-- 📚 novalidate désactive la validation HTML5 pour tester notre validation serveur -->
    <form method="POST" class="space-y-4" novalidate>

        <!-- Champ Pseudo -->
        <div>
            <label class="block text-gray-700 font-medium mb-2">
                Pseudo <span class="text-red-500">*</span>
            </label>
            <!-- 📚 CONCEPT : Préservation des données en cas d'erreur -->
            <!-- value="<?php echo htmlspecialchars($_POST['pseudo'] ?? ''); ?>" -->
            <!-- Si le formulaire a une erreur, on re-remplit le champ automatiquement -->
            <input type="text" name="pseudo" value="<?php echo htmlspecialchars($_POST['pseudo'] ?? ''); ?>"
                   required minlength="3" maxlength="50" placeholder="Votre pseudo (min. 3 caractères)"
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
            <p class="text-sm text-gray-500 mt-1">Entre 3 et 50 caractères</p>
        </div>

        <!-- Champ Email -->
        <div>
            <label class="block text-gray-700 font-medium mb-2">
                Email <span class="text-red-500">*</span>
            </label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required
                   placeholder="votre.email@exemple.fr"
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
        </div>

        <!-- Champ Mot de passe -->
        <div>
            <label class="block text-gray-700 font-medium mb-2">
                Mot de passe <span class="text-red-500">*</span>
            </label>
            <!-- 📚 NOTE : On ne pré-remplit JAMAIS les champs password pour la sécurité -->
            <input type="password" name="password" required minlength="6" placeholder="Minimum 6 caractères"
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
            <p class="text-sm text-gray-500 mt-1">Minimum 6 caractères</p>
        </div>

        <!-- Champ Confirmation mot de passe -->
        <div>
            <label class="block text-gray-700 font-medium mb-2">
                Confirmer le mot de passe <span class="text-red-500">*</span>
            </label>
            <input type="password" name="password_confirm" required minlength="6"
                   placeholder="Retapez votre mot de passe"
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
        </div>

        <!-- Information de sécurité -->
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
            <p class="text-sm text-blue-800">
                🔒 Votre mot de passe sera chiffré de manière sécurisée.
                Nous ne stockons jamais les mots de passe en clair.
            </p>
        </div>

        <!-- Bouton de soumission -->
        <button type="submit"
                class="w-full bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white font-bold py-3 rounded-xl transition-all duration-200 transform hover:scale-105 shadow-lg hover:shadow-xl">
            🚀 Créer mon compte
        </button>
    </form>

    <!-- Lien vers la page de connexion -->
    <div class="mt-6 text-center">
        <p class="text-gray-600">
            Vous avez déjà un compte ?
            <a href="login.php" class="text-purple-600 hover:text-purple-700 font-medium transition-colors">
                Connectez-vous
            </a>
        </p>
    </div>

</div>

<!-- JavaScript pour validation côté client (amélioration UX) -->
<script>
    // 📚 CONCEPT : Validation JavaScript (améliore l'UX mais ne remplace PAS la validation serveur)

    document.querySelector('form').addEventListener('submit', function(e) {
        const password = document.querySelector('input[name="password"]').value;
        const passwordConfirm = document.querySelector('input[name="password_confirm"]').value;

        // Vérification que les mots de passe correspondent
        if (password !== passwordConfirm) {
            e.preventDefault(); // Empêche la soumission du formulaire
            alert('⚠️ Les mots de passe ne correspondent pas !');
            document.querySelector('input[name="password_confirm"]').focus();
            return false;
        }

        // Vérification de la longueur minimale
        if (password.length < 6) {
            e.preventDefault();
            alert('⚠️ Le mot de passe doit contenir au moins 6 caractères !');
            document.querySelector('input[name="password"]').focus();
            return false;
        }
    });

    // Animation des champs lors du focus
    document.querySelectorAll('input').forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('transform', 'scale-105');
        });

        input.addEventListener('blur', function() {
            this.parentElement.classList.remove('transform', 'scale-105');
        });
    });
</script>
</body>

</html>