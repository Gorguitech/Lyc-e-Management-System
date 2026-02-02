<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_auth();

$page_title = "Ajouter un élève";
$current_page = 'eleves';

// Connexion à la base de données
$db = Database::getInstance();
$conn = $db->getConnection();

// Récupérer les classes pour le select
$classes_result = $conn->query("SELECT id, nom_classe FROM classes ORDER BY nom_classe");

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation des données
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
    
    // Vérifier si le matricule existe déjà
    if (empty($errors)) {
        $check_stmt = $conn->prepare("SELECT id FROM eleves WHERE matricule = ?");
        $check_stmt->bind_param("s", $data['matricule']);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $errors[] = "Ce matricule est déjà utilisé";
        }
    }
    
    if (empty($errors)) {
        try {
            $conn->begin_transaction();
            
            $stmt = $conn->prepare("INSERT INTO eleves (matricule, nom, prenom, classe_id, date_naissance, adresse, telephone, email, created_by) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssissssi", 
                $data['matricule'],
                $data['nom'],
                $data['prenom'],
                $data['classe_id'],
                $data['date_naissance'],
                $data['adresse'],
                $data['telephone'],
                $data['email'],
                $_SESSION['user_id']
            );
            
            if ($stmt->execute()) {
                $eleve_id = $conn->insert_id;
                
                // Journaliser l'action
                log_activity($_SESSION['user_id'], 'CREATE', 'eleves', $eleve_id, 
                           "Ajout de l'élève: {$data['nom']} {$data['prenom']}");
                
                $conn->commit();
                
                // Redirection avec message de succès
                header("Location: list.php?message=Élève ajouté avec succès&message_type=success");
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
            <h1 class="text-3xl font-bold text-gray-800">Ajouter un élève</h1>
            <p class="text-gray-600 mt-2">Remplissez le formulaire pour inscrire un nouvel élève</p>
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
                                   value="<?php echo htmlspecialchars($_POST['matricule'] ?? ''); ?>" 
                                   required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Ex: 20240001">
                            <p class="mt-1 text-xs text-gray-500">Identifiant unique de l'élève</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Date de naissance <span class="text-red-500">*</span>
                            </label>
                            <input type="date" 
                                   name="date_naissance" 
                                   value="<?php echo htmlspecialchars($_POST['date_naissance'] ?? ''); ?>" 
                                   required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Nom <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   name="nom" 
                                   value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>" 
                                   required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Nom de famille">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Prénom <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   name="prenom" 
                                   value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>" 
                                   required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Prénom">
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
                            <option value="">Sélectionnez une classe</option>
                            <?php while ($classe = $classes_result->fetch_assoc()): ?>
                            <option value="<?php echo $classe['id']; ?>" 
                                    <?php echo ($_POST['classe_id'] ?? '') == $classe['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($classe['nom_classe']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Optionnel - peut être assigné plus tard</p>
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
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                      placeholder="Adresse complète"><?php echo htmlspecialchars($_POST['adresse'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Téléphone</label>
                                <input type="tel" 
                                       name="telephone" 
                                       value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="Ex: 0612345678">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input type="email" 
                                       name="email" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="exemple@email.com">
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
                            <i class="fas fa-save mr-2"></i>Enregistrer l'élève
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
                        <span>Le matricule doit être unique pour chaque élève</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-blue-500 mt-0.5 mr-2"></i>
                        <span>Les champs marqués d'un * sont obligatoires</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-blue-500 mt-0.5 mr-2"></i>
                        <span>L'élève peut être assigné à une classe ultérieurement</span>
                    </li>
                </ul>
            </div>
            
            <!-- Statistiques rapides -->
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h3 class="text-sm font-medium text-gray-900 mb-3">Statistiques</h3>
                <div class="space-y-3">
                    <?php
                    $stats = $conn->query("
                        SELECT 
                            (SELECT COUNT(*) FROM eleves) as total_eleves,
                            (SELECT COUNT(*) FROM classes) as total_classes,
                            (SELECT AVG(note) FROM notes) as moyenne_generale
                    ")->fetch_assoc();
                    ?>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Élèves total</span>
                        <span class="font-semibold text-gray-900"><?php echo $stats['total_eleves']; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Classes actives</span>
                        <span class="font-semibold text-gray-900"><?php echo $stats['total_classes']; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Moyenne générale</span>
                        <span class="font-semibold text-gray-900"><?php echo number_format($stats['moyenne_generale'] ?? 0, 2); ?>/20</span>
                    </div>
                </div>
            </div>
            
            <!-- Derniers ajouts -->
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h3 class="text-sm font-medium text-gray-900 mb-3">Derniers élèves ajoutés</h3>
                <div class="space-y-3">
                    <?php
                    $recent = $conn->query("
                        SELECT nom, prenom, matricule, created_at 
                        FROM eleves 
                        ORDER BY created_at DESC 
                        LIMIT 3
                    ");
                    
                    if ($recent->num_rows > 0):
                        while ($row = $recent->fetch_assoc()):
                    ?>
                    <div class="flex items-center">
                        <div class="flex-shrink-0 h-8 w-8 bg-gray-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-user text-gray-500 text-xs"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['nom'] . ' ' . $row['prenom']); ?></p>
                            <p class="text-xs text-gray-500"><?php echo $row['matricule']; ?></p>
                        </div>
                    </div>
                    <?php
                        endwhile;
                    else:
                    ?>
                    <p class="text-sm text-gray-500 text-center py-2">Aucun élève enregistré</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>