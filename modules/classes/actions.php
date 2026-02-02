<?php
// modules/classes/actions.php
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

if (!in_array($action, ['delete']) || !is_numeric($id)) {
    header("Location: list.php?message=Action invalide&message_type=error");
    exit();
}

// Récupérer les infos de la classe pour le log
$stmt = $conn->prepare("SELECT nom_classe, niveau FROM classes WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$classe = $stmt->get_result()->fetch_assoc();

if (!$classe) {
    header("Location: list.php?message=Classe non trouvée&message_type=error");
    exit();
}

try {
    $conn->begin_transaction();
    
    switch ($action) {
        case 'delete':
            // Vérifier si la classe a des élèves
            $check_eleves = $conn->prepare("SELECT COUNT(*) as nb_eleves FROM eleves WHERE classe_id = ?");
            $check_eleves->bind_param("i", $id);
            $check_eleves->execute();
            $eleves_count = $check_eleves->get_result()->fetch_assoc()['nb_eleves'];
            
            if ($eleves_count > 0) {
                // Option 1: Déclasser les élèves (mettre classe_id à NULL)
                $update_eleves = $conn->prepare("UPDATE eleves SET classe_id = NULL WHERE classe_id = ?");
                $update_eleves->bind_param("i", $id);
                $update_eleves->execute();
                
                // Option 2: Empêcher la suppression (décommenter si préférable)
                // header("Location: list.php?message=Impossible de supprimer une classe ayant des élèves&message_type=error");
                // exit();
            }
            
            // Supprimer la classe
            $stmt = $conn->prepare("DELETE FROM classes WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            
            $log_details = "Suppression de la classe: {$classe['nom_classe']} ({$classe['niveau']})";
            break;
    }
    
    // Journaliser l'action
    log_activity($_SESSION['user_id'], strtoupper($action), 'classes', $id, $log_details);
    
    $conn->commit();
    
    header("Location: list.php?message=Action effectuée avec succès&message_type=success");
    exit();
    
} catch (Exception $e) {
    $conn->rollback();
    header("Location: list.php?message=Erreur: " . urlencode($e->getMessage()) . "&message_type=error");
    exit();
}
?>