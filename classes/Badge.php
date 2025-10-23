<?php
class Badge {
    public static function ensureSchema(PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS badges (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) NOT NULL UNIQUE,
            libelle VARCHAR(100) NOT NULL,
            description VARCHAR(255) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_badges (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            badge_id INT NOT NULL,
            earned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_badge (user_id, badge_id),
            INDEX idx_user_date (user_id, earned_at),
            CONSTRAINT fk_user_badges_badge FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        self::ensureDefaults($pdo);
    }

    private static function ensureDefaults(PDO $pdo): void {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM badges");
        $stmt->execute();
        $count = (int)$stmt->fetchColumn();
        if ($count === 0) {
            $ins = $pdo->prepare("INSERT INTO badges (code, libelle, description) VALUES
                ('premier_pas','Premier pas','Jouer son premier quiz'),
                ('explorateur','Explorateur','Jouer tous les thèmes au moins une fois'),
                ('perfectionniste','Perfectionniste','Obtenir 10/10 dans n\'importe quel thème'),
                ('marathon','Marathon','Jouer 10 parties en une journée')");
            $ins->execute();
        }
    }

    public static function getUserBadges(PDO $pdo, int $userId): array {
        self::ensureSchema($pdo);
        $sql = "SELECT b.code,b.libelle,b.description, ub.earned_at
                FROM user_badges ub
                INNER JOIN badges b ON b.id = ub.badge_id
                WHERE ub.user_id = ?
                ORDER BY ub.earned_at ASC";
        $st = $pdo->prepare($sql);
        $st->execute([$userId]);
        return $st->fetchAll();
    }

    public static function evaluateAndAward(PDO $pdo, int $userId, int $questionnaireId, int $score, int $totalQuestions, ?string $playedAt = null): void {
        self::ensureSchema($pdo);
        $pdo->beginTransaction();
        try {
            $badgeIds = self::mapBadgeIds($pdo);
            if (!empty($badgeIds['premier_pas'])) {
                $q = $pdo->prepare("SELECT COUNT(*) FROM scores WHERE user_id = ?");
                $q->execute([$userId]);
                $cnt = (int)$q->fetchColumn();
                if ($cnt >= 1) self::awardIfNotHas($pdo, $userId, $badgeIds['premier_pas']);
            }
            if (!empty($badgeIds['explorateur'])) {
                $q1 = $pdo->query("SELECT COUNT(*) FROM questionnaires WHERE actif = 1");
                $totalThemes = (int)$q1->fetchColumn();
                if ($totalThemes > 0) {
                    $q2 = $pdo->prepare("SELECT COUNT(DISTINCT questionnaire_id) FROM scores WHERE user_id = ?");
                    $q2->execute([$userId]);
                    $distinctPlayed = (int)$q2->fetchColumn();
                    if ($distinctPlayed >= $totalThemes) self::awardIfNotHas($pdo, $userId, $badgeIds['explorateur']);
                }
            }
            if (!empty($badgeIds['perfectionniste'])) {
                $isPerfect = ($score === 10) || ($totalQuestions >= 10 && $score === $totalQuestions);
                if ($isPerfect) self::awardIfNotHas($pdo, $userId, $badgeIds['perfectionniste']);
            }
            if (!empty($badgeIds['marathon'])) {
                $date = $playedAt ? substr($playedAt, 0, 10) : date('Y-m-d');
                $q = $pdo->prepare("SELECT COUNT(*) FROM scores WHERE user_id = ? AND DATE(date_jeu) = ?");
                $q->execute([$userId, $date]);
                $cnt = (int)$q->fetchColumn();
                if ($cnt >= 10) self::awardIfNotHas($pdo, $userId, $badgeIds['marathon']);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
        }
    }

    private static function mapBadgeIds(PDO $pdo): array {
        $rows = $pdo->query("SELECT id, code FROM badges")->fetchAll();
        $map = [];
        foreach ($rows as $r) $map[$r['code']] = (int)$r['id'];
        return $map;
    }

    private static function awardIfNotHas(PDO $pdo, int $userId, int $badgeId): void {
        $st = $pdo->prepare("SELECT 1 FROM user_badges WHERE user_id = ? AND badge_id = ?");
        $st->execute([$userId, $badgeId]);
        if (!$st->fetch()) {
            $ins = $pdo->prepare("INSERT INTO user_badges (user_id, badge_id) VALUES (?, ?)");
            $ins->execute([$userId, $badgeId]);
        }
    }
}