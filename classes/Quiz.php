<?php
/**
 * Classe Quiz
 * Gère le chargement et la logique d'un quiz
 *
 * 📚 RESPONSABILITÉS DE CETTE CLASSE :
 * - Charger les informations d'un thème depuis la BDD
 * - Charger 5 questions aléatoires du thème
 * - Calculer le score d'un joueur
 * - Sauvegarder le score en BDD
 */

class Quiz {
    // 📚 CONCEPT : Propriétés privées encapsulées
    private array $questions = [];      // Tableau d'objets Question (polymorphes)
    private string $themeCode;          // Code du thème ('rock', 'pop_fr'...)
    private array $themeInfo;           // Informations du questionnaire (titre, emoji, etc.)

    /**
     * Constructeur - Initialise le quiz pour un thème donné
     *
     * @param string $themeCode Code du questionnaire (ex: 'rock')
     * @throws Exception Si le thème n'existe pas ou est inactif
     */
    public function __construct(string $themeCode) {
        $this->themeCode = $themeCode;

        // 📚 CONCEPT : Initialisation en deux étapes
        // 1. Charger les infos du thème
        // 2. Charger les questions du thème
        // Si l'étape 1 échoue, on ne fait pas l'étape 2
        $this->chargerTheme();
        $this->chargerQuestionsAleatoires();
    }

    /**
     * Charge les informations du thème depuis la BDD
     *
     * 📚 CONCEPT : Méthode privée (private)
     * Cette méthode est utilisée uniquement en interne
     * Elle ne doit pas être appelée depuis l'extérieur de la classe
     *
     * @throws Exception Si le questionnaire n'existe pas
     */
    private function chargerTheme(): void {
        // 📚 Récupération de la connexion PDO
        $pdo = Database::getConnexion();

        // 📚 CONCEPT : Requête préparée pour la SÉCURITÉ
        // prepare() crée une requête avec des paramètres
        // ? sera remplacé par la valeur qu'on fournit dans execute()
        // AVANTAGES :
        // - Protection contre les injections SQL
        // - MySQL peut optimiser et réutiliser la requête
        $stmt = $pdo->prepare("
            SELECT *
            FROM questionnaires
            WHERE code = ? AND actif = 1
        ");

        // 📚 CONCEPT : Exécution avec paramètres
        // On passe les valeurs dans un tableau
        // PDO échappe automatiquement les caractères dangereux
        $stmt->execute([$this->themeCode]);

        // 📚 CONCEPT : fetch() récupère UNE ligne
        // Retourne un tableau associatif ou false si aucun résultat
        $this->themeInfo = $stmt->fetch();

        // 📚 CONCEPT : Validation et gestion d'erreurs
        // Si le thème n'existe pas ou est désactivé, on lance une exception
        if (!$this->themeInfo) {
            throw new Exception("❌ Questionnaire introuvable ou désactivé : " . htmlspecialchars($this->themeCode));
        }
    }

    /**
     * Charge 5 questions aléatoires du thème
     *
     * 📚 CONCEPT : Randomisation avec ORDER BY RAND()
     * RAND() génère un nombre aléatoire pour chaque ligne
     * ORDER BY RAND() trie les lignes dans un ordre aléatoire
     * LIMIT 5 ne prend que les 5 premières
     *
     * RÉSULTAT : 5 questions différentes à chaque partie !
     */
    private function chargerQuestionsAleatoires(): void {
        $pdo = Database::getConnexion();

        // 📚 SQL avec ORDER BY RAND() pour l'aléatoire
        $sql = "
            SELECT *
            FROM questions
            WHERE questionnaire_id = ?
            ORDER BY RAND()
            LIMIT 5
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$this->themeInfo['id']]);

        // 📚 CONCEPT : fetchAll() récupère TOUTES les lignes
        // Retourne un tableau de tableaux associatifs
        $lignes = $stmt->fetchAll();

        // 📚 CONCEPT POO : Factory Pattern
        // On crée différents types d'objets selon les données
        // C'est comme une "usine" qui fabrique le bon produit selon la commande

