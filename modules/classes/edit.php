<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_auth();

$page_title = "Modifier une classe";
$current_page = 'classes';

// Vérifier l'ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: list.php?message=ID invalide&message_type=error");
    exit();
}

$id = intval($_GET['id']);

// Connexion à la base de données
$db = Database::getInstance();
$conn = $db->getConnection();

// Récupérer la classe
$stmt = $conn->prepare("SELECT * FROM classes WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$classe = $result->fetch_assoc();

if (!$classe) {
    header("Location: list.php?message=Classe non trouvée&message_type=error");
    exit();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $data = [
        'nom_classe' => trim($_POST['nom_classe'] ?? ''),
        'niveau' => $_POST['niveau'] ?? '',
        'section' => $_POST['section'] ?? ''
    ];
    
    // Validation
    if (empty($data['nom_classe'])) $errors[] = "Le nom de la classe est obligatoire";
    
    // Vérifier si la classe existe déjà (pour une autre classe)
    if (empty($errors)) {
        $check_stmt = $conn->prepare("SELECT id FROM classes WHERE nom_classe = ? AND id != ?");
        $check_stmt->bind_param("si", $data['nom_classe'], $id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $errors[] = "Cette classe existe déjà";
        }
    }
    
    if (empty($errors)) {
        try {
            $conn->begin_transaction();
            
            $stmt = $conn->prepare("UPDATE classes SET 
                                   nom_classe = ?, 
                                   niveau = ?, 
                                   section = ?
                                   WHERE id = ?");
            $stmt->bind_param("sssi", 
                $data['nom_classe'],
                $data['niveau'],
                $data['section'],
                $id
            );
            
            if ($stmt->execute()) {
                // Journaliser l'action
                log_activity($_SESSION['user_id'], 'UPDATE', 'classes', $id, 
                           "Modification de la classe: {$data['nom_classe']}");
                
                $conn->commit();
                
                header("Location: list.php?message=Classe modifiée avec succès&message_type=success");
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
            <h1 class="text-3xl font-bold text-gray-800">Modifier la classe</h1>
            <p class="text-gray-600 mt-2">Modifiez les informations de <?php echo htmlspecialchars($classe['nom_classe']); ?></p>
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
                <!-- Informations de la classe -->
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4 pb-2 border-b">
                        <i class="fas fa-school mr-2"></i>Informations de la classe
                    </h3>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Nom de la classe <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               name="nom_classe" 
                               value="<?php echo htmlspecialchars($_POST['nom_classe'] ?? $classe['nom_classe']); ?>" 
                               required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Niveau</label>
                            <select name="niveau" 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Sélectionnez un niveau</option>
                                <option value="Terminale" <?php echo ($_POST['niveau'] ?? $classe['niveau']) === 'Terminale' ? 'selected' : ''; ?>>Terminale</option>
                                <option value="Première" <?php echo ($_POST['niveau'] ?? $classe['niveau']) === 'Première' ? 'selected' : ''; ?>>Première</option>
                                <option value="Seconde" <?php echo ($_POST['niveau'] ?? $classe['niveau']) === 'Seconde' ? 'selected' : ''; ?>>Seconde</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                            <input type="text" 
                                   name="section" 
                                   value="<?php echo htmlspecialchars($_POST['section'] ?? $classe['section']); ?>" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>
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
            <!-- Infos classe -->
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h3 class="text-sm font-medium text-gray-900 mb-3">Informations actuelles</h3>
                <div class="space-y-3">
                    <?php
                    // Nombre d'élèves dans cette classe
                    $eleves_stmt = $conn->prepare("SELECT COUNT(*) as nb_eleves FROM eleves WHERE classe_id = ?");
                    $eleves_stmt->bind_param("i", $id);
                    $eleves_stmt->execute();
                    $nb_eleves = $eleves_stmt->get_result()->fetch_assoc()['nb_eleves'];
                    ?>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Élèves inscrits</span>
                        <span class="font-semibold text-gray-900"><?php echo $nb_eleves; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Date création</span>
                        <span class="font-semibold text-gray-900"><?php echo format_date_fr($classe['created_at'], 'short'); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Liste des élèves -->
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h3 class="text-sm font-medium text-gray-900 mb-3">Élèves de cette classe</h3>
                <div class="space-y-3">
                    <?php
                    $eleves_liste = $conn->prepare("
                        SELECT nom, prenom, matricule 
                        FROM eleves 
                        WHERE classe_id = ? 
                        ORDER BY nom, prenom 
                        LIMIT 5
                    ");
                    $eleves_liste->bind_param("i", $id);
                    $eleves_liste->execute();
                    $eleves_result = $eleves_liste->get_result();
                    
                    if ($eleves_result->num_rows > 0):
                        while ($eleve = $eleves_result->fetch_assoc()):
                    ?>
                    <div class="flex items-center">
                        <div class="flex-shrink-0 h-8 w-8 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-user text-blue-600 text-xs"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?></p>
                            <p class="text-xs text-gray-500"><?php echo $eleve['matricule']; ?></p>
                        </div>
                    </div>
                    <?php
                        endwhile;
                        
                        if ($nb_eleves > 5):
                    ?>
                    <div class="pt-2 border-t">
                        <a href="<?php echo BASE_URL; ?>modules/eleves/list.php?classe_id=<?php echo $id; ?>" 
                           class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                            Voir tous les élèves (<?php echo $nb_eleves; ?>)
                        </a>
                    </div>
                    <?php
                        endif;
                    else:
                    ?>
                    <p class="text-sm text-gray-500 text-center py-2">Aucun élève dans cette classe</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Actions rapides -->
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h3 class="text-sm font-medium text-gray-900 mb-3">Actions rapides</h3>
                <div class="space-y-2">
                    <a href="view.php?id=<?php echo $id; ?>" 
                       class="flex items-center justify-center w-full px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                        <i class="fas fa-eye mr-2"></i>
                        Voir détails
                    </a>
                    <a href="<?php echo BASE_URL; ?>modules/eleves/add.php?classe_id=<?php echo $id; ?>" 
                       class="flex items-center justify-center w-full px-4 py-2 border border-blue-300 text-blue-700 rounded-lg hover:bg-blue-50">
                        <i class="fas fa-user-plus mr-2"></i>
                        Ajouter un élève
                    </a>
                    <button onclick="confirmDelete(<?php echo $id; ?>, '<?php echo htmlspecialchars(addslashes($classe['nom_classe'])); ?>')" 
                            class="flex items-center justify-center w-full px-4 py-2 border border-red-300 text-red-700 rounded-lg hover:bg-red-50">
                        <i class="fas fa-trash mr-2"></i>
                        Supprimer la classe
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, name) {
    if (confirm(`Êtes-vous sûr de vouloir supprimer la classe "${name}" ? Cette action affectera les élèves de cette classe.`)) {
        window.location.href = `actions.php?action=delete&id=${id}`;
    }
}
</script>

<?php include '../../includes/footer.php'; ?>