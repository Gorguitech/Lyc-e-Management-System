<?php
// includes/ajax.php
require_once '../config/config.php';
require_once 'auth.php';

// Démarrer la session si pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier l'authentification
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit();
}

// Connexion à la base de données
$db = Database::getInstance();
$conn = $db->getConnection();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_eleve_info':
        $id = intval($_GET['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("
                SELECT e.*, c.nom_classe, 
                       (SELECT AVG(note) FROM notes WHERE eleve_id = e.id) as moyenne
                FROM eleves e 
                LEFT JOIN classes c ON e.classe_id = c.id 
                WHERE e.id = ?
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $eleve = $result->fetch_assoc();
            
            if ($eleve) {
                echo json_encode(['success' => true, 'data' => $eleve]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Élève non trouvé']);
            }
        }
        break;
        
    case 'get_enseignant_info':
        $id = intval($_GET['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("SELECT * FROM enseignants WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $enseignant = $result->fetch_assoc();
            
            if ($enseignant) {
                echo json_encode(['success' => true, 'data' => $enseignant]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Enseignant non trouvé']);
            }
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
        break;
}
?>