<?php
// config/config.php
session_start();

define('APP_NAME', 'Lycée Management System');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/lycee/');

// Configuration de l'environnement
define('ENV', 'development'); // 'production' pour la mise en production

// Chemins
define('ROOT_PATH', dirname(dirname(__FILE__)));
define('INCLUDE_PATH', ROOT_PATH . '/includes');
define('MODULE_PATH', ROOT_PATH . '/modules');
define('ASSET_PATH', BASE_URL . 'assets/');

// Désactiver les erreurs en production
if (ENV === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Fonction de journalisation
function log_activity($user_id, $action, $table, $record_id = null, $details = null) {
    global $conn;
    
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, table_name, record_id, details, ip_address) 
                           VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ississ", $user_id, $action, $table, $record_id, $details, $ip);
    return $stmt->execute();
}

// Fonction de vérification d'authentification
function require_auth() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
        header('Location: ' . BASE_URL . 'login.php');
        exit();
    }
}

// Fonction de vérification de rôle
function require_role($required_role) {
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== $required_role) {
        if ($required_role === 'super_admin' && $_SESSION['user_role'] !== 'super_admin') {
            header('Location: ' . BASE_URL . 'dashboard.php');
            exit();
        }
    }
}

// Fonction pour sécuriser les sorties
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Inclure la connexion à la base de données
require_once 'database.php';
?>