<?php
require_once __DIR__ . '/../models/donation.php';
require_once __DIR__ . '/../models/cause.php';

class DonationController {
    private $donationModel;
    private $causeModel;

    public function __construct() {
        $this->donationModel = new Donation();
        $this->causeModel    = new Cause();
    }

    // US-10: Donor makes a donation
    public function create() {
        $this->requireRole('donateur');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $montant    = $_POST['montant'];
            $code_cause = $_POST['code_cause'];
            $id_user    = $_SESSION['user_id'];
            $anonyme    = isset($_POST['anonyme']) ? 1 : 0;

            if ($montant <= 0 || empty($code_cause)) {
                header('Location: index.php?action=make_donation&error=1');
                return;
            }

            if ($this->donationModel->create($montant, $id_user, $code_cause, $anonyme)) {
                header('Location: index.php?action=user_dashboard&success=1');
            } else {
                header('Location: index.php?action=make_donation&error=1');
            }
        } else {
            $causes = $this->causeModel->getAllCauses();
            require_once __DIR__ . '/../views/user/make-donation.php';
        }
    }

    // US-11: Donor views their donations
    public function userDashboard() {
        $this->requireRole('donateur');
        $donations = $this->donationModel->getUserDonations($_SESSION['user_id']);
        require_once __DIR__ . '/../views/user/dashboard.php';
    }

    // Home page: public landing page with causes and accepted donations
    public function home() {
        $causes    = $this->causeModel->getAllCauses();
        $stats     = $this->causeModel->getDonationStats();
        $donations = $this->donationModel->getRecentAcceptedDonations(10);
        require_once __DIR__ . '/../views/public/home.php';
    }

    // US-05 & US-06: Public causes list with optional keyword search
    public function causes() {
        $keyword = $_GET['keyword'] ?? '';
        if (!empty($keyword)) {
            $causes = $this->causeModel->searchByKeyword($keyword);
        } else {
            $causes = $this->causeModel->getAllCauses();
        }
        // Merge donation totals so the goal progress bar works on the causes page
        $statsRaw = $this->causeModel->getDonationStats();
        $statsMap = [];
        foreach ($statsRaw as $s) { $statsMap[$s['code_cause']] = $s['total_montant']; }
        foreach ($causes as &$c) {
            $c['total_montant'] = $statsMap[$c['code_cause']] ?? 0;
        }
        unset($c);
        require_once __DIR__ . '/../views/public/causes.php';
    }

    // US-14: Public donation stats per cause
    public function stats() {
        $stats = $this->causeModel->getDonationStats();
        require_once __DIR__ . '/../views/public/stats.php';
    }

    // Helper: redirect if user doesn't have the required role
    private function requireRole($role) {
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== $role) {
            header('Location: index.php?action=login');
            exit;
        }
    }
}
