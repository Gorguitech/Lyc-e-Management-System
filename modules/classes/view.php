<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_auth();

// Vérifier l'ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: list.php?message=ID invalide&message_type=error");
    exit();
}

$id = intval($_GET['id']);

// Connexion à la base de données
$db = Database::getInstance();
$conn = $db->getConnection();

// Récupérer la classe avec statistiques
$stmt = $conn->prepare("
    SELECT c.*,
           (SELECT COUNT(*) FROM eleves WHERE classe_id = c.id) as nb_eleves,
           (SELECT AVG(n.note) FROM eleves e 
            JOIN notes n ON e.id = n.eleve_id 
            WHERE e.classe_id = c.id) as moyenne_classe
    FROM classes c 
    WHERE c.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$classe = $result->fetch_assoc();

if (!$classe) {
    header("Location: list.php?message=Classe non trouvée&message_type=error");
    exit();
}

// Récupérer les élèves de cette classe
$eleves_stmt = $conn->prepare("
    SELECT e.*,
           (SELECT AVG(note) FROM notes WHERE eleve_id = e.id) as moyenne_eleve
    FROM eleves e 
    WHERE e.classe_id = ? 
    ORDER BY e.nom, e.prenom
");
$eleves_stmt->bind_param("i", $id);
$eleves_stmt->execute();
$eleves_result = $eleves_stmt->get_result();

// Récupérer les statistiques par matière
$matieres_stmt = $conn->prepare("
    SELECT n.matiere, 
           COUNT(*) as nb_notes, 
           AVG(n.note) as moyenne_matiere,
           MIN(n.note) as min_note,
           MAX(n.note) as max_note
    FROM notes n 
    JOIN eleves e ON n.eleve_id = e.id 
    WHERE e.classe_id = ? 
    GROUP BY n.matiere 
    ORDER BY nb_notes DESC
    LIMIT 5
");
$matieres_stmt->bind_param("i", $id);
$matieres_stmt->execute();
$matieres_result = $matieres_stmt->get_result();

// Récupérer les derniers événements de la classe
$evenements_stmt = $conn->prepare("
    SELECT action, details, created_at 
    FROM audit_log 
    WHERE (table_name = 'classes' AND record_id = ?) 
       OR (table_name = 'eleves' AND details LIKE ?)
    ORDER BY created_at DESC 
    LIMIT 5
");
$search_term = "%classe_id={$id}%";
$evenements_stmt->bind_param("is", $id, $search_term);
$evenements_stmt->execute();
$evenements_result = $evenements_stmt->get_result();

$page_title = "Détails classe - " . $classe['nom_classe'];
$current_page = 'classes';

include '../../includes/header.php';
?>

<div class="mb-8">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Détails de la classe</h1>
            <p class="text-gray-600 mt-2">Informations complètes et performances de <?php echo htmlspecialchars($classe['nom_classe']); ?></p>
        </div>
        <div class="flex space-x-2">
            <a href="edit.php?id=<?php echo $id; ?>" 
               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                <i class="fas fa-edit mr-2"></i>
                Modifier
            </a>
            <a href="list.php" 
               class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                <i class="fas fa-arrow-left mr-2"></i>
                Retour
            </a>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Colonne gauche : Informations -->
    <div class="lg:col-span-2">
        <!-- Carte classe -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
            <div class="bg-gradient-to-r from-purple-600 to-pink-600 p-6 text-white">
                <div class="flex flex-col md:flex-row items-center md:items-start gap-6">
                    <div class="flex-shrink-0">
                        <div class="w-24 h-24 bg-white/20 rounded-full flex items-center justify-center">
                            <i class="fas fa-school text-3xl"></i>
                        </div>
                    </div>
                    <div class="flex-1 text-center md:text-left">
                        <h2 class="text-2xl font-bold"><?php echo htmlspecialchars($classe['nom_classe']); ?></h2>
                        <div class="flex flex-wrap gap-3 mt-4">
                            <?php if ($classe['niveau']): ?>
                            <span class="px-3 py-1 bg-white/20 rounded-full text-sm">
                                <i class="fas fa-layer-group mr-1"></i>
                                <?php echo htmlspecialchars($classe['niveau']); ?>
                            </span>
                            <?php endif; ?>
                            
                            <?php if ($classe['section']): ?>
                            <span class="px-3 py-1 bg-white/20 rounded-full text-sm">
                                <i class="fas fa-bookmark mr-1"></i>
                                <?php echo htmlspecialchars($classe['section']); ?>
                            </span>
                            <?php endif; ?>
                            
                            <span class="px-3 py-1 bg-white/20 rounded-full text-sm">
                                <i class="fas fa-users mr-1"></i>
                                <?php echo $classe['nb_eleves']; ?> élève<?php echo $classe['nb_eleves'] > 1 ? 's' : ''; ?>
                            </span>
                            
                            <span class="px-3 py-1 bg-white/20 rounded-full text-sm">
                                <i class="fas fa-calendar-plus mr-1"></i>
                                Créée le <?php echo format_date_fr($classe['created_at'], 'short'); ?>
                            </span>
                        </div>
                    </div>
                    <div class="text-center md:text-right">
                        <div class="text-4xl font-bold">
                            <?php echo number_format($classe['moyenne_classe'] ?? 0, 2); ?><span class="text-xl">/20</span>
                        </div>
                        <p class="text-purple-100 text-sm">Moyenne de classe</p>
                    </div>
                </div>
            </div>
            
            <div class="p-6">
                <!-- Statistiques -->
                <div class="mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        <i class="fas fa-chart-bar mr-2 text-purple-600"></i>Statistiques
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="bg-blue-50 p-4 rounded-lg text-center">
                            <div class="text-2xl font-bold text-blue-700"><?php echo $classe['nb_eleves']; ?></div>
                            <div class="text-sm text-blue-600">Effectif</div>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg text-center">
                            <div class="text-2xl font-bold text-green-700"><?php echo number_format($classe['moyenne_classe'] ?? 0, 2); ?></div>
                            <div class="text-sm text-green-600">Moyenne classe</div>
                        </div>
                        <div class="bg-yellow-50 p-4 rounded-lg text-center">
                            <div class="text-2xl font-bold text-yellow-700">
                                <?php
                                $notes_classe = $conn->query("
                                    SELECT COUNT(*) as nb_notes 
                                    FROM notes n 
                                    JOIN eleves e ON n.eleve_id = e.id 
                                    WHERE e.classe_id = $id
                                ")->fetch_assoc()['nb_notes'];
                                echo $notes_classe;
                                ?>
                            </div>
                            <div class="text-sm text-yellow-600">Notes totales</div>
                        </div>
                        <div class="bg-purple-50 p-4 rounded-lg text-center">
                            <div class="text-2xl font-bold text-purple-700">
                                <?php
                                $matieres_classe = $conn->query("
                                    SELECT COUNT(DISTINCT matiere) as nb_matieres 
                                    FROM notes n 
                                    JOIN eleves e ON n.eleve_id = e.id 
                                    WHERE e.classe_id = $id
                                ")->fetch_assoc()['nb_matieres'];
                                echo $matieres_classe;
                                ?>
                            </div>
                            <div class="text-sm text-purple-600">Matières</div>
                        </div>
                    </div>
                </div>
                
                <!-- Performances par matière -->
                <?php if ($matieres_result->num_rows > 0): ?>
                <div class="mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        <i class="fas fa-book-open mr-2 text-blue-600"></i>Performances par matière
                    </h3>
                    <div class="space-y-4">
                        <?php while ($matiere = $matieres_result->fetch_assoc()): ?>
                        <div>
                            <div class="flex justify-between mb-1">
                                <div>
                                    <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($matiere['matiere']); ?></span>
                                    <span class="text-xs text-gray-500 ml-2"><?php echo $matiere['nb_notes']; ?> note(s)</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-sm font-semibold text-gray-900">
                                        <?php echo number_format($matiere['moyenne_matiere'], 2); ?>/20
                                    </span>
                                    <div class="text-xs text-gray-500">
                                        Min: <?php echo number_format($matiere['min_note'], 1); ?> - 
                                        Max: <?php echo number_format($matiere['max_note'], 1); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full" 
                                     style="width: <?php echo min(($matiere['moyenne_matiere'] / 20 * 100), 100); ?>%"></div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Liste des élèves -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-bold text-gray-800">
                    <i class="fas fa-users mr-2"></i>Élèves de la classe
                </h3>
                <a href="<?php echo BASE_URL; ?>modules/eleves/add.php?classe_id=<?php echo $id; ?>" 
                   class="inline-flex items-center px-3 py-1 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">
                    <i class="fas fa-user-plus mr-1"></i>Ajouter un élève
                </a>
            </div>
            
            <?php if ($eleves_result->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Élève</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Matricule</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Moyenne</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php while ($eleve = $eleves_result->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-8 w-8 bg-blue-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-user text-blue-600 text-xs"></i>
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                <?php echo htmlspecialchars($eleve['matricule']); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <?php if ($eleve['moyenne_eleve']): ?>
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                           <?php echo $eleve['moyenne_eleve'] >= 10 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo number_format($eleve['moyenne_eleve'], 2); ?>/20
                                </span>
                                <?php else: ?>
                                <span class="text-xs text-gray-500">Pas de notes</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                <div class="flex space-x-2">
                                    <a href="<?php echo BASE_URL; ?>modules/eleves/view.php?id=<?php echo $eleve['id']; ?>" 
                                       class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>modules/notes/add.php?eleve_id=<?php echo $eleve['id']; ?>" 
                                       class="text-green-600 hover:text-green-900">
                                        <i class="fas fa-plus-circle"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-8">
                <div class="text-gray-400 mb-4">
                    <i class="fas fa-users-slash text-4xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Aucun élève dans cette classe</h3>
                <p class="text-gray-500 mb-4">Cette classe n'a pas encore d'élèves inscrits.</p>
                <a href="<?php echo BASE_URL; ?>modules/eleves/add.php?classe_id=<?php echo $id; ?>" 
                   class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-user-plus mr-2"></i>
                    Ajouter un élève
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Colonne droite : Actions et historique -->
    <div class="lg:col-span-1">
        <div class="space-y-6">
            <!-- Actions rapides -->
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h3 class="text-sm font-medium text-gray-900 mb-4">Actions rapides</h3>
                <div class="space-y-2">
                    <a href="edit.php?id=<?php echo $id; ?>" 
                       class="flex items-center justify-center w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i class="fas fa-edit mr-2"></i>
                        Modifier la classe
                    </a>
                    <a href="<?php echo BASE_URL; ?>modules/eleves/add.php?classe_id=<?php echo $id; ?>" 
                       class="flex items-center justify-center w-full px-4 py-2 border border-blue-300 text-blue-700 rounded-lg hover:bg-blue-50">
                        <i class="fas fa-user-plus mr-2"></i>
                        Ajouter un élève
                    </a>
                    <a href="#" 
                       class="flex items-center justify-center w-full px-4 py-2 border border-green-300 text-green-700 rounded-lg hover:bg-green-50">
                        <i class="fas fa-file-pdf mr-2"></i>
                        Bulletin de classe
                    </a>
                    <button onclick="confirmDelete(<?php echo $id; ?>, '<?php echo htmlspecialchars(addslashes($classe['nom_classe'])); ?>')" 
                            class="flex items-center justify-center w-full px-4 py-2 border border-red-300 text-red-700 rounded-lg hover:bg-red-50">
                        <i class="fas fa-trash mr-2"></i>
                        Supprimer la classe
                    </button>
                </div>
            </div>
            
            <!-- Répartition par sexe -->
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h3 class="text-sm font-medium text-gray-900 mb-4">Répartition</h3>
                <?php
                // Note: Vous devrez ajouter un champ 'sexe' dans la table eleves si vous voulez cette fonctionnalité
                // Pour l'instant, on simule des données
                $garcons = rand(40, 60);
                $filles = 100 - $garcons;
                ?>
                <div class="space-y-3">
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-xs text-gray-600">Garçons</span>
                            <span class="text-xs font-semibold text-gray-900"><?php echo $garcons; ?>%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-500 h-2 rounded-full" style="width: <?php echo $garcons; ?>%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-xs text-gray-600">Filles</span>
                            <span class="text-xs font-semibold text-gray-900"><?php echo $filles; ?>%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-pink-500 h-2 rounded-full" style="width: <?php echo $filles; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Historique récent -->
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h3 class="text-sm font-medium text-gray-900 mb-4">Activité récente</h3>
                <div class="space-y-3">
                    <?php if ($evenements_result->num_rows > 0): ?>
                        <?php while ($log = $evenements_result->fetch_assoc()): ?>
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mt-0.5">
                                <?php 
                                $icon = 'fa-history';
                                $color = 'text-gray-400';
                                if (strpos($log['action'], 'CREATE') !== false) {
                                    $icon = 'fa-plus-circle';
                                    $color = 'text-green-500';
                                } elseif (strpos($log['action'], 'UPDATE') !== false) {
                                    $icon = 'fa-edit';
                                    $color = 'text-blue-500';
                                } elseif (strpos($log['action'], 'DELETE') !== false) {
                                    $icon = 'fa-trash';
                                    $color = 'text-red-500';
                                }
                                ?>
                                <i class="fas <?php echo $icon; ?> <?php echo $color; ?> text-sm"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-xs text-gray-900"><?php echo htmlspecialchars($log['details']); ?></p>
                                <p class="text-xs text-gray-400 mt-1"><?php echo format_date_fr($log['created_at'], 'datetime'); ?></p>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                    <p class="text-xs text-gray-500 text-center py-2">Aucune activité récente</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, name) {
    if (confirm(`Êtes-vous sûr de vouloir supprimer la classe "${name}" ? Les élèves de cette classe seront déclassés.`)) {
        window.location.href = `actions.php?action=delete&id=${id}`;
    }
}
</script>

<?php include '../../includes/footer.php'; ?>