<?php
// export_audit.php
require_once 'config/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

require_auth();

// Vérifier que seul un super_admin peut exporter
if ($_SESSION['user_role'] !== 'super_admin') {
    header("Location: " . BASE_URL . "dashboard.php?message=Accès non autorisé&message_type=error");
    exit();
}

// Connexion à la base de données
$db = Database::getInstance();
$conn = $db->getConnection();

// Récupérer les mêmes filtres que la page audit
$search = $_GET['search'] ?? '';
$action = $_GET['action'] ?? '';
$table_name = $_GET['table_name'] ?? '';
$user_id = $_GET['user_id'] ?? '';
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';

// Construire la requête (identique à audit.php)
$where = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where[] = "(a.details LIKE ? OR u.username LIKE ? OR u.full_name LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $types .= 'sss';
}

if (!empty($action) && $action !== 'all') {
    $where[] = "a.action = ?";
    $params[] = $action;
    $types .= 's';
}

if (!empty($table_name) && $table_name !== 'all') {
    $where[] = "a.table_name = ?";
    $params[] = $table_name;
    $types .= 's';
}

if (!empty($user_id) && is_numeric($user_id)) {
    $where[] = "a.user_id = ?";
    $params[] = $user_id;
    $types .= 'i';
}

if (!empty($date_debut)) {
    $where[] = "DATE(a.created_at) >= ?";
    $params[] = $date_debut;
    $types .= 's';
}

if (!empty($date_fin)) {
    $where[] = "DATE(a.created_at) <= ?";
    $params[] = $date_fin;
    $types .= 's';
}

$where_clause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

// Récupérer TOUS les logs (pas de pagination)
$query = "SELECT a.*, u.username, u.full_name, u.role 
          FROM audit_log a 
          JOIN users u ON a.user_id = u.id 
          $where_clause 
          ORDER BY a.created_at DESC";

$stmt = $conn->prepare($query);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Définir les en-têtes pour le téléchargement CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=audit_log_' . date('Y-m-d_H-i-s') . '.csv');

// Créer le fichier CSV
$output = fopen('php://output', 'w');

// En-têtes CSV
fputcsv($output, [
    'ID',
    'Date et Heure',
    'Utilisateur',
    'Rôle',
    'Action',
    'Table',
    'ID Enregistrement',
    'Détails',
    'Adresse IP'
]);

// Données CSV
while ($log = $result->fetch_assoc()) {
    fputcsv($output, [
        $log['id'],
        $log['created_at'],
        $log['full_name'] . ' (@' . $log['username'] . ')',
        $log['role'],
        $log['action'],
        $log['table_name'],
        $log['record_id'],
        $log['details'],
        $log['ip_address']
    ]);
}

fclose($output);
exit();