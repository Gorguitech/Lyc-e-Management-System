<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_auth();

$page_title = "Modifier un élève";
$current_page = 'eleves';

// Vérifier l'ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: list.php?message=ID invalide&message_type=error");
    exit();
}

$id = intval($_GET['id']);

// Connexion à la base de données
$db = Database::getInstance();
$conn = $db->getConnection();

// Récupérer l'élève
$stmt = $conn->prepare("SELECT * FROM eleves WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$eleve = $result->fetch_assoc();

if (!$eleve) {
    header("Location: list.php?message=Élève non trouvé&message_type=error");
    exit();
}

// Récupérer les classes
$classes_result = $conn->query("SELECT id, nom_classe FROM classes ORDER BY nom_classe");

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $data = [
        'matricule' => trim($_POST['matricule'] ?? ''),
        'nom' => trim($_POST['nom'] ?? ''),
        'prenom' => trim($_POST['prenom'] ?? ''),
        'classe_id' => $_POST['classe_id'] ? intval($_POST['classe_id']) : null,
        'date_naissance' => $_POST['date_naissance'] ?? '',
        'adresse' => trim($_POST['adresse'] ?? ''),
        'telephone' => trim($_POST['telephone'] ?? ''),
        'email' => trim($_POST['email'] ?? '')
    ];
    
    // Validation
    if (empty($data['matricule'])) $errors[] = "Le matricule est obligatoire";
    if (empty($data['nom'])) $errors[] = "Le nom est obligatoire";
    if (empty($data['prenom'])) $errors[] = "Le prénom est obligatoire";
    if (empty($data['date_naissance'])) $errors[] = "La date de naissance est obligatoire";
    
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'email n'est pas valide";
    }
    
    // Vérifier si le matricule existe déjà (pour un autre élève)
    if (empty($errors)) {
        $check_stmt = $conn->prepare("SELECT id FROM eleves WHERE matricule = ? AND id != ?");
        $check_stmt->bind_param("si", $data['matricule'], $id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $errors[] = "Ce matricule est déjà utilisé par un autre élève";
        }
    }
    
    if (empty($errors)) {
        try {
            $conn->begin_transaction();
            
            $stmt = $conn->prepare("UPDATE eleves SET 
                                   matricule = ?, 
                                   nom = ?, 
                                   prenom = ?, 
                                   classe_id = ?, 
                                   date_naissance = ?, 
                                   adresse = ?, 
                                   telephone = ?, 
                                   email = ?,
                                   updated_at = NOW()
                                   WHERE id = ?");
            $stmt->bind_param("sssissssi", 
                $data['matricule'],
                $data['nom'],
                $data['prenom'],
                $data['classe_id'],
                $data['date_naissance'],
                $data['adresse'],
                $data['telephone'],
                $data['email'],
                $id
            );
            
            if ($stmt->execute()) {
                // Journaliser l'action
                log_activity($_SESSION['user_id'], 'UPDATE', 'eleves', $id, 
                           "Modification de l'élève: {$data['nom']} {$data['prenom']}");
                
                $conn->commit();
                
                header("Location: list.php?message=Élève modifié avec succès&message_type=success");
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
            <h1 class="text-3xl font-bold text-gray-800">Modifier l'élève</h1>
            <p class="text-gray-600 mt-2">Modifiez les informations de <?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?></p>
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
                <!-- Informations personnelles -->
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4 pb-2 border-b">
                        <i class="fas fa-user-circle mr-2"></i>Informations personnelles
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Matricule <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   name="matricule" 
                                   value="<?php echo htmlspecialchars($_POST['matricule'] ?? $eleve['matricule']); ?>" 
                                   required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Date de naissance <span class="text-red-500">*</span>
                            </label>
                            <input type="date" 
                                   name="date_naissance" 
                                   value="<?php echo htmlspecialchars($_POST['date_naissance'] ?? $eleve['date_naissance']); ?>" 
                                   required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Nom <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   name="nom" 
                                   value="<?php echo htmlspecialchars($_POST['nom'] ?? $eleve['nom']); ?>" 
                                   required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Prénom <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   name="prenom" 
                                   value="<?php echo htmlspecialchars($_POST['prenom'] ?? $eleve['prenom']); ?>" 
                                   required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>
                </div>
                
                <!-- Scolarité -->
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4 pb-2 border-b">
                        <i class="fas fa-school mr-2"></i>Scolarité
                    </h3>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Classe</label>
                        <select name="classe_id" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Non assigné</option>
                            <?php 
                            $classes_result->data_seek(0); // Réinitialiser le pointeur
                            while ($classe = $classes_result->fetch_assoc()): 
                            ?>
                            <option value="<?php echo $classe['id']; ?>" 
                                    <?php echo (($_POST['classe_id'] ?? $eleve['classe_id']) == $classe['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($classe['nom_classe']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Contact -->
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4 pb-2 border-b">
                        <i class="fas fa-address-card mr-2"></i>Contact
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Adresse</label>
                            <textarea name="adresse" 
                                      rows="3"
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"><?php echo htmlspecialchars($_POST['adresse'] ?? $eleve['adresse']); ?></textarea>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Téléphone</label>
                                <input type="tel" 
                                       name="telephone" 
                                       value="<?php echo htmlspecialchars($_POST['telephone'] ?? $eleve['telephone']); ?>" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input type="email" 
                                       name="email" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? $eleve['email']); ?>" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
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
            <!-- Infos élève -->
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h3 class="text-sm font-medium text-gray-900 mb-3">Informations actuelles</h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Matricule</span>
                        <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($eleve['matricule']); ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Date d'inscription</span>
                        <span class="font-semibold text-gray-900"><?php echo format_date_fr($eleve['created_at'], 'short'); ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Dernière modification</span>
                        <span class="font-semibold text-gray-900">
                            <?php echo $eleve['updated_at'] ? format_date_fr($eleve['updated_at'], 'short') : 'Jamais'; ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Actions rapides -->
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h3 class="text-sm font-medium text-gray-900 mb-3">Actions rapides</h3>
                <div class="space-y-2">
                    <a href="view.php?id=<?php echo $id; ?>" 
                       class="flex items-center justify-center w-full px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                        <i class="fas fa-eye mr-2"></i>
                        Voir la fiche
                    </a>
                    <a href="<?php echo BASE_URL; ?>modules/notes/add.php?eleve_id=<?php echo $id; ?>" 
                       class="flex items-center justify-center w-full px-4 py-2 border border-blue-300 text-blue-700 rounded-lg hover:bg-blue-50">
                        <i class="fas fa-plus-circle mr-2"></i>
                        Ajouter une note
                    </a>
                    <button onclick="confirmDelete(<?php echo $id; ?>, '<?php echo htmlspecialchars(addslashes($eleve['nom'] . ' ' . $eleve['prenom'])); ?>')" 
                            class="flex items-center justify-center w-full px-4 py-2 border border-red-300 text-red-700 rounded-lg hover:bg-red-50">
                        <i class="fas fa-trash mr-2"></i>
                        Supprimer l'élève
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, name) {
    if (confirm(`Êtes-vous sûr de vouloir supprimer l'élève "${name}" ? Cette action est irréversible.`)) {
        window.location.href = `actions.php?action=delete&id=${id}`;
    }
}
</script>

<?php include '../../includes/footer.php'; ?>