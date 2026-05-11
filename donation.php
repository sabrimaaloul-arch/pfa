<?php
require_once __DIR__ . '/../config/database.php';

class Donation {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Donor makes a donation (US-10)
    public function create($montant, $id_user, $code_cause, $anonyme = 0) {
        $stmt = $this->conn->prepare(
            "INSERT INTO DON (montant, date_don, statut, anonyme, id_user, code_cause)
             VALUES (:montant, CURDATE(), 'en_attente', :anonyme, :id_user, :code_cause)"
        );
        $stmt->bindValue(':montant',    (float)$montant);
        $stmt->bindValue(':anonyme',    (int)$anonyme,    PDO::PARAM_INT);
        $stmt->bindValue(':id_user',    (int)$id_user,    PDO::PARAM_INT);
        $stmt->bindValue(':code_cause', (string)$code_cause, PDO::PARAM_STR);
        return $stmt->execute();
    }

    // Get donations for one user (US-11)
    public function getUserDonations($user_id) {
        $stmt = $this->conn->prepare(
            "SELECT d.*, c.nom_cause
             FROM DON d
             JOIN CAUSE c ON d.code_cause = c.code_cause
             WHERE d.id_user = :user_id
             ORDER BY d.date_don DESC"
        );
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get all donations (admin, US-12)
    public function getAllDonations() {
        $stmt = $this->conn->prepare(
            "SELECT d.*, u.nom AS donateur_nom, c.nom_cause
             FROM DON d
             JOIN UTILISATEUR u ON d.id_user   = u.id_user
             JOIN CAUSE c       ON d.code_cause = c.code_cause
             ORDER BY d.date_don DESC"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get only pending donations (agent, US-15)
    public function getPendingDonations() {
        $stmt = $this->conn->prepare(
            "SELECT d.*, u.nom AS donateur_nom, c.nom_cause
             FROM DON d
             JOIN UTILISATEUR u ON d.id_user   = u.id_user
             JOIN CAUSE c       ON d.code_cause = c.code_cause
             WHERE d.statut = 'en_attente'
             ORDER BY d.date_don ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get one donation by ID
    public function getDonationById($id) {
        $stmt = $this->conn->prepare(
            "SELECT d.*, u.nom AS donateur_nom, c.nom_cause
             FROM DON d
             JOIN UTILISATEUR u ON d.id_user   = u.id_user
             JOIN CAUSE c       ON d.code_cause = c.code_cause
             WHERE d.id_don = :id"
        );
        $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Search/filter donations (US-12)
    public function searchDonations($filters) {
        $query = "SELECT d.*, u.nom AS donateur_nom, c.nom_cause
                  FROM DON d
                  JOIN UTILISATEUR u ON d.id_user   = u.id_user
                  JOIN CAUSE c       ON d.code_cause = c.code_cause
                  WHERE 1=1";

        if (!empty($filters['montant_min']))  $query .= " AND d.montant   >= :montant_min";
        if (!empty($filters['montant_max']))  $query .= " AND d.montant   <= :montant_max";
        if (!empty($filters['date_debut']))   $query .= " AND d.date_don  >= :date_debut";
        if (!empty($filters['date_fin']))     $query .= " AND d.date_don  <= :date_fin";
        if (!empty($filters['code_cause']))   $query .= " AND d.code_cause = :code_cause";
        if (!empty($filters['statut']))       $query .= " AND d.statut    = :statut";

        $query .= " ORDER BY d.date_don DESC";

        $stmt = $this->conn->prepare($query);

        if (!empty($filters['montant_min']))  $stmt->bindValue(':montant_min', $filters['montant_min']);
        if (!empty($filters['montant_max']))  $stmt->bindValue(':montant_max', $filters['montant_max']);
        if (!empty($filters['date_debut']))   $stmt->bindValue(':date_debut',  $filters['date_debut']);
        if (!empty($filters['date_fin']))     $stmt->bindValue(':date_fin',    $filters['date_fin']);
        if (!empty($filters['code_cause']))   $stmt->bindValue(':code_cause',  $filters['code_cause']);
        if (!empty($filters['statut']))       $stmt->bindValue(':statut',      $filters['statut']);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Update donation status
    public function updateStatus($id, $statut) {
        $stmt = $this->conn->prepare(
            "UPDATE DON SET statut = :statut WHERE id_don = :id"
        );
        $stmt->bindParam(':statut', $statut);
        $stmt->bindParam(':id',     $id);
        return $stmt->execute();
    }

    // Delete donation (US-13)
    public function delete($id) {
        $stmt = $this->conn->prepare(
            "DELETE FROM DON WHERE id_don = :id"
        );
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    // Recent accepted donations for home page
    public function getRecentAcceptedDonations($limit = 10) {
        $limit = intval($limit);
        $stmt = $this->conn->prepare(
            "SELECT d.montant, d.date_don, d.anonyme, c.nom_cause,
                    CASE WHEN d.anonyme = 1 THEN 'Anonyme' ELSE u.nom END AS donateur_nom
             FROM DON d
             JOIN UTILISATEUR u ON d.id_user   = u.id_user
             JOIN CAUSE c       ON d.code_cause = c.code_cause
             WHERE d.statut = 'accepte'
             ORDER BY d.date_don DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Global stats for admin dashboard (US-21)
    public function getGlobalStats() {
        $stmt = $this->conn->prepare(
            "SELECT
                (SELECT COUNT(*) FROM UTILISATEUR)                          AS total_users,
                COUNT(*)                                                    AS total_dons,
                SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END)     AS dons_en_attente,
                COALESCE(SUM(CASE WHEN statut = 'accepte' THEN montant END), 0) AS montant_total
             FROM DON"
        );
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
