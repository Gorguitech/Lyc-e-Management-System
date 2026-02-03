<?php
require_once 'config/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

require_auth();

// Vérifier que seul un super_admin peut accéder
if ($_SESSION['user_role'] !== 'super_admin') {
    header("Location: " . BASE_URL . "dashboard.php?message=Accès non autorisé&message_type=error");
    exit();
}

$page_title = "Journal d'audit";
$current_page = 'audit';

// Connexion à la base de données
$db = Database::getInstance();
$conn = $db->getConnection();

// Gestion des paramètres
$search = $_GET['search'] ?? '';
$action = $_GET['action'] ?? '';
$table_name = $_GET['table_name'] ?? '';
$user_id = $_GET['user_id'] ?? '';
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Construire la requête
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

// Récupérer les logs d'audit
$query = "SELECT a.*, u.username, u.full_name, u.role 
          FROM audit_log a 
          JOIN users u ON a.user_id = u.id 
          $where_clause 
          ORDER BY a.created_at DESC 
          LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($query);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Compter le total
$count_query = "SELECT COUNT(*) as total FROM audit_log a JOIN users u ON a.user_id = u.id $where_clause";
if (!empty($where)) {
    $stmt_count = $conn->prepare($count_query);
    if ($types) {
        $count_params = array_slice($params, 0, count($params) - 2);
        $count_types = substr($types, 0, -2);
        $stmt_count->bind_param($count_types, ...$count_params);
    }
    $stmt_count->execute();
    $count_result = $stmt_count->get_result();
} else {
    $count_result = $conn->query($count_query);
}
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Récupérer les données pour les filtres
$actions_result = $conn->query("SELECT DISTINCT action FROM audit_log ORDER BY action");
$tables_result = $conn->query("SELECT DISTINCT table_name FROM audit_log WHERE table_name IS NOT NULL ORDER BY table_name");
$users_result = $conn->query("SELECT id, username, full_name FROM users ORDER BY username");

include 'includes/header.php';
?>

