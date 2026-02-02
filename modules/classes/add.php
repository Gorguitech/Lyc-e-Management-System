<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_auth();

$page_title = "Créer une classe";
$current_page = 'classes';

// Connexion à la base de données
$db = Database::getInstance();
$conn = $db->getConnection();

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
    
    // Vérifier si la classe existe déjà
    if (empty($errors)) {
        $check_stmt = $conn->prepare("SELECT id FROM classes WHERE nom_classe = ?");
        $check_stmt->bind_param("s", $data['nom_classe']);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $errors[] = "Cette classe existe déjà";
        }
    }
    
    if (empty($errors)) {
        try {
            $conn->begin_transaction();
            
            $stmt = $conn->prepare("INSERT INTO classes (nom_classe, niveau, section, created_by) 
                                   VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", 
                $data['nom_classe'],
                $data['niveau'],
                $data['section'],
                $_SESSION['user_id']
            );
            
            if ($stmt->execute()) {
                $classe_id = $conn->insert_id;
                
                // Journaliser l'action
                log_activity($_SESSION['user_id'], 'CREATE', 'classes', $classe_id, 
                           "Création de la classe: {$data['nom_classe']}");
                
                $conn->commit();
                
                header("Location: list.php?message=Classe créée avec succès&message_type=success");
                exit();
            } else {
                throw new Exception("Erreur lors de la création: " . $stmt->error);
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
            <h1 class="text-3xl font-bold text-gray-800">Créer une classe</h1>
            <p class="text-gray-600 mt-2">Ajoutez une nouvelle classe à l'établissement</p>
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
                               value="<?php echo htmlspecialchars($_POST['nom_classe'] ?? ''); ?>" 
                               required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Ex: Terminale A, Première B, Seconde C">
                        <p class="mt-1 text-xs text-gray-500">Doit être unique dans l'établissement</p>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Niveau</label>
                            <select name="niveau" 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Sélectionnez un niveau</option>
                                <option value="Terminale" <?php echo ($_POST['niveau'] ?? '') === 'Terminale' ? 'selected' : ''; ?>>Terminale</option>
                                <option value="Première" <?php echo ($_POST['niveau'] ?? '') === 'Première' ? 'selected' : ''; ?>>Première</option>
                                <option value="Seconde" <?php echo ($_POST['niveau'] ?? '') === 'Seconde' ? 'selected' : ''; ?>>Seconde</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                            <input type="text" 
                                   name="section" 
                                   value="<?php echo htmlspecialchars($_POST['section'] ?? ''); ?>" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Ex: Scientifique, Littéraire">
                        </div>
                    </div>
                </div>
                
                <!-- Exemples -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h4 class="text-sm font-medium text-blue-900 mb-2">
                        <i class="fas fa-lightbulb mr-1"></i>Exemples de dénominations
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2 text-xs text-blue-700">
                        <div class="bg-white p-2 rounded">
                            <div class="font-medium">Terminale A</div>
                            <div class="text-blue-600">Niveau: Terminale</div>
                        </div>
                        <div class="bg-white p-2 rounded">
                            <div class="font-medium">Première B</div>
                            <div class="text-blue-600">Niveau: Première</div>
                        </div>
                        <div class="bg-white p-2 rounded">
                            <div class="font-medium">Seconde C</div>
                            <div class="text-blue-600">Niveau: Seconde</div>
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
                            <i class="fas fa-save mr-2"></i>Créer la classe
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Informations -->
    <div class="lg:col-span-1">
        <div class="space-y-6">
            <!-- Statistiques -->
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h3 class="text-sm font-medium text-gray-900 mb-3">Statistiques</h3>
                <div class="space-y-3">
                    <?php
                    $stats = $conn->query("
                        SELECT 
                            (SELECT COUNT(*) FROM classes) as total_classes,
                            (SELECT COUNT(*) FROM eleves) as total_eleves,
                            (SELECT COUNT(DISTINCT niveau) FROM classes) as nb_niveaux
                    ")->fetch_assoc();
                    
                    $moyenne_par_classe = $stats['total_classes'] > 0 ? 
                        round($stats['total_eleves'] / $stats['total_classes'], 1) : 0;
                    ?>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Classes total</span>
                        <span class="font-semibold text-gray-900"><?php echo $stats['total_classes']; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Élèves total</span>
                        <span class="font-semibold text-gray-900"><?php echo $stats['total_eleves']; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Niveaux</span>
                        <span class="font-semibold text-gray-900"><?php echo $stats['nb_niveaux']; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Moyenne/Classe</span>
                        <span class="font-semibold text-gray-900"><?php echo $moyenne_par_classe; ?> élèves</span>
                    </div>
                </div>
            </div>
            
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
                        <span>Le nom de classe doit être unique</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-blue-500 mt-0.5 mr-2"></i>
                        <span>Utilisez une nomenclature cohérente</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-blue-500 mt-0.5 mr-2"></i>
                        <span>Vous pouvez assigner les élèves ultérieurement</span>
                    </li>
                </ul>
            </div>
            
            <!-- Dernières classes -->
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h3 class="text-sm font-medium text-gray-900 mb-3">Dernières classes créées</h3>
                <div class="space-y-3">
                    <?php
                    $recent = $conn->query("
                        SELECT nom_classe, niveau, created_at 
                        FROM classes 
                        ORDER BY created_at DESC 
                        LIMIT 3
                    ");
                    
                    if ($recent->num_rows > 0):
                        while ($row = $recent->fetch_assoc()):
                    ?>
                    <div class="flex items-center">
                        <div class="flex-shrink-0 h-8 w-8 bg-purple-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-school text-purple-600 text-xs"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['nom_classe']); ?></p>
                            <p class="text-xs text-gray-500"><?php echo $row['niveau'] ?: 'Non spécifié'; ?></p>
                        </div>
                    </div>
                    <?php
                        endwhile;
                    else:
                    ?>
                    <p class="text-sm text-gray-500 text-center py-2">Aucune classe créée</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>