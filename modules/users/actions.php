<?php
// modules/users/actions.php
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

// Récupérer les infos de l'utilisateur pour le log
$stmt = $conn->prepare("SELECT username, role, is_active FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header("Location: list.php?message=Utilisateur non trouvé&message_type=error");
    exit();
}

// Vérifications de sécurité
if ($id == $_SESSION['user_id']) {
    header("Location: list.php?message=Vous ne pouvez pas effectuer cette action sur votre propre compte&message_type=error");
    exit();
}

// Empêcher un admin simple de modifier un super_admin
if ($_SESSION['user_role'] !== 'super_admin' && $user['role'] === 'super_admin') {
    header("Location: list.php?message=Vous ne pouvez pas modifier un Super Admin&message_type=error");
    exit();
}

try {
    $conn->begin_transaction();
    
    switch ($action) {
        case 'delete':
            // Vérifier si l'utilisateur a des activités
            $check_activities = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM audit_log 
                WHERE user_id = ? 
                OR (table_name IN ('eleves', 'enseignants', 'classes', 'notes') AND record_id IN (
                    SELECT id FROM eleves WHERE created_by = ?
                    UNION SELECT id FROM enseignants WHERE created_by = ?
                    UNION SELECT id FROM classes WHERE created_by = ?
                    UNION SELECT id FROM notes WHERE created_by = ?
                ))
            ");
            $check_activities->bind_param("iiiii", $id, $id, $id, $id, $id);
            $check_activities->execute();
            $activities_count = $check_activities->get_result()->fetch_assoc()['count'];
            
            if ($activities_count > 0) {
                // Désactiver au lieu de supprimer
                $stmt = $conn->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $log_details = "Désactivation de l'utilisateur: {$user['username']} (activités existantes)";
            } else {
                // Supprimer l'utilisateur
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $log_details = "Suppression de l'utilisateur: {$user['username']}";
            }
            break;
            
        case 'activate':
            if ($user['is_active']) {
                header("Location: list.php?message=L'utilisateur est déjà actif&message_type=error");
                exit();
            }
            $stmt = $conn->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $log_details = "Activation de l'utilisateur: {$user['username']}";
            break;
            
        case 'deactivate':
            if (!$user['is_active']) {
                header("Location: list.php?message=L'utilisateur est déjà inactif&message_type=error");
                exit();
            }
            $stmt = $conn->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $log_details = "Désactivation de l'utilisateur: {$user['username']}";
            break;
    }
    
    // Journaliser l'action
    log_activity($_SESSION['user_id'], strtoupper($action), 'users', $id, $log_details);
    
    $conn->commit();
    
    header("Location: list.php?message=Action effectuée avec succès&message_type=success");
    exit();
    
} catch (Exception $e) {
    $conn->rollback();
    header("Location: list.php?message=Erreur: " . urlencode($e->getMessage()) . "&message_type=error");
    exit();
}
?>