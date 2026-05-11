<?php
require_once __DIR__ . '/../models/donation.php';
require_once __DIR__ . '/../models/Verification.php';

class AgentController {
    private $donationModel;
    private $verificationModel;

    public function __construct() {
        $this->donationModel     = new Donation();
        $this->verificationModel = new Verification();
    }

    // US-15: Agent sees pending donations
    public function dashboard() {
        $this->requireAgent();
        $donations    = $this->donationModel->getPendingDonations();
        $agentStats   = $this->verificationModel->getAgentStats($_SESSION['user_id']);
        $monthlyStats = $this->verificationModel->getAgentMonthlyStats($_SESSION['user_id']);
        require_once __DIR__ . '/../views/agent/dashboard.php';
    }

    // US-16: Agent approves or rejects a donation
    public function verify() {
        $this->requireAgent();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id_don   = $_POST['id_don'];
            $decision = $_POST['decision'];
            $raison   = trim($_POST['raison_rejet'] ?? '');
            $id_agent = $_SESSION['user_id'];

            $resultat = ($decision === 'accepter') ? 'accepte' : 'rejete';

            $this->donationModel->updateStatus($id_don, $resultat);

            $this->verificationModel->create(
                $id_don,
                $id_agent,
                $resultat,
                ($resultat === 'rejete' && !empty($raison)) ? $raison : null
            );

            header('Location: index.php?action=agent_dashboard&success=1');
            exit;
        } else {
            $id_don = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if (!$id_don) {
                header('Location: index.php?action=agent_dashboard&error=missing_id');
                exit;
            }
            $donation = $this->donationModel->getDonationById($id_don);
            if (!$donation) {
                header('Location: index.php?action=agent_dashboard&error=not_found');
                exit;
            }
            if ($donation['statut'] !== 'en_attente') {
                header('Location: index.php?action=agent_dashboard&error=already_processed');
                exit;
            }
            require_once __DIR__ . '/../views/agent/verify-donation.php';
        }
    }

    // US-17: Agent views their past verifications
    public function myVerifications() {
        $this->requireAgent();
        $verifications = $this->verificationModel->getByAgent($_SESSION['user_id']);
        require_once __DIR__ . '/../views/agent/my-verifications.php';
    }

    private function requireAgent() {
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'agent') {
            header('Location: index.php?action=login');
            exit;
        }
    }
}
