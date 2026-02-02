<?php
// modules/notes/list.php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_auth();

$page_title = "Gestion des notes";
$current_page = 'notes';

// Connexion à la base de données
$db = Database::getInstance();
$conn = $db->getConnection();

// Gestion des paramètres
$search = $_GET['search'] ?? '';
$matiere = $_GET['matiere'] ?? '';
$type_note = $_GET['type_note'] ?? '';
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';
$eleve_id = $_GET['eleve_id'] ?? '';
$enseignant_id = $_GET['enseignant_id'] ?? '';

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Construire la requête
$where = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where[] = "(e.nom LIKE ? OR e.prenom LIKE ? OR ens.nom LIKE ? OR ens.prenom LIKE ? OR n.matiere LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
    $types .= 'sssss';
}

if (!empty($matiere)) {
    $where[] = "n.matiere LIKE ?";
    $params[] = "%$matiere%";
    $types .= 's';
}

if (!empty($type_note) && $type_note !== 'all') {
    $where[] = "n.type_note = ?";
    $params[] = $type_note;
    $types .= 's';
}

if (!empty($date_debut)) {
    $where[] = "n.date_note >= ?";
    $params[] = $date_debut;
    $types .= 's';
}

if (!empty($date_fin)) {
    $where[] = "n.date_note <= ?";
    $params[] = $date_fin;
    $types .= 's';
}

if (!empty($eleve_id) && is_numeric($eleve_id)) {
    $where[] = "n.eleve_id = ?";
    $params[] = $eleve_id;
    $types .= 'i';
}

if (!empty($enseignant_id) && is_numeric($enseignant_id)) {
    $where[] = "n.enseignant_id = ?";
    $params[] = $enseignant_id;
    $types .= 'i';
}

$where_clause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

// Récupérer les notes
$query = "SELECT n.*, 
          e.nom as nom_eleve, e.prenom as prenom_eleve, e.matricule,
          ens.nom as nom_enseignant, ens.prenom as prenom_enseignant,
          c.nom_classe
          FROM notes n 
          JOIN eleves e ON n.eleve_id = e.id 
          JOIN enseignants ens ON n.enseignant_id = ens.id 
          LEFT JOIN classes c ON e.classe_id = c.id 
          $where_clause 
          ORDER BY n.date_note DESC, n.created_at DESC 
          LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($query);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Compter le total
$count_query = "SELECT COUNT(*) as total FROM notes n 
                JOIN eleves e ON n.eleve_id = e.id 
                JOIN enseignants ens ON n.enseignant_id = ens.id 
                $where_clause";
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
$matieres_result = $conn->query("SELECT DISTINCT matiere FROM notes WHERE matiere != '' ORDER BY matiere");
$eleves_result = $conn->query("SELECT id, nom, prenom FROM eleves ORDER BY nom, prenom");
$enseignants_result = $conn->query("SELECT id, nom, prenom FROM enseignants ORDER BY nom, prenom");

// Messages
$message = $_GET['message'] ?? '';
$message_type = $_GET['message_type'] ?? '';

include '../../includes/header.php';
?>

