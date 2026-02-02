<?php
require_once 'config/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
// Vérifier l'authentification
require_auth();


// Définir le titre de la page
$page_title = "Dashboard";

// Connexion à la base de données
$db = Database::getInstance();
$conn = $db->getConnection();

// Récupérer les statistiques
$stats = [];
$queries = [
    'eleves' => "SELECT COUNT(*) as count FROM eleves",
    'enseignants' => "SELECT COUNT(*) as count FROM enseignants",
    'classes' => "SELECT COUNT(*) as count FROM classes",
    'notes' => "SELECT COUNT(*) as count FROM notes",
    'notes_recentes' => "SELECT COUNT(*) as count FROM notes WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
];

foreach ($queries as $key => $query) {
    $result = $conn->query($query);
    if ($result) {
        $row = $result->fetch_assoc();
        $stats[$key] = $row['count'];
    }
}

// Moyenne des notes
$moyenne_query = "SELECT AVG(note) as moyenne FROM notes";
$moyenne_result = $conn->query($moyenne_query);
$moyenne = $moyenne_result->fetch_assoc()['moyenne'] ?? 0;

// Derniers élèves ajoutés
$recent_eleves = $conn->query("
    SELECT e.*, c.nom_classe 
    FROM eleves e 
    LEFT JOIN classes c ON e.classe_id = c.id 
    ORDER BY e.created_at DESC 
    LIMIT 5
");

// Dernières notes ajoutées
$recent_notes = $conn->query("
    SELECT n.*, e.nom, e.prenom, ens.nom as nom_enseignant 
    FROM notes n 
    JOIN eleves e ON n.eleve_id = e.id 
    JOIN enseignants ens ON n.enseignant_id = ens.id 
    ORDER BY n.created_at DESC 
    LIMIT 5
");

// Meilleurs élèves
$top_eleves = $conn->query("
    SELECT e.id, e.nom, e.prenom, AVG(n.note) as moyenne 
    FROM eleves e 
    JOIN notes n ON e.id = n.eleve_id 
    GROUP BY e.id 
    ORDER BY moyenne DESC 
    LIMIT 5
");

// Activité récente
$activite = $conn->query("
    SELECT a.*, u.username 
    FROM audit_log a 
    JOIN users u ON a.user_id = u.id 
    ORDER BY a.created_at DESC 
    LIMIT 5
");

// Inclure l'en-tête
include 'includes/header.php';
?>

<!-- Welcome Banner -->
<div class="mb-8">
    <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-2xl p-6 text-white">
        <div class="flex flex-col md:flex-row justify-between items-center">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold">Bonjour, <?php echo $_SESSION['full_name']; ?>! 👋</h1>
                <p class="mt-2 text-blue-100">Bienvenue sur votre tableau de bord de gestion scolaire.</p>
                <p class="text-sm text-blue-200 mt-1">
                    <i class="fas fa-calendar-day mr-1"></i>
                    <?php echo format_date_fr(time(), 'full'); ?>
                </p>
            </div>
            <div class="mt-4 md:mt-0">
                <a href="#" class="inline-flex items-center px-4 py-2 bg-white text-blue-600 rounded-lg font-medium hover:bg-blue-50">
                    <i class="fas fa-plus mr-2"></i>
                    Ajouter un élève
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Statistiques principales -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Carte Élèves -->
    <div class="stat-card bg-white rounded-2xl p-6 shadow-md">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm font-medium text-gray-500">Élèves</p>
                <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $stats['eleves'] ?? 0; ?></p>
                <p class="text-xs text-gray-500 mt-1">Total inscrits</p>
            </div>
            <div class="p-3 bg-blue-50 rounded-xl">
                <i class="fas fa-user-graduate text-blue-600 text-2xl"></i>
            </div>
        </div>
        <div class="mt-4 pt-4 border-t border-gray-100">
            <a href="<?php echo BASE_URL; ?>modules/eleves/list.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
                Voir la liste
                <i class="fas fa-arrow-right ml-2 text-xs"></i>
            </a>
        </div>
    </div>
    
    <!-- Carte Enseignants -->
    <div class="stat-card bg-white rounded-2xl p-6 shadow-md">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm font-medium text-gray-500">Enseignants</p>
                <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $stats['enseignants'] ?? 0; ?></p>
                <p class="text-xs text-gray-500 mt-1">Personnel éducatif</p>
            </div>
            <div class="p-3 bg-green-50 rounded-xl">
                <i class="fas fa-chalkboard-teacher text-green-600 text-2xl"></i>
            </div>
        </div>
        <div class="mt-4 pt-4 border-t border-gray-100">
            <a href="<?php echo BASE_URL; ?>modules/enseignants/list.php" class="text-green-600 hover:text-green-800 text-sm font-medium flex items-center">
                Voir la liste
                <i class="fas fa-arrow-right ml-2 text-xs"></i>
            </a>
        </div>
    </div>
    
    <!-- Carte Classes -->
    <div class="stat-card bg-white rounded-2xl p-6 shadow-md">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm font-medium text-gray-500">Classes</p>
                <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $stats['classes'] ?? 0; ?></p>
                <p class="text-xs text-gray-500 mt-1">Divisions actives</p>
            </div>
            <div class="p-3 bg-purple-50 rounded-xl">
                <i class="fas fa-school text-purple-600 text-2xl"></i>
            </div>
        </div>
        <div class="mt-4 pt-4 border-t border-gray-100">
            <a href="<?php echo BASE_URL; ?>modules/classes/list.php" class="text-purple-600 hover:text-purple-800 text-sm font-medium flex items-center">
                Voir la liste
                <i class="fas fa-arrow-right ml-2 text-xs"></i>
            </a>
        </div>
    </div>
    
    <!-- Carte Performance -->
    <div class="stat-card bg-white rounded-2xl p-6 shadow-md">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm font-medium text-gray-500">Moyenne générale</p>
                <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo number_format($moyenne, 2); ?>/20</p>
                <p class="text-xs text-gray-500 mt-1">Performance globale</p>
            </div>
            <div class="p-3 bg-orange-50 rounded-xl">
                <i class="fas fa-chart-line text-orange-600 text-2xl"></i>
            </div>
        </div>
        <div class="mt-4 pt-4 border-t border-gray-100">
            <div class="flex items-center text-xs">
                <span class="text-green-600 font-medium">
                    <i class="fas fa-arrow-up mr-1"></i>
                    <?php echo $stats['notes_recentes'] ?? 0; ?> notes cette semaine
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Graphiques et tableaux -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
    <!-- Graphique de performance -->
    <div class="bg-white rounded-2xl shadow-md p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-bold text-gray-800">Performance académique</h3>
            <div class="flex space-x-2">
                <button class="px-3 py-1 text-xs bg-blue-50 text-blue-600 rounded-lg font-medium">Mensuel</button>
                <button class="px-3 py-1 text-xs text-gray-500 hover:text-gray-700 rounded-lg">Trimestriel</button>
            </div>
        </div>
        <div class="h-64">
            <canvas id="performanceChart"></canvas>
        </div>
    </div>
    
    <!-- Répartition par classe -->
    <div class="bg-white rounded-2xl shadow-md p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-bold text-gray-800">Répartition par classe</h3>
            <div class="text-sm text-gray-500">
                <i class="fas fa-info-circle mr-1"></i>
                Total: <?php echo $stats['eleves'] ?? 0; ?> élèves
            </div>
        </div>
        <div class="h-64">
            <canvas id="classesChart"></canvas>
        </div>
    </div>
</div>

<!-- Tableaux récents -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Derniers élèves -->
    <div class="bg-white rounded-2xl shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-800">Derniers élèves inscrits</h3>
                <a href="<?php echo BASE_URL; ?>modules/eleves/list.php" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                    Voir tout
                </a>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Élève</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Classe</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if ($recent_eleves->num_rows > 0): ?>
                        <?php while ($eleve = $recent_eleves->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-8 w-8 bg-blue-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-user text-blue-600 text-xs"></i>
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900"><?php echo $eleve['nom'] . ' ' . $eleve['prenom']; ?></div>
                                        <div class="text-xs text-gray-500"><?php echo $eleve['matricule']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
                                    <?php echo $eleve['nom_classe'] ?? 'Non assigné'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo date('d/m/Y', strtotime($eleve['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <a href="#" class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-gray-500">
                                <i class="fas fa-user-slash text-2xl mb-2 block"></i>
                                Aucun élève trouvé
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Dernières notes -->
    <div class="bg-white rounded-2xl shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-800">Dernières notes ajoutées</h3>
                <a href="<?php echo BASE_URL; ?>modules/notes/list.php" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                    Voir tout
                </a>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Élève</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Matière</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Note</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Enseignant</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if ($recent_notes->num_rows > 0): ?>
                        <?php while ($note = $recent_notes->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo $note['nom'] . ' ' . $note['prenom']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                <?php echo $note['matiere']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                           <?php echo $note['note'] >= 10 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $note['note']; ?>/20
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo $note['nom_enseignant']; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-gray-500">
                                <i class="fas fa-clipboard text-2xl mb-2 block"></i>
                                Aucune note trouvée
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Meilleurs élèves -->
<div class="mt-8 bg-white rounded-2xl shadow-md overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <div class="flex justify-between items-center">
            <h3 class="text-lg font-bold text-gray-800">Meilleurs élèves</h3>
            <div class="text-sm text-gray-500">
                Classement par moyenne générale
            </div>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rang</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Élève</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Moyenne</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progression</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Détails</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($top_eleves->num_rows > 0): ?>
                    <?php $rank = 1; ?>
                    <?php while ($eleve = $top_eleves->fetch_assoc()): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-8 h-8 flex items-center justify-center rounded-full 
                                            <?php echo $rank <= 3 ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'; ?>">
                                    <?php echo $rank; ?>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-bold">
                                    <?php echo strtoupper(substr($eleve['prenom'], 0, 1)); ?>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900"><?php echo $eleve['nom'] . ' ' . $eleve['prenom']; ?></div>
                                    <div class="text-xs text-gray-500">ID: <?php echo $eleve['id']; ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-lg font-bold text-gray-900"><?php echo number_format($eleve['moyenne'], 2); ?>/20</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <?php $progression = rand(5, 15); ?>
                                <span class="text-green-600 font-medium mr-2">
                                    <i class="fas fa-arrow-up"></i> <?php echo $progression; ?>%
                                </span>
                                <div class="w-24 bg-gray-200 rounded-full h-2">
                                    <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo min($progression, 100); ?>%"></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <a href="#" class="text-blue-600 hover:text-blue-900 font-medium">
                                Voir bulletin
                            </a>
                        </td>
                    </tr>
                    <?php $rank++; ?>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                            <i class="fas fa-trophy text-2xl mb-2 block"></i>
                            Pas encore de données de performance
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// Inclure le pied de page
include 'includes/footer.php';
?>