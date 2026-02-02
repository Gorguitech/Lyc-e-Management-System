<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_auth();

$page_title = "Ajouter un enseignant";
$current_page = 'enseignants';

// Connexion à la base de données
$db = Database::getInstance();
$conn = $db->getConnection();

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
    
    // Vérifier si le matricule existe déjà
    if (empty($errors)) {
        $check_stmt = $conn->prepare("SELECT id FROM enseignants WHERE matricule = ? OR email = ?");
        $check_stmt->bind_param("ss", $data['matricule'], $data['email']);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $errors[] = "Ce matricule ou email est déjà utilisé";
        }
    }
    
    if (empty($errors)) {
        try {
            $conn->begin_transaction();
            
            $stmt = $conn->prepare("INSERT INTO enseignants (matricule, nom, prenom, matiere, specialite, telephone, email, created_by) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssi", 
                $data['matricule'],
                $data['nom'],
                $data['prenom'],
                $data['matiere'],
                $data['specialite'],
                $data['telephone'],
                $data['email'],
                $_SESSION['user_id']
            );
            
            if ($stmt->execute()) {
                $enseignant_id = $conn->insert_id;
                
                // Journaliser l'action
                log_activity($_SESSION['user_id'], 'CREATE', 'enseignants', $enseignant_id, 
                           "Ajout de l'enseignant: {$data['nom']} {$data['prenom']}");
                
                $conn->commit();
                
                header("Location: list.php?message=Enseignant ajouté avec succès&message_type=success");
                exit();
            } else {
                throw new Exception("Erreur lors de l'insertion: " . $stmt->error);
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
            <h1 class="text-3xl font-bold text-gray-800">Ajouter un enseignant</h1>
            <p class="text-gray-600 mt-2">Remplissez le formulaire pour ajouter un nouvel enseignant</p>
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
                                   value="<?php echo htmlspecialchars($_POST['matricule'] ?? ''); ?>" 
                                   required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Ex: ENS001">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Nom <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   name="nom" 
                                   value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>" 
                                   required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Prénom <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   name="prenom" 
                                   value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>" 
                                   required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Email <span class="text-red-500">*</span>
                            </label>
                            <input type="email" 
                                   name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                   required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="exemple@email.com">
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
                                   value="<?php echo htmlspecialchars($_POST['matiere'] ?? ''); ?>" 
                                   required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Ex: Mathématiques">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Spécialité</label>
                            <input type="text" 
                                   name="specialite" 
                                   value="<?php echo htmlspecialchars($_POST['specialite'] ?? ''); ?>" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Ex: Algèbre, Géométrie">
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
                               value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Ex: 0612345678">
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
                            <i class="fas fa-save mr-2"></i>Enregistrer l'enseignant
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Informations -->
    <div class="lg:col-span-1">
        <div class="space-y-6">
            <!-- Aide -->
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-5">
                <div class="flex items-center mb-3">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-900">Conseils</h3>
                    </div>
                </div>
                <ul class="space-y-2 text-sm text-blue-800">
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-blue-500 mt-0.5 mr-2"></i>
                        <span>Le matricule doit être unique pour chaque enseignant</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-blue-500 mt-0.5 mr-2"></i>
                        <span>L'email doit être valide et unique</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-blue-500 mt-0.5 mr-2"></i>
                        <span>Les champs marqués d'un * sont obligatoires</span>
                    </li>
                </ul>
            </div>
            
            <!-- Statistiques -->
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h3 class="text-sm font-medium text-gray-900 mb-3">Statistiques</h3>
                <div class="space-y-3">
                    <?php
                    $stats = $conn->query("
                        SELECT 
                            (SELECT COUNT(*) FROM enseignants) as total_enseignants,
                            (SELECT COUNT(DISTINCT matiere) FROM enseignants) as nb_matieres
                    ")->fetch_assoc();
                    ?>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Enseignants total</span>
                        <span class="font-semibold text-gray-900"><?php echo $stats['total_enseignants']; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Matières enseignées</span>
                        <span class="font-semibold text-gray-900"><?php echo $stats['nb_matieres']; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>