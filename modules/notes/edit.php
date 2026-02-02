<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_auth();

$page_title = "Modifier une note";
$current_page = 'notes';

// Vérifier l'ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: list.php?message=ID invalide&message_type=error");
    exit();
}

$id = intval($_GET['id']);

// Connexion à la base de données
$db = Database::getInstance();
$conn = $db->getConnection();

// Récupérer la note avec les informations associées
$stmt = $conn->prepare("
    SELECT n.*, 
           e.nom as nom_eleve, e.prenom as prenom_eleve, e.id as eleve_id,
           ens.nom as nom_enseignant, ens.prenom as prenom_enseignant, ens.id as enseignant_id
    FROM notes n 
    JOIN eleves e ON n.eleve_id = e.id 
    JOIN enseignants ens ON n.enseignant_id = ens.id 
    WHERE n.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$note = $result->fetch_assoc();

if (!$note) {
    header("Location: list.php?message=Note non trouvée&message_type=error");
    exit();
}

// Récupérer les élèves et enseignants pour les selects
$eleves_result = $conn->query("SELECT id, nom, prenom FROM eleves ORDER BY nom, prenom");
$enseignants_result = $conn->query("SELECT id, nom, prenom FROM enseignants ORDER BY nom, prenom");

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $data = [
        'eleve_id' => $_POST['eleve_id'] ? intval($_POST['eleve_id']) : null,
        'enseignant_id' => $_POST['enseignant_id'] ? intval($_POST['enseignant_id']) : null,
        'matiere' => trim($_POST['matiere'] ?? ''),
        'note' => $_POST['note'] ? floatval(str_replace(',', '.', $_POST['note'])) : null,
        'coefficient' => $_POST['coefficient'] ? floatval(str_replace(',', '.', $_POST['coefficient'])) : 1.00,
        'type_note' => $_POST['type_note'] ?? 'devoir',
        'date_note' => $_POST['date_note'] ?? date('Y-m-d'),
        'commentaire' => trim($_POST['commentaire'] ?? '')
    ];
    
    // Validation
    if (empty($data['eleve_id'])) $errors[] = "Veuillez sélectionner un élève";
    if (empty($data['enseignant_id'])) $errors[] = "Veuillez sélectionner un enseignant";
    if (empty($data['matiere'])) $errors[] = "La matière est obligatoire";
    if ($data['note'] === null) $errors[] = "La note est obligatoire";
    
    if ($data['note'] !== null && ($data['note'] < 0 || $data['note'] > 20)) {
        $errors[] = "La note doit être comprise entre 0 et 20";
    }
    
    if ($data['coefficient'] !== null && ($data['coefficient'] < 0.1 || $data['coefficient'] > 5)) {
        $errors[] = "Le coefficient doit être compris entre 0.1 et 5";
    }
    
    if (empty($errors)) {
        try {
            $conn->begin_transaction();
            
            $stmt = $conn->prepare("UPDATE notes SET 
                                   eleve_id = ?, 
                                   enseignant_id = ?, 
                                   matiere = ?, 
                                   note = ?, 
                                   coefficient = ?, 
                                   type_note = ?, 
                                   date_note = ?, 
                                   commentaire = ?
                                   WHERE id = ?");
            $stmt->bind_param("iisddsssi", 
                $data['eleve_id'],
                $data['enseignant_id'],
                $data['matiere'],
                $data['note'],
                $data['coefficient'],
                $data['type_note'],
                $data['date_note'],
                $data['commentaire'],
                $id
            );
            
            if ($stmt->execute()) {
                // Récupérer les noms pour le log
                $eleve_stmt = $conn->prepare("SELECT nom, prenom FROM eleves WHERE id = ?");
                $eleve_stmt->bind_param("i", $data['eleve_id']);
                $eleve_stmt->execute();
                $eleve = $eleve_stmt->get_result()->fetch_assoc();
                
                // Journaliser l'action
                log_activity($_SESSION['user_id'], 'UPDATE', 'notes', $id, 
                           "Modification de note: {$eleve['nom']} {$eleve['prenom']} - {$data['matiere']} - {$data['note']}/20");
                
                $conn->commit();
                
                header("Location: list.php?message=Note modifiée avec succès&message_type=success");
                exit();
            } else {
                throw new Exception("Erreur lors de la modification: " . $stmt->error);
            }
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Une erreur est survenue: " . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
?>

<div class="mb-8">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Modifier une note</h1>
            <p class="text-gray-600 mt-2">Modifiez la note attribuée</p>
        </div>
        <a href="list.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
            <i class="fas fa-arrow-left mr-2"></i>
            Retour à la liste
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Formulaire -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-sm p-6">
            <?php if (!empty($errors)): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-red-500"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">
                            Veuillez corriger les erreurs suivantes:
                        </h3>
                        <div class="mt-2 text-sm text-red-700">
                            <ul class="list-disc pl-5 space-y-1">
                                <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="space-y-6">
                <!-- Sélection élève et enseignant -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Élève <span class="text-red-500">*</span>
                        </label>
                        <select name="eleve_id" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Sélectionnez un élève</option>
                            <?php while ($eleve = $eleves_result->fetch_assoc()): ?>
                            <option value="<?php echo $eleve['id']; ?>" 
                                    <?php echo ($_POST['eleve_id'] ?? $note['eleve_id']) == $eleve['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Enseignant <span class="text-red-500">*</span>
                        </label>
                        <select name="enseignant_id" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Sélectionnez un enseignant</option>
                            <?php 
                            $enseignants_result->data_seek(0); // Réinitialiser le pointeur
                            while ($enseignant = $enseignants_result->fetch_assoc()): 
                            ?>
                            <option value="<?php echo $enseignant['id']; ?>" 
                                    <?php echo ($_POST['enseignant_id'] ?? $note['enseignant_id']) == $enseignant['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($enseignant['nom'] . ' ' . $enseignant['prenom']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Informations de la note -->
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4 pb-2 border-b">
                        <i class="fas fa-clipboard-check mr-2"></i>Informations de la note
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Matière <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   name="matiere" 
                                   value="<?php echo htmlspecialchars($_POST['matiere'] ?? $note['matiere']); ?>" 
                                   required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Note /20 <span class="text-red-500">*</span>
                            </label>
                            <input type="number" 
                                   name="note" 
                                   value="<?php echo htmlspecialchars($_POST['note'] ?? $note['note']); ?>" 
                                   required
                                   min="0" 
                                   max="20" 
                                   step="0.1"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Coefficient</label>
                            <input type="number" 
                                   name="coefficient" 
                                   value="<?php echo htmlspecialchars($_POST['coefficient'] ?? $note['coefficient']); ?>" 
                                   min="0.1" 
                                   max="5" 
                                   step="0.1"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Type de note</label>
                            <select name="type_note" 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="devoir" <?php echo ($_POST['type_note'] ?? $note['type_note']) === 'devoir' ? 'selected' : ''; ?>>Devoir</option>
                                <option value="examen" <?php echo ($_POST['type_note'] ?? $note['type_note']) === 'examen' ? 'selected' : ''; ?>>Examen</option>
                                <option value="participation" <?php echo ($_POST['type_note'] ?? $note['type_note']) === 'participation' ? 'selected' : ''; ?>>Participation</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date de la note</label>
                            <input type="date" 
                                   name="date_note" 
                                   value="<?php echo htmlspecialchars($_POST['date_note'] ?? $note['date_note']); ?>" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>
                </div>
                
                <!-- Commentaire -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Commentaire (optionnel)</label>
                    <textarea name="commentaire" 
                              rows="3"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                              placeholder="Commentaires sur la note..."><?php echo htmlspecialchars($_POST['commentaire'] ?? $note['commentaire']); ?></textarea>
                </div>
                
                <!-- Actions -->
                <div class="pt-6 border-t border-gray-200">
                    <div class="flex justify-end space-x-3">
                        <a href="list.php" 
                           class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-medium">
                            Annuler
                        </a>
                        <button type="submit" 
                                class="px-4 py-2 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg hover:from-blue-700 hover:to-purple-700 font-medium shadow-md">
                            <i class="fas fa-save mr-2"></i>Enregistrer les modifications
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Informations -->
    <div class="lg:col-span-1">
        <div class="space-y-6">
            <!-- Infos note -->
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h3 class="text-sm font-medium text-gray-900 mb-3">Informations actuelles</h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Élève</span>
                        <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($note['nom_eleve'] . ' ' . $note['prenom_eleve']); ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Enseignant</span>
                        <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($note['nom_enseignant'] . ' ' . $note['prenom_enseignant']); ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Date d'ajout</span>
                        <span class="font-semibold text-gray-900"><?php echo format_date_fr($note['created_at'], 'short'); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Historique des modifications -->
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h3 class="text-sm font-medium text-gray-900 mb-3">Historique</h3>
                <div class="space-y-3">
                    <?php
                    $historique_stmt = $conn->prepare("
                        SELECT action, details, created_at 
                        FROM audit_log 
                        WHERE table_name = 'notes' AND record_id = ? 
                        ORDER BY created_at DESC 
                        LIMIT 3
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
            
            <!-- Actions rapides -->
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h3 class="text-sm font-medium text-gray-900 mb-3">Actions rapides</h3>
                <div class="space-y-2">
                    <a href="list.php" 
                       class="flex items-center justify-center w-full px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Retour à la liste
                    </a>
                    <a href="<?php echo BASE_URL; ?>modules/eleves/view.php?id=<?php echo $note['eleve_id']; ?>" 
                       class="flex items-center justify-center w-full px-4 py-2 border border-blue-300 text-blue-700 rounded-lg hover:bg-blue-50">
                        <i class="fas fa-user mr-2"></i>
                        Voir fiche élève
                    </a>
                    <button onclick="confirmDelete(<?php echo $id; ?>, '<?php echo htmlspecialchars(addslashes($note['nom_eleve'] . ' ' . $note['prenom_eleve'] . ' - ' . $note['matiere'] . ' - ' . $note['note'] . '/20')); ?>')" 
                            class="flex items-center justify-center w-full px-4 py-2 border border-red-300 text-red-700 rounded-lg hover:bg-red-50">
                        <i class="fas fa-trash mr-2"></i>
                        Supprimer la note
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, name) {
    if (confirm(`Êtes-vous sûr de vouloir supprimer la note "${name}" ? Cette action est irréversible.`)) {
        window.location.href = `actions.php?action=delete&id=${id}`;
    }
}
</script>

<?php include '../../includes/footer.php'; ?>