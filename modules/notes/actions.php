<?php
// modules/notes/actions.php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_auth();

// Vérifier que seul un admin ou enseignant peut faire des actions
if ($_SESSION['user_role'] !== 'super_admin' && $_SESSION['user_role'] !== 'admin' && $_SESSION['user_role'] !== 'enseignant') {
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

// Récupérer les infos de la note pour le log
$stmt = $conn->prepare("
    SELECT n.matiere, n.note, e.nom as nom_eleve, e.prenom as prenom_eleve 
    FROM notes n 
    JOIN eleves e ON n.eleve_id = e.id 
    WHERE n.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$note = $stmt->get_result()->fetch_assoc();

if (!$note) {
    header("Location: list.php?message=Note non trouvée&message_type=error");
    exit();
}

try {
    $conn->begin_transaction();
    
    switch ($action) {
        case 'delete':
            // Vérifier si l'utilisateur a le droit de supprimer
            // Un enseignant ne peut supprimer que ses propres notes
            if ($_SESSION['user_role'] === 'enseignant') {
                $check_ownership = $conn->prepare("SELECT id FROM notes WHERE id = ? AND enseignant_id = ?");
                $check_ownership->bind_param("ii", $id, $_SESSION['user_id']);
                $check_ownership->execute();
                if ($check_ownership->get_result()->num_rows === 0) {
                    header("Location: list.php?message=Vous ne pouvez supprimer que vos propres notes&message_type=error");
                    exit();
                }
            }
            
            // Supprimer la note
            $stmt = $conn->prepare("DELETE FROM notes WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            
            $log_details = "Suppression de note: {$note['nom_eleve']} {$note['prenom_eleve']} - {$note['matiere']} - {$note['note']}/20";
            break;
    }
    
    // Journaliser l'action
    log_activity($_SESSION['user_id'], strtoupper($action), 'notes', $id, $log_details);
    
    $conn->commit();
    
    header("Location: list.php?message=Note supprimée avec succès&message_type=success");
    exit();
    
} catch (Exception $e) {
    $conn->rollback();
    header("Location: list.php?message=Erreur: " . urlencode($e->getMessage()) . "&message_type=error");
    exit();
}
?>