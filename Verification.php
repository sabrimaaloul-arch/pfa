<?php
require_once __DIR__ . '/../config/database.php';

class Verification {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Save a verification decision (US-16)
    public function create($id_don, $id_agent, $resultat, $raison_rejet = null) {
        $stmt = $this->conn->prepare(
            "INSERT INTO VERIFICATION (date_verif, resultat, raison_rejet, id_don, id_agent)
             VALUES (CURDATE(), :resultat, :raison_rejet, :id_don, :id_agent)"
        );
        $stmt->bindParam(':resultat',     $resultat);
        $stmt->bindParam(':raison_rejet', $raison_rejet);
        $stmt->bindParam(':id_don',       $id_don);
        $stmt->bindParam(':id_agent',     $id_agent);
        return $stmt->execute();
    }

    // Get verifications done by one agent (US-17)
    public function getByAgent($id_agent) {
        $stmt = $this->conn->prepare(
            "SELECT v.*, d.montant, u.nom AS donateur_nom
             FROM VERIFICATION v
             JOIN DON d         ON v.id_don   = d.id_don
             JOIN UTILISATEUR u ON d.id_user  = u.id_user
             WHERE v.id_agent = :id_agent
             ORDER BY v.date_verif DESC"
        );
        $stmt->bindParam(':id_agent', $id_agent);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Agent performance stats — no DB change needed
    public function getAgentStats($id_agent) {
        $stmt = $this->conn->prepare(
            "SELECT
                COUNT(*)                                                        AS total,
                SUM(CASE WHEN resultat = 'accepte' THEN 1 ELSE 0 END)          AS accepte,
                SUM(CASE WHEN resultat = 'rejete'  THEN 1 ELSE 0 END)          AS rejete,
                SUM(CASE WHEN date_verif = CURDATE() THEN 1 ELSE 0 END)        AS today,
                SUM(CASE WHEN date_verif >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                         THEN 1 ELSE 0 END)                                    AS this_week,
                COALESCE(SUM(d.montant * (resultat = 'accepte')), 0)           AS montant_valide
             FROM VERIFICATION v
             JOIN DON d ON v.id_don = d.id_don
             WHERE v.id_agent = :id_agent"
        );
        $stmt->bindParam(':id_agent', $id_agent, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Monthly breakdown for chart (last 6 months)
    public function getAgentMonthlyStats($id_agent) {
        $stmt = $this->conn->prepare(
            "SELECT
                DATE_FORMAT(date_verif, '%Y-%m')                               AS month,
                DATE_FORMAT(date_verif, '%b %Y')                               AS label,
                COUNT(*)                                                        AS total,
                SUM(CASE WHEN resultat = 'accepte' THEN 1 ELSE 0 END)          AS accepte,
                SUM(CASE WHEN resultat = 'rejete'  THEN 1 ELSE 0 END)          AS rejete
             FROM VERIFICATION
             WHERE id_agent = :id_agent
               AND date_verif >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
             GROUP BY DATE_FORMAT(date_verif, '%Y-%m')
             ORDER BY month ASC"
        );
        $stmt->bindParam(':id_agent', $id_agent, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get all verifications for admin (US-18)
    public function getAll() {
        $stmt = $this->conn->prepare(
            "SELECT v.*,
                    d.montant,
                    donor.nom  AS donateur_nom,
                    agent.nom  AS agent_nom
             FROM VERIFICATION v
             JOIN DON d              ON v.id_don   = d.id_don
             JOIN UTILISATEUR donor  ON d.id_user  = donor.id_user
             JOIN UTILISATEUR agent  ON v.id_agent = agent.id_user
             ORDER BY v.date_verif DESC"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