        foreach ($lignes as $ligne) {
            // Préparation du tableau des réponses
            $reponses = [
                $ligne['reponse_a'],
                $ligne['reponse_b'],
                $ligne['reponse_c'],
                $ligne['reponse_d']
            ];

            // 📚 CONCEPT : POLYMORPHISME - Instanciation selon le type
            // On crée le bon type d'objet selon le champ 'type_question'
            // Tous ces objets sont des Question, mais avec des comportements différents

            switch ($ligne['type_question']) {
                case 'image':
                    // 📚 Question avec image
                    $question = new QuestionImage(
                        $ligne['id'],
                        $ligne['question'],
                        $reponses,
                        $ligne['bonne_reponse'],
                        $ligne['media_url'],      // Chemin de l'image
                        $ligne['explication']
                    );
                    break;

                case 'audio':
                    // 📚 Question avec audio
                    $question = new QuestionAudio(
                        $ligne['id'],
                        $ligne['question'],
                        $reponses,
                        $ligne['bonne_reponse'],
                        $ligne['media_url'],      // Chemin du fichier audio
                        $ligne['explication']
                    );
                    break;

                default:  // 'texte'
                    // 📚 Question texte classique
                    $question = new QuestionTexte(
                        $ligne['id'],
                        $ligne['question'],
                        $reponses,
                        $ligne['bonne_reponse'],
                        $ligne['explication']
                    );
            }

            // 📚 Ajout de l'objet Question au tableau
            // Peu importe son type, c'est toujours une Question
            $this->questions[] = $question;
        }
    }

    /**
     * Calcule le score en comparant les réponses
     *
     * 📚 CONCEPT : Logique métier encapsulée
     * La vérification des réponses est centralisée ici
     *
     * @param array $reponsesUtilisateur Tableau des réponses (index => choix)
     * @return int Le score (nombre de bonnes réponses)
     */
    public function calculerScore(array $reponsesUtilisateur): int {
        $score = 0;

        // 📚 Parcours de chaque question
        foreach ($this->questions as $index => $question) {
            // Récupération de la réponse pour cette question
            // ?? -1 signifie "si la réponse n'existe pas, prendre -1"
            // -1 est une valeur impossible (les réponses vont de 0 à 3)
            $reponseUser = (int)($reponsesUtilisateur[$index] ?? -1);

            // 📚 CONCEPT : Polymorphisme en action
            // On appelle estCorrect() sans savoir si c'est une QuestionTexte, Image ou Audio
            // Chaque objet utilise la méthode héritée de Question
            if ($question->estCorrect($reponseUser)) {
                $score++;  // Bonne réponse : on incrémente
            }
        }

        return $score;
    }

    /**
     * Sauvegarde le score dans la BDD
     *
     * 📚 CONCEPT : Persistance des données
     * On enregistre le résultat pour l'historique
     *
     * @param int $userId ID de l'utilisateur
     * @param int $score Score obtenu
     * @param int|null $tempsSecondes Temps mis pour répondre (optionnel)
     */
    public function sauvegarderScore(int $userId, int $score, ?int $tempsSecondes = null): void {
        $pdo = Database::getConnexion();

        // 📚 CONCEPT : Requête INSERT préparée
        $sql = "
            INSERT INTO scores (user_id, questionnaire_id, score, total_questions, temps_seconde)
            VALUES (?, ?, ?, ?, ?)
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $userId,
            $this->themeInfo['id'],
            $score,
            count($this->questions),
            $tempsSecondes
        ]);
        require_once __DIR__ . '/Badge.php';
        $playedAt = date('Y-m-d H:i:s');
        Badge::evaluateAndAward($pdo, $userId, (int)$this->themeInfo['id'], (int)$score, (int)count($this->questions), $playedAt);
    }

    // ====== GETTERS ======

    /**
     * Retourne le tableau des questions chargées
     *
     * @return array Tableau d'objets Question (polymorphes)
     */
    public function getQuestions(): array {
        return $this->questions;
    }

    /**
     * Retourne les informations du thème
     *
     * @return array Tableau associatif (titre, emoji, couleur, etc.)
     */
    public function getThemeInfo(): array {
        return $this->themeInfo;
    }
}