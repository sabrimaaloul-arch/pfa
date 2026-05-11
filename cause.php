<?php
require_once __DIR__ . '/../config/database.php';

class Cause {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Get all causes (US-05)
    public function getAllCauses() {
        $stmt = $this->conn->prepare(
            "SELECT * FROM CAUSE ORDER BY nom_cause"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Search causes by keyword (US-06)
    public function searchByKeyword($keyword) {
        $like = '%' . $keyword . '%';
        $stmt = $this->conn->prepare(
            "SELECT * FROM CAUSE
             WHERE nom_cause LIKE :kw OR description LIKE :kw
             ORDER BY nom_cause"
        );
        $stmt->bindParam(':kw', $like);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get cause by code
    public function getCauseById($code) {
        $stmt = $this->conn->prepare(
            "SELECT * FROM CAUSE WHERE code_cause = :code"
        );
        $stmt->bindParam(':code', $code);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Add cause (US-07)
    public function create($code, $nom, $description, $image_url = null, $objectif = null) {
        $stmt = $this->conn->prepare(
            "INSERT INTO CAUSE (code_cause, nom_cause, description, image_url, objectif)
             VALUES (:code, :nom, :description, :image_url, :objectif)"
        );
        $stmt->bindParam(':code',        $code);
        $stmt->bindParam(':nom',         $nom);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':image_url',   $image_url);
        $stmt->bindParam(':objectif',    $objectif);
        return $stmt->execute();
    }

    // Edit cause (US-08)
    public function update($code, $nom, $description, $image_url = null, $objectif = null) {
        if ($image_url !== null) {
            $stmt = $this->conn->prepare(
                "UPDATE CAUSE SET nom_cause = :nom, description = :description,
                                  image_url = :image_url, objectif = :objectif
                 WHERE code_cause = :code"
            );
            $stmt->bindParam(':image_url', $image_url);
        } else {
            $stmt = $this->conn->prepare(
                "UPDATE CAUSE SET nom_cause = :nom, description = :description,
                                  objectif = :objectif
                 WHERE code_cause = :code"
            );
        }
        $stmt->bindParam(':nom',         $nom);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':objectif',    $objectif);
        $stmt->bindParam(':code',        $code);
        return $stmt->execute();
    }

    // Remove cause image
    public function removeImage($code) {
        $stmt = $this->conn->prepare(
            "UPDATE CAUSE SET image_url = NULL WHERE code_cause = :code"
        );
        $stmt->bindParam(':code', $code);
        return $stmt->execute();
    }

    // Delete cause (US-09)
    public function delete($code) {
        $stmt = $this->conn->prepare(
            "DELETE FROM CAUSE WHERE code_cause = :code"
        );
        $stmt->bindParam(':code', $code);
        return $stmt->execute();
    }

    // Stats: total donated per cause (US-14, US-21)
    public function getDonationStats() {
        $stmt = $this->conn->prepare(
            "SELECT c.code_cause, c.nom_cause, c.image_url, c.objectif,
                    COALESCE(SUM(d.montant), 0) AS total_montant,
                    COUNT(d.id_don) AS nb_dons
             FROM CAUSE c
             LEFT JOIN DON d ON c.code_cause = d.code_cause AND d.statut = 'accepte'
             GROUP BY c.code_cause, c.nom_cause, c.image_url, c.objectif
             ORDER BY total_montant DESC"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
