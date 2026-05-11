<?php
require_once __DIR__ . '/../models/user.php';
require_once __DIR__ . '/../models/cause.php';
require_once __DIR__ . '/../models/donation.php';
require_once __DIR__ . '/../models/Verification.php';

class AdminController {
    private $userModel;
    private $causeModel;
    private $donationModel;
    private $verificationModel;

    public function __construct() {
        $this->userModel         = new User();
        $this->causeModel        = new Cause();
        $this->donationModel     = new Donation();
        $this->verificationModel = new Verification();
    }

    // US-21: Admin dashboard with global stats
    public function dashboard() {
        $this->requireAdmin();
        $stats = $this->donationModel->getGlobalStats();
        require_once __DIR__ . '/../views/admin/dashboard.php';
    }

    // US-05 to US-09: Manage causes
    public function causes() {
        $this->requireAdmin();
        $causes = $this->causeModel->getAllCauses();
        require_once __DIR__ . '/../views/admin/causes.php';
    }

    public function addCause() {
        $this->requireAdmin();
        $code        = trim($_POST['code_cause']);
        $nom         = trim($_POST['nom_cause']);
        $description = trim($_POST['description']);
        $objectif    = !empty($_POST['objectif']) ? (float)$_POST['objectif'] : null;
        $image_url   = null;

        if (!empty($_FILES['image']['name'])) {
            $image_url = $this->handleCauseImageUpload($_FILES['image'], $code);
            if ($image_url === false) {
                header('Location: index.php?action=admin_causes&error=Image invalide. Formats acceptés : JPG, PNG, WEBP. Taille max : 2 Mo.');
                return;
            }
        }

        if ($this->causeModel->create($code, $nom, $description, $image_url, $objectif)) {
            header('Location: index.php?action=admin_causes&success=1');
        } else {
            if ($image_url && file_exists(__DIR__ . '/../' . $image_url)) {
                unlink(__DIR__ . '/../' . $image_url);
            }
            header('Location: index.php?action=admin_causes&error=Erreur lors de l\'ajout.');
        }
    }

    public function editCause() {
        $this->requireAdmin();
        $code        = trim($_POST['code_cause']);
        $nom         = trim($_POST['nom_cause']);
        $description = trim($_POST['description']);
        $objectif    = !empty($_POST['objectif']) ? (float)$_POST['objectif'] : null;
        $image_url   = null;

        if (!empty($_POST['remove_image'])) {
            $existing = $this->causeModel->getCauseById($code);
            if ($existing && $existing['image_url']) {
                $path = __DIR__ . '/../' . $existing['image_url'];
                if (file_exists($path)) unlink($path);
            }
            $this->causeModel->removeImage($code);
        }

        if (!empty($_FILES['image']['name'])) {
            $existing = $this->causeModel->getCauseById($code);
            if ($existing && $existing['image_url']) {
                $path = __DIR__ . '/../' . $existing['image_url'];
                if (file_exists($path)) unlink($path);
            }
            $image_url = $this->handleCauseImageUpload($_FILES['image'], $code);
            if ($image_url === false) {
                header('Location: index.php?action=admin_causes&error=Image invalide. Formats acceptés : JPG, PNG, WEBP. Taille max : 2 Mo.');
                return;
            }
        }

        if ($this->causeModel->update($code, $nom, $description, $image_url, $objectif)) {
            header('Location: index.php?action=admin_causes&success=1');
        } else {
            header('Location: index.php?action=admin_causes&error=Erreur lors de la modification.');
        }
    }

    /**
     * Validates and moves an uploaded cause image.
     * Returns the relative path on success, false on failure.
     */
    private function handleCauseImageUpload(array $file, string $code): string|false {
        $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize     = 2 * 1024 * 1024; // 2 MB

        if ($file['error'] !== UPLOAD_ERR_OK)  return false;
        if ($file['size']  > $maxSize)          return false;

        // Validate MIME type from actual file content (not just extension)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $allowedMime))     return false;

        $ext      = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];
        $filename = 'cause_' . preg_replace('/[^a-z0-9_-]/i', '_', strtolower($code)) . '_' . time() . '.' . $ext;
        $destDir  = __DIR__ . '/../uploads/causes/';
        $destPath = $destDir . $filename;

        if (!is_dir($destDir)) mkdir($destDir, 0755, true);
        if (!move_uploaded_file($file['tmp_name'], $destPath)) return false;

        return 'uploads/causes/' . $filename;
    }

    public function deleteCause() {
        $this->requireAdmin();
        $code = $_GET['code'] ?? null;
        if ($code) {
            $this->causeModel->delete($code);
        }
        header('Location: index.php?action=admin_causes&success=1');
    }

    // US-12 & US-13: Manage donations
    public function donations() {
        $this->requireAdmin();
        $causes = $this->causeModel->getAllCauses();

        $hasFilter = !empty($_GET['montant_min']) || !empty($_GET['montant_max'])
                  || !empty($_GET['date_debut'])  || !empty($_GET['date_fin'])
                  || !empty($_GET['code_cause'])  || !empty($_GET['statut']);

        if ($hasFilter) {
            $filters = [
                'montant_min' => $_GET['montant_min'] ?? null,
                'montant_max' => $_GET['montant_max'] ?? null,
                'date_debut'  => $_GET['date_debut']  ?? null,
                'date_fin'    => $_GET['date_fin']    ?? null,
                'code_cause'  => $_GET['code_cause']  ?? null,
                'statut'      => $_GET['statut']      ?? null,
            ];
            $donations = $this->donationModel->searchDonations($filters);
        } else {
            $donations = $this->donationModel->getAllDonations();
        }

        require_once __DIR__ . '/../views/admin/donations.php';
    }

    public function deleteDonation() {
        $this->requireAdmin();
        $id = $_GET['id'] ?? null;
        if ($id) {
            $this->donationModel->delete($id);
        }
        header('Location: index.php?action=admin_donations&success=1');
    }

    // US-18: All transactions (verifications)
    public function transactions() {
        $this->requireAdmin();
        $transactions = $this->verificationModel->getAll();
        require_once __DIR__ . '/../views/admin/transactions.php';
    }

    // US-19 & US-20: Manage users
    public function users() {
        $this->requireAdmin();
        $users = $this->userModel->getAllUsers();
        require_once __DIR__ . '/../views/admin/users.php';
    }

    public function createUser() {
        $this->requireAdmin();
        $nom      = trim($_POST['nom']);
        $email    = trim($_POST['email']);
        $password = $_POST['mot_de_passe'];
        $role     = $_POST['role'];

        if (!in_array($role, ['agent', 'admin', 'donateur'])) {
            header('Location: index.php?action=admin_users&error=Rôle invalide.');
            return;
        }

        if ($this->userModel->createByAdmin($nom, $email, $password, $role)) {
            header('Location: index.php?action=admin_users&success=1');
        } else {
            header('Location: index.php?action=admin_users&error=Cet email est déjà utilisé.');
        }
    }

    public function deleteUser() {
        $this->requireAdmin();
        $id = $_GET['id'] ?? null;
        if ($id && $id != $_SESSION['user_id']) {
            $this->userModel->delete($id);
        }
        header('Location: index.php?action=admin_users&success=1');
    }

    private function requireAdmin() {
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
            header('Location: index.php?action=login');
            exit;
        }
    }
}
