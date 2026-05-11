<?php
require_once __DIR__ . '/../models/user.php';

class AuthController {
    private $userModel;

    public function __construct() {
        $this->userModel = new User();
    }

    // US-01: Visitor registers as donor
    public function register() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nom      = trim($_POST['nom']);
            $email    = trim($_POST['email']);
            $password = $_POST['mot_de_passe'];

            if (empty($nom) || empty($email) || empty($password)) {
                header('Location: index.php?action=register&error=Tous les champs sont obligatoires.');
                return;
            }

            if ($this->userModel->register($nom, $email, $password, 'donateur')) {
                header('Location: index.php?action=login&success=1');
            } else {
                header('Location: index.php?action=register&error=Cet email est déjà utilisé.');
            }
        } else {
            require_once __DIR__ . '/../views/auth/register.php';
        }
    }

    // US-02: Any user logs in
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email    = trim($_POST['email']);
            $password = $_POST['mot_de_passe'];

            $user = $this->userModel->login($email, $password);
            if ($user) {
                $_SESSION['user_id']   = $user['id_user'];
                $_SESSION['user_name'] = $user['nom'];
                $_SESSION['user_role'] = $user['role'];

                if ($user['role'] === 'admin') {
                    header('Location: index.php?action=admin_dashboard');
                } elseif ($user['role'] === 'agent') {
                    header('Location: index.php?action=agent_dashboard');
                } else {
                    header('Location: index.php?action=user_dashboard');
                }
            } else {
                header('Location: index.php?action=login&error=1');
            }
        } else {
            require_once __DIR__ . '/../views/auth/login.php';
        }
    }

    // US-03: Logout
    public function logout() {
        session_destroy();
        header('Location: index.php?action=login');
    }
}
