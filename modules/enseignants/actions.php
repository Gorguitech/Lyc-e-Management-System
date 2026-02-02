<?php
// modules/enseignants/actions.php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_auth();

// Vérifier que seul un admin peut faire des actions
if ($_SESSION['user_role'] !== 'super_admin' && $_SESSION['user_role'] !== 'admin') {
    header("Location: list.php?message=Accès non autorisé&message_type=error");
    exit();
}

// Connexion à la base de données
$db = Database::getInstance();
$conn = $db->getConnection();

// Récupérer l'action
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$id = $_GET['id'] ?? $_POST['id'] ?? 0;

if (!in_array($action, ['delete', 'activate', 'deactivate']) || !is_numeric($id)) {
    header("Location: list.php?message=Action invalide&message_type=error");
    exit();
}

// Récupérer les infos de l'enseignant pour le log
$stmt = $conn->prepare("SELECT nom, prenom, matiere FROM enseignants WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$enseignant = $stmt->get_result()->fetch_assoc();

if (!$enseignant) {
    header("Location: list.php?message=Enseignant non trouvé&message_type=error");
    exit();
}

try {
    $conn->begin_transaction();
    
    switch ($action) {
        case 'delete':
            // Vérifier si l'enseignant a attribué des notes
            $check_notes = $conn->prepare("SELECT COUNT(*) as nb_notes FROM notes WHERE enseignant_id = ?");
            $check_notes->bind_param("i", $id);
            $check_notes->execute();
            $notes_count = $check_notes->get_result()->fetch_assoc()['nb_notes'];
            
            if ($notes_count > 0) {
                // Option 1: Supprimer les notes d'abord OU réassigner à un autre enseignant
                // Pour l'instant, on empêche la suppression
                header("Location: list.php?message=Impossible de supprimer un enseignant ayant attribué des notes&message_type=error");
                exit();
            }
            
            // Supprimer l'enseignant
            $stmt = $conn->prepare("DELETE FROM enseignants WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            
            $log_details = "Suppression de l'enseignant: {$enseignant['nom']} {$enseignant['prenom']} ({$enseignant['matiere']})";
            break;
            
        // Note: Vous pouvez ajouter d'autres actions comme activate/deactivate si vous ajoutez un champ is_active
    }
    
    // Journaliser l'action
    log_activity($_SESSION['user_id'], strtoupper($action), 'enseignants', $id, $log_details);
    
    $conn->commit();
    
    header("Location: list.php?message=Action effectuée avec succès&message_type=success");
    exit();
    
} catch (Exception $e) {
    $conn->rollback();
    header("Location: list.php?message=Erreur: " . urlencode($e->getMessage()) . "&message_type=error");
    exit();
}
?>