<div class="mb-8">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Gestion des notes</h1>
            <p class="text-gray-600 mt-2">Consultez et gérez toutes les notes des élèves</p>
        </div>
        <a href="add.php" class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg font-medium hover:from-blue-700 hover:to-purple-700 transition shadow-md">
            <i class="fas fa-plus mr-2"></i>
            Nouvelle note
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
    <form method="GET" action="" class="space-y-4">
        <!-- Ligne 1 -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Rechercher</label>
                <div class="relative">
                    <input type="text" 
                           name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Élève, enseignant, matière..." 
                           class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Matière</label>
                <select name="matiere" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">Toutes les matières</option>
                    <?php while ($row = $matieres_result->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($row['matiere']); ?>" <?php echo $matiere == $row['matiere'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($row['matiere']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Type de note</label>
                <select name="type_note" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="all">Tous les types</option>
                    <option value="devoir" <?php echo $type_note === 'devoir' ? 'selected' : ''; ?>>Devoir</option>
                    <option value="examen" <?php echo $type_note === 'examen' ? 'selected' : ''; ?>>Examen</option>
                    <option value="participation" <?php echo $type_note === 'participation' ? 'selected' : ''; ?>>Participation</option>
                </select>
            </div>
        </div>
        
        <!-- Ligne 2 -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Élève</label>
                <select name="eleve_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">Tous les élèves</option>
                    <?php while ($eleve = $eleves_result->fetch_assoc()): ?>
                    <option value="<?php echo $eleve['id']; ?>" <?php echo $eleve_id == $eleve['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Enseignant</label>
                <select name="enseignant_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">Tous les enseignants</option>
                    <?php while ($enseignant = $enseignants_result->fetch_assoc()): ?>
                    <option value="<?php echo $enseignant['id']; ?>" <?php echo $enseignant_id == $enseignant['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($enseignant['nom'] . ' ' . $enseignant['prenom']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
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
        </div>
        
        <!-- Actions -->
        <div class="flex items-center justify-between pt-2">
            <div class="text-sm text-gray-600">
                <i class="fas fa-clipboard-list mr-1"></i>
                <?php echo $total_rows; ?> note<?php echo $total_rows > 1 ? 's' : ''; ?> trouvée<?php echo $total_rows > 1 ? 's' : ''; ?>
            </div>
            <div class="flex space-x-2">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">
                    <i class="fas fa-filter mr-2"></i>Filtrer
                </button>
                <?php if ($search || $matiere || $type_note || $date_debut || $date_fin || $eleve_id || $enseignant_id): ?>
                <a href="list.php" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                    <i class="fas fa-times mr-2"></i>Réinitialiser
                </a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<!-- Statistiques rapides -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <?php
    $stats_query = "SELECT 
                    COUNT(*) as total_notes,
                    AVG(note) as moyenne_generale,
                    MIN(note) as note_min,
                    MAX(note) as note_max
                    FROM notes";
    $stats_result = $conn->query($stats_query);
    $stats = $stats_result->fetch_assoc();
    
    // Assurer que toutes les valeurs existent
    $total_notes = $stats['total_notes'] ?? 0;
    $moyenne_generale = $stats['moyenne_generale'] ?? 0;
    $note_min = $stats['note_min'] ?? 0;
    $note_max = $stats['note_max'] ?? 0;
    ?>
    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-clipboard-list text-blue-600"></i>
                </div>
            </div>
            <div class="ml-3">
                <div class="text-2xl font-bold text-gray-800"><?php echo $total_notes; ?></div>
                <div class="text-sm text-gray-600">Notes totales</div>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-chart-line text-green-600"></i>
                </div>
            </div>
            <div class="ml-3">
                <div class="text-2xl font-bold text-gray-800"><?php echo number_format($moyenne_generale, 2); ?></div>
                <div class="text-sm text-gray-600">Moyenne générale</div>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-arrow-down text-red-600"></i>
                </div>
            </div>
            <div class="ml-3">
                <div class="text-2xl font-bold text-gray-800"><?php echo number_format($note_min, 1); ?></div>
                <div class="text-sm text-gray-600">Note minimum</div>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-arrow-up text-purple-600"></i>
                </div>
            </div>
            <div class="ml-3">
                <div class="text-2xl font-bold text-gray-800"><?php echo number_format($note_max, 1); ?></div>
                <div class="text-sm text-gray-600">Note maximum</div>
            </div>
        </div>
    </div>
</div>

<!-- Tableau des notes -->
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Élève
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Classe
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Matière & Note
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Type
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Enseignant
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Date
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($note = $result->fetch_assoc()): ?>
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10 bg-gradient-to-br from-blue-100 to-purple-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-user text-blue-600"></i>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($note['nom_eleve'] . ' ' . $note['prenom_eleve']); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo htmlspecialchars($note['matricule']); ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php if ($note['nom_classe']): ?>
                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                <?php echo htmlspecialchars($note['nom_classe']); ?>
                            </span>
                            <?php else: ?>
                            <span class="text-xs text-gray-500">Non assigné</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div>
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($note['matiere']); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Coef: <?php echo $note['coefficient']; ?>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <span class="px-2 py-1 inline-flex text-sm leading-5 font-semibold rounded-full 
                                                <?php echo $note['note'] >= 10 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $note['note']; ?>/20
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php 
                            $type_badge = [
                                'devoir' => ['bg-yellow-100', 'text-yellow-800', 'Devoir'],
                                'examen' => ['bg-red-100', 'text-red-800', 'Examen'],
                                'participation' => ['bg-blue-100', 'text-blue-800', 'Participation']
                            ];
                            $type = $type_badge[$note['type_note']] ?? $type_badge['devoir'];
                            ?>
                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $type[0]; ?> <?php echo $type[1]; ?>">
                                <?php echo $type[2]; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            <?php echo htmlspecialchars($note['nom_enseignant'] . ' ' . $note['prenom_enseignant']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo format_date_fr($note['date_note'], 'short'); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                <button onclick="showNoteDetails(<?php echo htmlspecialchars(json_encode($note)); ?>)" 
                                        class="text-blue-600 hover:text-blue-900 p-1 hover:bg-blue-50 rounded"
                                        title="Voir">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <a href="edit.php?id=<?php echo $note['id']; ?>" 
                                   class="text-green-600 hover:text-green-900 p-1 hover:bg-green-50 rounded"
                                   title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button onclick="confirmDelete(<?php echo $note['id']; ?>, '<?php echo htmlspecialchars(addslashes($note['nom_eleve'] . ' ' . $note['prenom_eleve'] . ' - ' . $note['matiere'] . ' - ' . $note['note'] . '/20')); ?>')" 
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
                    <td colspan="7" class="px-6 py-12 text-center">
                        <div class="text-gray-400 mb-4">
                            <i class="fas fa-clipboard text-4xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Aucune note trouvée</h3>
                        <p class="text-gray-500 mb-4">
                            <?php echo $search || $matiere || $type_note || $date_debut || $date_fin || $eleve_id || $enseignant_id ? 
                                'Aucune note ne correspond à vos critères.' : 
                                'Commencez par ajouter votre première note.'; ?>
                        </p>
                        <?php if (!($search || $matiere || $type_note || $date_debut || $date_fin || $eleve_id || $enseignant_id)): ?>
                        <a href="add.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i>
                            Ajouter une note
                        </a>
                        <?php endif; ?>
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

<!-- Modal détails note -->
<div id="noteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl p-6 max-w-md w-full mx-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold text-gray-800">Détails de la note</h3>
            <button onclick="closeNoteModal()" class="text-gray-400 hover:text-gray-500">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="noteDetails" class="space-y-3"></div>
        <div class="mt-6 pt-4 border-t">
            <button onclick="closeNoteModal()" 
                    class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">
                Fermer
            </button>
        </div>
    </div>
</div>

<script>
function showNoteDetails(note) {
    const details = document.getElementById('noteDetails');
    details.innerHTML = `
        <div class="flex justify-between">
            <span class="text-sm text-gray-600">Élève</span>
            <span class="font-medium">${note.nom_eleve} ${note.prenom_eleve}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-gray-600">Matricule</span>
            <span class="font-medium">${note.matricule}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-gray-600">Classe</span>
            <span class="font-medium">${note.nom_classe || 'Non assigné'}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-gray-600">Matière</span>
            <span class="font-medium">${note.matiere}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-gray-600">Note</span>
            <span class="font-medium ${note.note >= 10 ? 'text-green-600' : 'text-red-600'}">
                ${note.note}/20
            </span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-gray-600">Type</span>
            <span class="font-medium capitalize">${note.type_note}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-gray-600">Coefficient</span>
            <span class="font-medium">${note.coefficient}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-gray-600">Date</span>
            <span class="font-medium">${new Date(note.date_note).toLocaleDateString('fr-FR')}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-gray-600">Enseignant</span>
            <span class="font-medium">${note.nom_enseignant} ${note.prenom_enseignant}</span>
        </div>
        ${note.commentaire ? `
        <div class="pt-3 border-t">
            <p class="text-sm text-gray-600 mb-1">Commentaire</p>
            <p class="text-sm text-gray-800 bg-gray-50 p-3 rounded">${note.commentaire}</p>
        </div>
        ` : ''}
        <div class="pt-3 border-t">
            <p class="text-sm text-gray-600 mb-1">Date d'ajout</p>
            <p class="text-sm text-gray-800">${new Date(note.created_at).toLocaleString('fr-FR')}</p>
        </div>
    `;
    document.getElementById('noteModal').classList.remove('hidden');
}

function closeNoteModal() {
    document.getElementById('noteModal').classList.add('hidden');
}

function confirmDelete(id, name) {
    if (confirm(`Êtes-vous sûr de vouloir supprimer la note "${name}" ? Cette action est irréversible.`)) {
        window.location.href = `actions.php?action=delete&id=${id}`;
    }
}

// Fermer modal en cliquant à l'extérieur
document.getElementById('noteModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeNoteModal();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>