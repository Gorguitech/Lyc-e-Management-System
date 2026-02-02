<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_auth();

$page_title = "Modifier un enseignant";
$current_page = 'enseignants';

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

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $data = [
        'matricule' => trim($_POST['matricule'] ?? ''),
        'nom' => trim($_POST['nom'] ?? ''),
        'prenom' => trim($_POST['prenom'] ?? ''),
        'matiere' => trim($_POST['matiere'] ?? ''),
        'specialite' => trim($_POST['specialite'] ?? ''),
        'telephone' => trim($_POST['telephone'] ?? ''),
        'email' => trim($_POST['email'] ?? '')
    ];
    
    // Validation
    if (empty($data['matricule'])) $errors[] = "Le matricule est obligatoire";
    if (empty($data['nom'])) $errors[] = "Le nom est obligatoire";
    if (empty($data['prenom'])) $errors[] = "Le prénom est obligatoire";
    if (empty($data['matiere'])) $errors[] = "La matière est obligatoire";
    if (empty($data['email'])) $errors[] = "L'email est obligatoire";
    
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'email n'est pas valide";
    }
    
    // Vérifier si le matricule existe déjà (pour un autre enseignant)
    if (empty($errors)) {
        $check_stmt = $conn->prepare("SELECT id FROM enseignants WHERE (matricule = ? OR email = ?) AND id != ?");
        $check_stmt->bind_param("ssi", $data['matricule'], $data['email'], $id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $errors[] = "Ce matricule ou email est déjà utilisé par un autre enseignant";
        }
    }
    
    if (empty($errors)) {
        try {
            $conn->begin_transaction();
            
            $stmt = $conn->prepare("UPDATE enseignants SET 
                                   matricule = ?, 
                                   nom = ?, 
                                   prenom = ?, 
                                   matiere = ?, 
                                   specialite = ?, 
                                   telephone = ?, 
                                   email = ?
                                   WHERE id = ?");
            $stmt->bind_param("sssssssi", 
                $data['matricule'],
                $data['nom'],
                $data['prenom'],
                $data['matiere'],
                $data['specialite'],
                $data['telephone'],
                $data['email'],
                $id
            );
            
            if ($stmt->execute()) {
                // Journaliser l'action
                log_activity($_SESSION['user_id'], 'UPDATE', 'enseignants', $id, 
                           "Modification de l'enseignant: {$data['nom']} {$data['prenom']}");
                
                $conn->commit();
                
                header("Location: list.php?message=Enseignant modifié avec succès&message_type=success");
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
            <h1 class="text-3xl font-bold text-gray-800">Modifier l'enseignant</h1>
            <p class="text-gray-600 mt-2">Modifiez les informations de <?php echo htmlspecialchars($enseignant['nom'] . ' ' . $enseignant['prenom']); ?></p>
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
                        <i class="fas fa-user-tie mr-2"></i>Informations personnelles
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Matricule <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   name="matricule" 
                                   value="<?php echo htmlspecialchars($_POST['matricule'] ?? $enseignant['matricule']); ?>" 
                                   required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Nom <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   name="nom" 
                                   value="<?php echo htmlspecialchars($_POST['nom'] ?? $enseignant['nom']); ?>" 
                                   required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Prénom <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   name="prenom" 
                                   value="<?php echo htmlspecialchars($_POST['prenom'] ?? $enseignant['prenom']); ?>" 
                                   required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Email <span class="text-red-500">*</span>
                            </label>
                            <input type="email" 
                                   name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? $enseignant['email']); ?>" 
                                   required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>
                </div>
                
                <!-- Informations professionnelles -->
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4 pb-2 border-b">
                        <i class="fas fa-graduation-cap mr-2"></i>Informations professionnelles
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Matière principale <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   name="matiere" 
                                   value="<?php echo htmlspecialchars($_POST['matiere'] ?? $enseignant['matiere']); ?>" 
                                   required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Spécialité</label>
                            <input type="text" 
                                   name="specialite" 
                                   value="<?php echo htmlspecialchars($_POST['specialite'] ?? $enseignant['specialite']); ?>" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>
                </div>
                
                <!-- Contact -->
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4 pb-2 border-b">
                        <i class="fas fa-phone-alt mr-2"></i>Contact
                    </h3>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Téléphone</label>
                        <input type="tel" 
                               name="telephone" 
                               value="<?php echo htmlspecialchars($_POST['telephone'] ?? $enseignant['telephone']); ?>" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
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
            <!-- Infos enseignant -->
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h3 class="text-sm font-medium text-gray-900 mb-3">Informations actuelles</h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Matricule</span>
                        <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($enseignant['matricule']); ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Date d'inscription</span>
                        <span class="font-semibold text-gray-900"><?php echo format_date_fr($enseignant['created_at'], 'short'); ?></span>
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
                    <a href="<?php echo BASE_URL; ?>modules/notes/add.php?enseignant_id=<?php echo $id; ?>" 
                       class="flex items-center justify-center w-full px-4 py-2 border border-blue-300 text-blue-700 rounded-lg hover:bg-blue-50">
                        <i class="fas fa-plus-circle mr-2"></i>
                        Ajouter une note
                    </a>
                    <button onclick="confirmDelete(<?php echo $id; ?>, '<?php echo htmlspecialchars(addslashes($enseignant['nom'] . ' ' . $enseignant['prenom'])); ?>')" 
                            class="flex items-center justify-center w-full px-4 py-2 border border-red-300 text-red-700 rounded-lg hover:bg-red-50">
                        <i class="fas fa-trash mr-2"></i>
                        Supprimer l'enseignant
                    </button>
                </div>
            </div>
            
            <!-- Statistiques -->
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h3 class="text-sm font-medium text-gray-900 mb-3">Statistiques</h3>
                <div class="space-y-3">
                    <?php
                    // Nombre de notes attribuées par cet enseignant
                    $notes_stmt = $conn->prepare("SELECT COUNT(*) as nb_notes FROM notes WHERE enseignant_id = ?");
                    $notes_stmt->bind_param("i", $id);
                    $notes_stmt->execute();
                    $notes_count = $notes_stmt->get_result()->fetch_assoc()['nb_notes'];
                    ?>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Notes attribuées</span>
                        <span class="font-semibold text-gray-900"><?php echo $notes_count; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Moyenne attribuée</span>
                        <span class="font-semibold text-gray-900">
                            <?php
                            $moyenne_stmt = $conn->prepare("SELECT AVG(note) as moyenne FROM notes WHERE enseignant_id = ?");
                            $moyenne_stmt->bind_param("i", $id);
                            $moyenne_stmt->execute();
                            $moyenne = $moyenne_stmt->get_result()->fetch_assoc()['moyenne'] ?? 0;
                            echo number_format($moyenne, 2) . '/20';
                            ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, name) {
    if (confirm(`Êtes-vous sûr de vouloir supprimer l'enseignant "${name}" ?`)) {
        window.location.href = `actions.php?action=delete&id=${id}`;
    }
}
</script>

<?php include '../../includes/footer.php'; ?>