<?php
// test-connection.php
require_once 'config/database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    echo "✅ Connexion à la base de données réussie!<br>";
    
    // Tester les tables
    $tables = ['users', 'classes', 'eleves', 'enseignants', 'notes', 'audit_log'];
    
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            echo "✅ Table '$table' existe<br>";
        } else {
            echo "❌ Table '$table' manquante<br>";
        }
    }
    
    // Vérifier l'utilisateur admin
    $result = $conn->query("SELECT username, email, role FROM users WHERE username = 'admin'");
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        echo "✅ Utilisateur admin trouvé: {$user['username']} ({$user['role']})<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage();
}