<?php
require_once __DIR__ . '/../config/database.php';

class User {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Register a new donor (US-01)
    public function register($nom, $email, $password, $role = 'donateur') {
        // Check if email already exists
        $check = $this->conn->prepare("SELECT id_user FROM UTILISATEUR WHERE email = :email");
        $check->bindParam(':email', $email);
        $check->execute();
        if ($check->fetch()) return false; // Email taken

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->conn->prepare(
            "INSERT INTO UTILISATEUR (nom, email, mot_de_passe, role)
             VALUES (:nom, :email, :mot_de_passe, :role)"
        );
        $stmt->bindParam(':nom',          $nom);
        $stmt->bindParam(':email',        $email);
        $stmt->bindParam(':mot_de_passe', $hashed);
        $stmt->bindParam(':role',         $role);
        return $stmt->execute();
    }

    // Login (US-02)
    public function login($email, $password) {
        $stmt = $this->conn->prepare(
            "SELECT * FROM UTILISATEUR WHERE email = :email"
        );
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['mot_de_passe'])) {
            return $user;
        }
        return false;
    }

    // Get all users (US-19)
    public function getAllUsers() {
        $stmt = $this->conn->prepare(
            "SELECT * FROM UTILISATEUR ORDER BY nom"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get user by ID
    public function getUserById($id) {
        $stmt = $this->conn->prepare(
            "SELECT * FROM UTILISATEUR WHERE id_user = :id"
        );
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Admin creates agent or admin account (US-04, US-20)
    public function createByAdmin($nom, $email, $password, $role) {
        return $this->register($nom, $email, $password, $role);
    }

    // Delete user (US-19)
    public function delete($id) {
        $stmt = $this->conn->prepare(
            "DELETE FROM UTILISATEUR WHERE id_user = :id"
        );
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
