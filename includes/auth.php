<?php
// includes/auth.php
require_once __DIR__ . '/../config/config.php';

class Auth {
    private $conn;
    
    public function __construct() {
        $db = Database::getInstance();
        $this->conn = $db->getConnection();
    }
    
    public function login($username, $password) {
        $stmt = $this->conn->prepare("SELECT id, username, password, role, full_name, is_active 
                                     FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (!$user['is_active']) {
                return ['success' => false, 'message' => 'Compte désactivé'];
            }
            
            if (password_verify($password, $user['password'])) {
                // Mettre à jour la dernière connexion
                $update_stmt = $this->conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $update_stmt->bind_param("i", $user['id']);
                $update_stmt->execute();
                
                // Créer la session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                
                // Journaliser la connexion
                log_activity($user['id'], 'LOGIN', 'users', $user['id'], 'Connexion réussie');
                
                return ['success' => true];
            }
        }
        
        return ['success' => false, 'message' => 'Identifiants incorrects'];
    }
    
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            log_activity($_SESSION['user_id'], 'LOGOUT', 'users', $_SESSION['user_id'], 'Déconnexion');
            session_destroy();
        }
    }
    
    public function createUser($data, $created_by) {
        // Vérifier si l'utilisateur existe déjà
        $check_stmt = $this->conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check_stmt->bind_param("ss", $data['username'], $data['email']);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            return ['success' => false, 'message' => 'Nom d\'utilisateur ou email déjà utilisé'];
        }
        
        // Créer l'utilisateur
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
        
        $stmt = $this->conn->prepare("INSERT INTO users (username, password, email, role, full_name, created_by) 
                                     VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssi", 
            $data['username'], 
            $hashed_password, 
            $data['email'], 
            $data['role'], 
            $data['full_name'], 
            $created_by
        );
        
        if ($stmt->execute()) {
            $user_id = $this->conn->insert_id;
            log_activity($created_by, 'CREATE_USER', 'users', $user_id, 
                        "Création de l'utilisateur: {$data['username']}");
            return ['success' => true, 'user_id' => $user_id];
        }
        
        return ['success' => false, 'message' => 'Erreur lors de la création'];
    }
}
?>