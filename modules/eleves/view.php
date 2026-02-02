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

// Récupérer l'élève avec sa classe
$stmt = $conn->prepare("
    SELECT e.*, c.nom_classe, c.niveau, c.section 
    FROM eleves e 
    LEFT JOIN classes c ON e.classe_id = c.id 
    WHERE e.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$eleve = $result->fetch_assoc();

if (!$eleve) {
    header("Location: list.php?message=Élève non trouvé&message_type=error");
    exit();
}

// Récupérer les notes de l'élève
$notes_stmt = $conn->prepare("
    SELECT n.*, ens.nom as nom_enseignant, ens.prenom as prenom_enseignant 
    FROM notes n 
    JOIN enseignants ens ON n.enseignant_id = ens.id 
    WHERE n.eleve_id = ? 
    ORDER BY n.date_note DESC, n.created_at DESC
");
$notes_stmt->bind_param("i", $id);
$notes_stmt->execute();
$notes_result = $notes_stmt->get_result();

// Calculer la moyenne générale
$moyenne_stmt = $conn->prepare("
    SELECT AVG(note) as moyenne, COUNT(*) as nb_notes 
    FROM notes 
    WHERE eleve_id = ?
");
$moyenne_stmt->bind_param("i", $id);
$moyenne_stmt->execute();
$moyenne_data = $moyenne_stmt->get_result()->fetch_assoc();

$page_title = "Fiche élève - " . $eleve['nom'] . ' ' . $eleve['prenom'];
$current_page = 'eleves';

include '../../includes/header.php';
?>

<div class="mb-8">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Fiche élève</h1>
            <p class="text-gray-600 mt-2">Informations détaillées et performances</p>
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
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 p-6 text-white">
                <div class="flex flex-col md:flex-row items-center md:items-start gap-6">
                    <div class="flex-shrink-0">
                        <div class="w-24 h-24 bg-white/20 rounded-full flex items-center justify-center">
                            <i class="fas fa-user-graduate text-3xl"></i>
                        </div>
                    </div>
                    <div class="flex-1 text-center md:text-left">
                        <h2 class="text-2xl font-bold"><?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?></h2>
                        <p class="text-blue-100 mt-1"><?php echo htmlspecialchars($eleve['matricule']); ?></p>
                        <div class="flex flex-wrap gap-3 mt-4">
                            <?php if ($eleve['nom_classe']): ?>
                            <span class="px-3 py-1 bg-white/20 rounded-full text-sm">
                                <i class="fas fa-school mr-1"></i>
                                <?php echo htmlspecialchars($eleve['nom_classe']); ?>
                            </span>
                            <?php endif; ?>
                            
                            <?php 
                            $date_naissance = new DateTime($eleve['date_naissance']);
                            $aujourdhui = new DateTime();
                            $age = $aujourdhui->diff($date_naissance)->y;
                            ?>
                            <span class="px-3 py-1 bg-white/20 rounded-full text-sm">
                                <i class="fas fa-birthday-cake mr-1"></i>
                                <?php echo $age; ?> ans
                            </span>
                            
                            <span class="px-3 py-1 bg-white/20 rounded-full text-sm">
                                <i class="fas fa-calendar-day mr-1"></i>
                                Né le <?php echo format_date_fr($eleve['date_naissance'], 'long'); ?>
                            </span>
                        </div>
                    </div>
                    <div class="text-center md:text-right">
                        <div class="text-4xl font-bold">
                            <?php echo number_format($moyenne_data['moyenne'] ?? 0, 2); ?><span class="text-xl">/20</span>
                        </div>
                        <p class="text-blue-100 text-sm">Moyenne générale</p>
                        <p class="text-blue-200 text-xs mt-1"><?php echo $moyenne_data['nb_notes'] ?? 0; ?> note(s)</p>
                    </div>
                </div>
            </div>
            
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Informations de contact -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            <i class="fas fa-address-card mr-2 text-blue-600"></i>Contact
                        </h3>
                        <div class="space-y-3">
                            <?php if ($eleve['email']): ?>
                            <div class="flex items-start">
                                <i class="fas fa-envelope text-gray-400 mt-1 mr-3"></i>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Email</p>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($eleve['email']); ?></p>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($eleve['telephone']): ?>
                            <div class="flex items-start">
                                <i class="fas fa-phone text-gray-400 mt-1 mr-3"></i>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Téléphone</p>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($eleve['telephone']); ?></p>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($eleve['adresse']): ?>
                            <div class="flex items-start">
                                <i class="fas fa-map-marker-alt text-gray-400 mt-1 mr-3"></i>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Adresse</p>
                                    <p class="text-sm text-gray-600 whitespace-pre-line"><?php echo htmlspecialchars($eleve['adresse']); ?></p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Informations scolaires -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            <i class="fas fa-school mr-2 text-purple-600"></i>Scolarité
                        </h3>
                        <div class="space-y-3">
                            <div class="flex items-start">
                                <i class="fas fa-id-card text-gray-400 mt-1 mr-3"></i>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Matricule</p>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($eleve['matricule']); ?></p>
                                </div>
                            </div>
                            
                            <?php if ($eleve['nom_classe']): ?>
                            <div class="flex items-start">
                                <i class="fas fa-users text-gray-400 mt-1 mr-3"></i>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Classe</p>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($eleve['nom_classe']); ?></p>
                                    <?php if ($eleve['niveau'] || $eleve['section']): ?>
                                    <p class="text-xs text-gray-500">
                                        <?php echo $eleve['niveau'] ? $eleve['niveau'] . ' ' : ''; ?>
                                        <?php echo $eleve['section'] ? '(' . $eleve['section'] . ')' : ''; ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="flex items-start">
                                <i class="fas fa-calendar-plus text-gray-400 mt-1 mr-3"></i>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Date d'inscription</p>
                                    <p class="text-sm text-gray-600"><?php echo format_date_fr($eleve['created_at'], 'full'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Notes -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-bold text-gray-800">
                    <i class="fas fa-clipboard-list mr-2"></i>Notes
                </h3>
                <a href="<?php echo BASE_URL; ?>modules/notes/add.php?eleve_id=<?php echo $id; ?>" 
                   class="inline-flex items-center px-3 py-1 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">
                    <i class="fas fa-plus mr-1"></i>Ajouter une note
                </a>
            </div>
            
            <?php if ($notes_result->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Matière</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Note</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Enseignant</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php while ($note = $notes_result->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($note['matiere']); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                           <?php echo $note['note'] >= 10 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $note['note']; ?>/20
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                <span class="capitalize"><?php echo $note['type_note']; ?></span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                <?php echo htmlspecialchars($note['nom_enseignant'] . ' ' . $note['prenom_enseignant']); ?>
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
                <h3 class="text-lg font-medium text-gray-900 mb-2">Aucune note</h3>
                <p class="text-gray-500 mb-4">Cet élève n'a pas encore de notes enregistrées.</p>
                <a href="<?php echo BASE_URL; ?>modules/notes/add.php?eleve_id=<?php echo $id; ?>" 
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
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-sm text-gray-600">Moyenne générale</span>
                            <span class="text-sm font-semibold text-gray-900">
                                <?php echo number_format($moyenne_data['moyenne'] ?? 0, 2); ?>/20
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full" 
                                 style="width: <?php echo min((($moyenne_data['moyenne'] ?? 0) / 20 * 100), 100); ?>%"></div>
                        </div>
                    </div>
                    
                    <?php
                    // Récupérer les statistiques par matière
                    $matieres_stmt = $conn->prepare("
                        SELECT matiere, AVG(note) as moyenne, COUNT(*) as nb_notes 
                        FROM notes 
                        WHERE eleve_id = ? 
                        GROUP BY matiere 
                        ORDER BY moyenne DESC
                        LIMIT 5
                    ");
                    $matieres_stmt->bind_param("i", $id);
                    $matieres_stmt->execute();
                    $matieres_result = $matieres_stmt->get_result();
                    ?>
                    
                    <?php if ($matieres_result->num_rows > 0): ?>
                    <div class="pt-4 border-t border-gray-200">
                        <h4 class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-3">Par matière</h4>
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
                                    <div class="bg-green-500 h-1 rounded-full" 
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
                </div>
            </div>
            
            <!-- Actions rapides -->
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h3 class="text-sm font-medium text-gray-900 mb-4">Actions rapides</h3>
                <div class="space-y-2">
                    <a href="edit.php?id=<?php echo $id; ?>" 
                       class="flex items-center justify-center w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i class="fas fa-edit mr-2"></i>
                        Modifier la fiche
                    </a>
                    <a href="<?php echo BASE_URL; ?>modules/notes/add.php?eleve_id=<?php echo $id; ?>" 
                       class="flex items-center justify-center w-full px-4 py-2 border border-blue-300 text-blue-700 rounded-lg hover:bg-blue-50">
                        <i class="fas fa-plus-circle mr-2"></i>
                        Ajouter une note
                    </a>
                    <a href="#" 
                       class="flex items-center justify-center w-full px-4 py-2 border border-green-300 text-green-700 rounded-lg hover:bg-green-50">
                        <i class="fas fa-file-pdf mr-2"></i>
                        Générer bulletin
                    </a>
                    <button onclick="confirmDelete(<?php echo $id; ?>, '<?php echo htmlspecialchars(addslashes($eleve['nom'] . ' ' . $eleve['prenom'])); ?>')" 
                            class="flex items-center justify-center w-full px-4 py-2 border border-red-300 text-red-700 rounded-lg hover:bg-red-50">
                        <i class="fas fa-trash mr-2"></i>
                        Supprimer l'élève
                    </button>
                </div>
            </div>
            
            <!-- Historique -->
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h3 class="text-sm font-medium text-gray-900 mb-4">Historique récent</h3>
                <div class="space-y-3">
                    <?php
                    $historique_stmt = $conn->prepare("
                        SELECT action, details, created_at 
                        FROM audit_log 
                        WHERE table_name = 'eleves' AND record_id = ? 
                        ORDER BY created_at DESC 
                        LIMIT 5
                    ");
                    $historique_stmt->bind_param("i", $id);
                    $historique_stmt->execute();
                    $historique_result = $historique_stmt->get_result();
                    
                    if ($historique_result->num_rows > 0):
                        while ($log = $historique_result->fetch_assoc()):
                    ?>
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
                    <?php
                        endwhile;
                    else:
                    ?>
                    <p class="text-xs text-gray-500 text-center py-2">Aucun historique</p>
                    <?php endif; ?>
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
    `;
    document.getElementById('noteModal').classList.remove('hidden');
}

function closeNoteModal() {
    document.getElementById('noteModal').classList.add('hidden');
}

function confirmDelete(id, name) {
    if (confirm(`Êtes-vous sûr de vouloir supprimer l'élève "${name}" ? Cette action est irréversible.`)) {
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