<div class="mb-8">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Journal d'audit</h1>
            <p class="text-gray-600 mt-2">Historique complet des activités du système</p>
        </div>
        <div class="flex space-x-2">
            <button onclick="exportAuditLog()" 
                    class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium">
                <i class="fas fa-file-export mr-2"></i>
                Exporter
            </button>
            <?php if ($search || $action || $table_name || $user_id || $date_debut || $date_fin): ?>
            <a href="audit.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                <i class="fas fa-times mr-2"></i>
                Réinitialiser
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Filtres avancés -->
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <form method="GET" action="" class="space-y-4">
        <!-- Ligne 1 -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Rechercher</label>
                <div class="relative">
                    <input type="text" 
                           name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Détails, utilisateur..." 
                           class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Action</label>
                <select name="action" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="all">Toutes les actions</option>
                    <?php while ($row = $actions_result->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($row['action']); ?>" <?php echo $action == $row['action'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($row['action']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Table</label>
                <select name="table_name" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="all">Toutes les tables</option>
                    <?php while ($row = $tables_result->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($row['table_name']); ?>" <?php echo $table_name == $row['table_name'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($row['table_name']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Utilisateur</label>
                <select name="user_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">Tous les utilisateurs</option>
                    <?php while ($user = $users_result->fetch_assoc()): ?>
                    <option value="<?php echo $user['id']; ?>" <?php echo $user_id == $user['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['username'] . ' (' . $user['full_name'] . ')'); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>
        
        <!-- Ligne 2 -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date début</label>
                <input type="date" 
                       name="date_debut" 
                       value="<?php echo htmlspecialchars($date_debut); ?>" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date fin</label>
                <input type="date" 
                       name="date_fin" 
                       value="<?php echo htmlspecialchars($date_fin); ?>" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            
            <div class="flex items-end">
                <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">
                    <i class="fas fa-filter mr-2"></i>Filtrer
                </button>
            </div>
        </div>
        
        <!-- Résumé -->
        <div class="flex items-center justify-between pt-2">
            <div class="text-sm text-gray-600">
                <i class="fas fa-history mr-1"></i>
                <?php echo $total_rows; ?> événement<?php echo $total_rows > 1 ? 's' : ''; ?> trouvé<?php echo $total_rows > 1 ? 's' : ''; ?>
            </div>
            <div class="text-sm text-gray-500">
                Affichage des 100 derniers jours
            </div>
        </div>
    </form>
</div>

<!-- Statistiques -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <?php
    // Calculer les statistiques
    $stats_query = "SELECT 
                    COUNT(*) as total_events,
                    COUNT(DISTINCT user_id) as unique_users,
                    COUNT(DISTINCT table_name) as tables_touched,
                    COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_events
                    FROM audit_log";
    $stats = $conn->query($stats_query)->fetch_assoc();
    ?>
    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-history text-blue-600"></i>
                </div>
            </div>
            <div class="ml-3">
                <div class="text-2xl font-bold text-gray-800"><?php echo $stats['total_events'] ?? 0; ?></div>
                <div class="text-sm text-gray-600">Événements total</div>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-users text-green-600"></i>
                </div>
            </div>
            <div class="ml-3">
                <div class="text-2xl font-bold text-gray-800"><?php echo $stats['unique_users'] ?? 0; ?></div>
                <div class="text-sm text-gray-600">Utilisateurs actifs</div>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-database text-purple-600"></i>
                </div>
            </div>
            <div class="ml-3">
                <div class="text-2xl font-bold text-gray-800"><?php echo $stats['tables_touched'] ?? 0; ?></div>
                <div class="text-sm text-gray-600">Tables concernées</div>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-calendar-day text-yellow-600"></i>
                </div>
            </div>
            <div class="ml-3">
                <div class="text-2xl font-bold text-gray-800"><?php echo $stats['today_events'] ?? 0; ?></div>
                <div class="text-sm text-gray-600">Aujourd'hui</div>
            </div>
        </div>
    </div>
</div>

<!-- Tableau d'audit -->
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Date & Heure
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Utilisateur
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Action & Table
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Détails
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        IP & ID
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($log = $result->fetch_assoc()): ?>
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <div class="font-medium text-gray-900"><?php echo format_date_fr($log['created_at'], 'short'); ?></div>
                            <div class="text-xs text-gray-400"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-8 w-8 bg-gradient-to-br from-blue-100 to-purple-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-user text-blue-600 text-xs"></i>
                                </div>
                                <div class="ml-3">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($log['full_name']); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        @<?php echo htmlspecialchars($log['username']); ?>
                                        <span class="ml-2 px-1 py-0.5 text-xs rounded 
                                                    <?php echo $log['role'] === 'super_admin' ? 'bg-purple-100 text-purple-800' : 
                                                           ($log['role'] === 'admin' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800'); ?>">
                                            <?php echo $log['role']; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex flex-col space-y-1">
                                <?php 
                                $action_badge = [
                                    'CREATE' => ['bg-green-100', 'text-green-800', 'fas fa-plus-circle'],
                                    'UPDATE' => ['bg-yellow-100', 'text-yellow-800', 'fas fa-edit'],
                                    'DELETE' => ['bg-red-100', 'text-red-800', 'fas fa-trash'],
                                    'LOGIN' => ['bg-blue-100', 'text-blue-800', 'fas fa-sign-in-alt'],
                                    'LOGOUT' => ['bg-gray-100', 'text-gray-800', 'fas fa-sign-out-alt'],
                                    'CREATE_USER' => ['bg-indigo-100', 'text-indigo-800', 'fas fa-user-plus']
                                ];
                                $action_type = $action_badge[$log['action']] ?? ['bg-gray-100', 'text-gray-800', 'fas fa-history'];
                                ?>
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $action_type[0]; ?> <?php echo $action_type[1]; ?> w-fit">
                                    <i class="<?php echo $action_type[2]; ?> mr-1"></i>
                                    <?php echo htmlspecialchars($log['action']); ?>
                                </span>
                                
                                <?php if ($log['table_name']): ?>
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 w-fit">
                                    <i class="fas fa-table mr-1"></i>
                                    <?php echo htmlspecialchars($log['table_name']); ?>
                                    <?php if ($log['record_id']): ?>
                                    <span class="ml-1 text-gray-600">#<?php echo $log['record_id']; ?></span>
                                    <?php endif; ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($log['details']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <div class="text-xs">
                                <div class="font-mono"><?php echo htmlspecialchars($log['ip_address']); ?></div>
                                <div class="text-gray-400">ID: <?php echo $log['id']; ?></div>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center">
                        <div class="text-gray-400 mb-4">
                            <i class="fas fa-history text-4xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Aucun événement d'audit</h3>
                        <p class="text-gray-500">
                            <?php echo $search || $action || $table_name || $user_id || $date_debut || $date_fin ? 
                                'Aucun événement ne correspond à vos critères.' : 
                                'Les activités du système apparaîtront ici.'; ?>
                        </p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
        <div class="flex items-center justify-between">
            <div class="text-sm text-gray-700">
                Page <span class="font-medium"><?php echo $page; ?></span> sur <span class="font-medium"><?php echo $total_pages; ?></span>
            </div>
            <div class="flex space-x-2">
                <?php if ($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                   class="px-3 py-1 border border-gray-300 rounded text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <i class="fas fa-chevron-left mr-1"></i>Précédent
                </a>
                <?php endif; ?>
                
                <?php 
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                
                for ($i = $start; $i <= $end; $i++): 
                ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                   class="px-3 py-1 border rounded text-sm font-medium <?php echo $i == $page ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 text-gray-700 hover:bg-gray-50'; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                   class="px-3 py-1 border border-gray-300 rounded text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Suivant<i class="fas fa-chevron-right ml-1"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Graphique d'activité (optionnel) -->
<div class="mt-8 bg-white rounded-xl shadow-sm p-6">
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-lg font-bold text-gray-800">
            <i class="fas fa-chart-line mr-2"></i>Activité par jour (7 derniers jours)
        </h3>
        <div class="text-sm text-gray-500">
            <i class="fas fa-calendar-alt mr-1"></i>
            Semaine en cours
        </div>
    </div>
    <div class="h-64">
        <canvas id="activityChart"></canvas>
    </div>
</div>

<script>
// Fonction d'export
function exportAuditLog() {
    const params = new URLSearchParams(window.location.search);
    window.open(`export_audit.php?${params.toString()}`, '_blank');
}

// Graphique d'activité
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('activityChart');
    if (ctx) {
        // Données simulées (dans un vrai projet, vous feriez un appel AJAX)
        const days = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
        const activities = [12, 19, 8, 15, 22, 3, 7];
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: days,
                datasets: [{
                    label: 'Événements',
                    data: activities,
                    backgroundColor: 'rgba(59, 130, 246, 0.5)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 5
                        }
                    }
                }
            }
        });
    }
});

// Auto-refresh toutes les 30 secondes (optionnel)
setTimeout(function() {
    if (!document.querySelector('#activityChart') && window.location.search === '') {
        window.location.reload();
    }
}, 30000);
</script>

<?php include 'includes/footer.php'; ?>