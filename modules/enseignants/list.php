<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_auth();

$page_title = "Gestion des enseignants";
$current_page = 'enseignants';

// Connexion à la base de données
$db = Database::getInstance();
$conn = $db->getConnection();

// Gestion des paramètres
$search = $_GET['search'] ?? '';
$matiere = $_GET['matiere'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Construire la requête
$where = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where[] = "(nom LIKE ? OR prenom LIKE ? OR matricule LIKE ? OR email LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $types .= 'ssss';
}

if (!empty($matiere)) {
    $where[] = "matiere LIKE ?";
    $params[] = "%$matiere%";
    $types .= 's';
}

$where_clause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

// Récupérer les enseignants
$query = "SELECT * FROM enseignants $where_clause ORDER BY nom, prenom LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($query);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Compter le total
$count_query = "SELECT COUNT(*) as total FROM enseignants $where_clause";
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

// Récupérer les matières uniques pour le filtre
$matieres_result = $conn->query("SELECT DISTINCT matiere FROM enseignants WHERE matiere != '' ORDER BY matiere");

// Messages
$message = $_GET['message'] ?? '';
$message_type = $_GET['message_type'] ?? '';

include '../../includes/header.php';
?>

<div class="mb-8">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Gestion des enseignants</h1>
            <p class="text-gray-600 mt-2">Gérez le personnel enseignant de l'établissement</p>
        </div>
        <a href="add.php" class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg font-medium hover:from-blue-700 hover:to-purple-700 transition shadow-md">
            <i class="fas fa-plus mr-2"></i>
            Nouvel enseignant
        </a>
    </div>
</div>

<?php if ($message): ?>
<div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'; ?>">
    <div class="flex items-center">
        <div class="flex-shrink-0">
            <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-red-500'; ?>"></i>
        </div>
        <div class="ml-3">
            <p class="text-sm font-medium <?php echo $message_type === 'success' ? 'text-green-800' : 'text-red-800'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Filtres et recherche -->
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <form method="GET" action="" class="space-y-4 md:space-y-0 md:grid md:grid-cols-3 md:gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Rechercher</label>
            <div class="relative">
                <input type="text" 
                       name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Nom, prénom, matricule..." 
                       class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
            </div>
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Filtrer par matière</label>
            <select name="matiere" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">Toutes les matières</option>
                <?php while ($row = $matieres_result->fetch_assoc()): ?>
                <option value="<?php echo htmlspecialchars($row['matiere']); ?>" <?php echo $matiere == $row['matiere'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($row['matiere']); ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div class="flex items-end">
            <button type="submit" class="w-full md:w-auto px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">
                <i class="fas fa-filter mr-2"></i>Filtrer
            </button>
            <?php if ($search || $matiere): ?>
            <a href="list.php" class="ml-2 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                <i class="fas fa-times mr-2"></i>Réinitialiser
            </a>
            <?php endif; ?>
        </div>
    </form>
    
    <div class="mt-4 pt-4 border-t border-gray-200">
        <div class="text-sm text-gray-600">
            <i class="fas fa-chalkboard-teacher mr-1"></i>
            <?php echo $total_rows; ?> enseignant<?php echo $total_rows > 1 ? 's' : ''; ?> trouvé<?php echo $total_rows > 1 ? 's' : ''; ?>
        </div>
    </div>
</div>

<!-- Tableau des enseignants -->
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Enseignant
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Matière
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Spécialité
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Contact
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($enseignant = $result->fetch_assoc()): ?>
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10 bg-gradient-to-br from-green-100 to-blue-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-chalkboard-teacher text-green-600"></i>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($enseignant['nom'] . ' ' . $enseignant['prenom']); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo htmlspecialchars($enseignant['matricule']); ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                <?php echo htmlspecialchars($enseignant['matiere']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            <?php echo htmlspecialchars($enseignant['specialite'] ?? 'N/A'); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($enseignant['email']); ?></div>
                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($enseignant['telephone'] ?? 'N/A'); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                <a href="view.php?id=<?php echo $enseignant['id']; ?>" 
                                   class="text-blue-600 hover:text-blue-900 p-1 hover:bg-blue-50 rounded"
                                   title="Voir">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit.php?id=<?php echo $enseignant['id']; ?>" 
                                   class="text-green-600 hover:text-green-900 p-1 hover:bg-green-50 rounded"
                                   title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button onclick="confirmDelete(<?php echo $enseignant['id']; ?>, '<?php echo htmlspecialchars(addslashes($enseignant['nom'] . ' ' . $enseignant['prenom'])); ?>')" 
                                        class="text-red-600 hover:text-red-900 p-1 hover:bg-red-50 rounded"
                                        title="Supprimer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center">
                        <div class="text-gray-400 mb-4">
                            <i class="fas fa-chalkboard-teacher text-4xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Aucun enseignant trouvé</h3>
                        <p class="text-gray-500 mb-4">
                            <?php echo $search || $matiere ? 'Aucun enseignant ne correspond à vos critères.' : 'Commencez par ajouter votre premier enseignant.'; ?>
                        </p>
                        <?php if (!($search || $matiere)): ?>
                        <a href="add.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i>
                            Ajouter un enseignant
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination (identique à celle des élèves) -->
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

<script>
function confirmDelete(id, name) {
    if (confirm(`Êtes-vous sûr de vouloir supprimer l'enseignant "${name}" ?`)) {
        window.location.href = `actions.php?action=delete&id=${id}`;
    }
}
</script>

<?php include '../../includes/footer.php'; ?>