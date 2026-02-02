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

// Récupérer l'enseignant
$stmt = $conn->prepare("SELECT * FROM enseignants WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$enseignant = $result->fetch_assoc();

if (!$enseignant) {
    header("Location: list.php?message=Enseignant non trouvé&message_type=error");
    exit();
}

// Récupérer les notes attribuées par cet enseignant
$notes_stmt = $conn->prepare("
    SELECT n.*, e.nom as nom_eleve, e.prenom as prenom_eleve, c.nom_classe 
    FROM notes n 
    JOIN eleves e ON n.eleve_id = e.id 
    LEFT JOIN classes c ON e.classe_id = c.id 
    WHERE n.enseignant_id = ? 
    ORDER BY n.date_note DESC, n.created_at DESC
");
$notes_stmt->bind_param("i", $id);
$notes_stmt->execute();
$notes_result = $notes_stmt->get_result();

// Statistiques
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as nb_notes,
        AVG(note) as moyenne,
        MIN(note) as min_note,
        MAX(note) as max_note,
        COUNT(DISTINCT matiere) as nb_matieres
    FROM notes 
    WHERE enseignant_id = ?
");
$stats_stmt->bind_param("i", $id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// Récupérer les matières enseignées
$matieres_stmt = $conn->prepare("
    SELECT matiere, COUNT(*) as nb_notes, AVG(note) as moyenne 
    FROM notes 
    WHERE enseignant_id = ? 
    GROUP BY matiere 
    ORDER BY nb_notes DESC
");
$matieres_stmt->bind_param("i", $id);
$matieres_stmt->execute();
$matieres_result = $matieres_stmt->get_result();

$page_title = "Fiche enseignant - " . $enseignant['nom'] . ' ' . $enseignant['prenom'];
$current_page = 'enseignants';

include '../../includes/header.php';
?>

<div class="mb-8">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Fiche enseignant</h1>
            <p class="text-gray-600 mt-2">Informations détaillées et statistiques</p>
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
        <!-- Carte profil -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
            <div class="bg-gradient-to-r from-green-600 to-blue-600 p-6 text-white">
                <div class="flex flex-col md:flex-row items-center md:items-start gap-6">
                    <div class="flex-shrink-0">
                        <div class="w-24 h-24 bg-white/20 rounded-full flex items-center justify-center">
                            <i class="fas fa-chalkboard-teacher text-3xl"></i>
                        </div>
                    </div>
                    <div class="flex-1 text-center md:text-left">
                        <h2 class="text-2xl font-bold"><?php echo htmlspecialchars($enseignant['nom'] . ' ' . $enseignant['prenom']); ?></h2>
                        <p class="text-green-100 mt-1"><?php echo htmlspecialchars($enseignant['matricule']); ?></p>
                        <div class="flex flex-wrap gap-3 mt-4">
                            <span class="px-3 py-1 bg-white/20 rounded-full text-sm">
                                <i class="fas fa-book mr-1"></i>
                                <?php echo htmlspecialchars($enseignant['matiere']); ?>
                            </span>
                            
                            <?php if ($enseignant['specialite']): ?>
                            <span class="px-3 py-1 bg-white/20 rounded-full text-sm">
                                <i class="fas fa-star mr-1"></i>
                                <?php echo htmlspecialchars($enseignant['specialite']); ?>
                            </span>
                            <?php endif; ?>
                            
                            <span class="px-3 py-1 bg-white/20 rounded-full text-sm">
                                <i class="fas fa-calendar-plus mr-1"></i>
                                Inscrit le <?php echo format_date_fr($enseignant['created_at'], 'short'); ?>
                            </span>
                        </div>
                    </div>
                    <div class="text-center md:text-right">
                        <div class="text-4xl font-bold">
                            <?php echo number_format($stats['moyenne'] ?? 0, 2); ?><span class="text-xl">/20</span>
                        </div>
                        <p class="text-green-100 text-sm">Moyenne attribuée</p>
                        <p class="text-green-200 text-xs mt-1"><?php echo $stats['nb_notes'] ?? 0; ?> note(s)</p>
                    </div>
                </div>
            </div>
            
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Informations de contact -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            <i class="fas fa-address-card mr-2 text-green-600"></i>Contact
                        </h3>
                        <div class="space-y-3">
                            <div class="flex items-start">
                                <i class="fas fa-envelope text-gray-400 mt-1 mr-3"></i>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Email</p>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($enseignant['email']); ?></p>
                                </div>
                            </div>
                            
                            <?php if ($enseignant['telephone']): ?>
                            <div class="flex items-start">
                                <i class="fas fa-phone text-gray-400 mt-1 mr-3"></i>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Téléphone</p>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($enseignant['telephone']); ?></p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Informations professionnelles -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            <i class="fas fa-graduation-cap mr-2 text-blue-600"></i>Informations professionnelles
                        </h3>
                        <div class="space-y-3">
                            <div class="flex items-start">
                                <i class="fas fa-id-card text-gray-400 mt-1 mr-3"></i>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Matricule</p>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($enseignant['matricule']); ?></p>
                                </div>
                            </div>
                            
                            <div class="flex items-start">
                                <i class="fas fa-book text-gray-400 mt-1 mr-3"></i>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Matière principale</p>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($enseignant['matiere']); ?></p>
                                </div>
                            </div>
                            
                            <?php if ($enseignant['specialite']): ?>
                            <div class="flex items-start">
                                <i class="fas fa-star text-gray-400 mt-1 mr-3"></i>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Spécialité</p>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($enseignant['specialite']); ?></p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Notes attribuées -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-bold text-gray-800">
                    <i class="fas fa-clipboard-list mr-2"></i>Notes attribuées
                </h3>
                <a href="<?php echo BASE_URL; ?>modules/notes/add.php?enseignant_id=<?php echo $id; ?>" 
                   class="inline-flex items-center px-3 py-1 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">
                    <i class="fas fa-plus mr-1"></i>Ajouter une note
                </a>
            </div>
            
            <?php if ($notes_result->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Élève</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Classe</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Matière</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Note</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php while ($note = $notes_result->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-8 w-8 bg-blue-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-user text-blue-600 text-xs"></i>
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($note['nom_eleve'] . ' ' . $note['prenom_eleve']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                <?php echo htmlspecialchars($note['nom_classe'] ?? 'N/A'); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                <?php echo htmlspecialchars($note['matiere']); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                           <?php echo $note['note'] >= 10 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $note['note']; ?>/20
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                <?php echo format_date_fr($note['date_note'], 'short'); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                <div class="flex space-x-2">
                                    <button onclick="showNoteDetails(<?php echo htmlspecialchars(json_encode($note)); ?>)" 
                                            class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <a href="<?php echo BASE_URL; ?>modules/notes/edit.php?id=<?php echo $note['id']; ?>" 
                                       class="text-green-600 hover:text-green-900">
                                        <i class="fas fa-edit"></i>
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
                    <i class="fas fa-clipboard text-4xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Aucune note attribuée</h3>
                <p class="text-gray-500 mb-4">Cet enseignant n'a pas encore attribué de notes.</p>
                <a href="<?php echo BASE_URL; ?>modules/notes/add.php?enseignant_id=<?php echo $id; ?>" 
                   class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-plus mr-2"></i>
                    Ajouter la première note
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Colonne droite : Statistiques et actions -->
    <div class="lg:col-span-1">
        <div class="space-y-6">
            <!-- Statistiques -->
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h3 class="text-sm font-medium text-gray-900 mb-4">Statistiques</h3>
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-blue-50 p-3 rounded-lg text-center">
                            <div class="text-2xl font-bold text-blue-700"><?php echo $stats['nb_notes'] ?? 0; ?></div>
                            <div class="text-xs text-blue-600">Notes totales</div>
                        </div>
                        <div class="bg-green-50 p-3 rounded-lg text-center">
                            <div class="text-2xl font-bold text-green-700"><?php echo $stats['nb_matieres'] ?? 0; ?></div>
                            <div class="text-xs text-green-600">Matières</div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-sm text-gray-600">Moyenne générale</span>
                            <span class="text-sm font-semibold text-gray-900">
                                <?php echo number_format($stats['moyenne'] ?? 0, 2); ?>/20
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-green-600 h-2 rounded-full" 
                                 style="width: <?php echo min((($stats['moyenne'] ?? 0) / 20 * 100), 100); ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <div class="text-xs text-gray-600">Note min</div>
                            <div class="text-sm font-semibold <?php echo ($stats['min_note'] ?? 0) < 10 ? 'text-red-600' : 'text-green-600'; ?>">
                                <?php echo number_format($stats['min_note'] ?? 0, 2); ?>/20
                            </div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-600">Note max</div>
                            <div class="text-sm font-semibold text-green-600">
                                <?php echo number_format($stats['max_note'] ?? 0, 2); ?>/20
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Matières enseignées -->
            <?php if ($matieres_result->num_rows > 0): ?>
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h3 class="text-sm font-medium text-gray-900 mb-4">Matières enseignées</h3>
                <div class="space-y-3">
                    <?php while ($matiere = $matieres_result->fetch_assoc()): ?>
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-xs text-gray-600"><?php echo htmlspecialchars($matiere['matiere']); ?></span>
                            <span class="text-xs font-semibold text-gray-900">
                                <?php echo number_format($matiere['moyenne'], 2); ?>/20
                            </span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-1">
                            <div class="bg-blue-500 h-1 rounded-full" 
                                 style="width: <?php echo min(($matiere['moyenne'] / 20 * 100), 100); ?>%"></div>
                        </div>
                        <div class="text-xs text-gray-400 text-right mt-1">
                            <?php echo $matiere['nb_notes']; ?> note(s)
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Actions rapides -->
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h3 class="text-sm font-medium text-gray-900 mb-4">Actions rapides</h3>
                <div class="space-y-2">
                    <a href="edit.php?id=<?php echo $id; ?>" 
                       class="flex items-center justify-center w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i class="fas fa-edit mr-2"></i>
                        Modifier la fiche
                    </a>
                    <a href="<?php echo BASE_URL; ?>modules/notes/add.php?enseignant_id=<?php echo $id; ?>" 
                       class="flex items-center justify-center w-full px-4 py-2 border border-blue-300 text-blue-700 rounded-lg hover:bg-blue-50">
                        <i class="fas fa-plus-circle mr-2"></i>
                        Ajouter une note
                    </a>
                    <a href="#" 
                       class="flex items-center justify-center w-full px-4 py-2 border border-green-300 text-green-700 rounded-lg hover:bg-green-50">
                        <i class="fas fa-file-pdf mr-2"></i>
                        Rapport mensuel
                    </a>
                    <button onclick="confirmDelete(<?php echo $id; ?>, '<?php echo htmlspecialchars(addslashes($enseignant['nom'] . ' ' . $enseignant['prenom'])); ?>')" 
                            class="flex items-center justify-center w-full px-4 py-2 border border-red-300 text-red-700 rounded-lg hover:bg-red-50">
                        <i class="fas fa-trash mr-2"></i>
                        Supprimer l'enseignant
                    </button>
                </div>
            </div>
        </div>
    </div>
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
            <span class="text-sm text-gray-600">Classe</span>
            <span class="font-medium">${note.nom_classe || 'N/A'}</span>
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
            <span class="font-medium capitalize">${note.type_note || 'devoir'}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-gray-600">Coefficient</span>
            <span class="font-medium">${note.coefficient || 1.00}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-gray-600">Date</span>
            <span class="font-medium">${new Date(note.date_note).toLocaleDateString('fr-FR')}</span>
        </div>
        ${note.commentaire ? `
        <div class="pt-3 border-t">
            <p class="text-sm text-gray-600 mb-1">Commentaire</p>
            <p class="text-sm text-gray-800 bg-gray-50 p-3 rounded">${note.commentaire}</p>
        </div>
        ` : ''}
    `;
    document.getElementById('noteModal').classList.remove('hidden');
}

function closeNoteModal() {
    document.getElementById('noteModal').classList.add('hidden');
}

function confirmDelete(id, name) {
    if (confirm(`Êtes-vous sûr de vouloir supprimer l'enseignant "${name}" ? Cette action est irréversible.`)) {
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