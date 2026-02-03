<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_auth();

// Vérifier que seul un admin peut ajouter des utilisateurs
if ($_SESSION['user_role'] !== 'super_admin' && $_SESSION['user_role'] !== 'admin') {
    header("Location: " . BASE_URL . "dashboard.php?message=Accès non autorisé&message_type=error");
    exit();
}

$page_title = "Ajouter un utilisateur";
$current_page = 'users';

// Connexion à la base de données
$db = Database::getInstance();
$conn = $db->getConnection();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $data = [
        'username' => trim($_POST['username'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'email' => trim($_POST['email'] ?? ''),
        'role' => $_POST['role'] ?? 'admin',
        'full_name' => trim($_POST['full_name'] ?? '')
    ];
    
    // Validation
    if (empty($data['username'])) $errors[] = "Le nom d'utilisateur est obligatoire";
    if (empty($data['password'])) $errors[] = "Le mot de passe est obligatoire";
    if (empty($data['email'])) $errors[] = "L'email est obligatoire";
    if (empty($data['full_name'])) $errors[] = "Le nom complet est obligatoire";
    
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'email n'est pas valide";
    }
    
    if ($data['password'] !== $data['confirm_password']) {
        $errors[] = "Les mots de passe ne correspondent pas";
    }
    
    if (strlen($data['password']) < 6) {
        $errors[] = "Le mot de passe doit contenir au moins 6 caractères";
    }
    
    // Vérifier si l'utilisateur existe déjà
    if (empty($errors)) {
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check_stmt->bind_param("ss", $data['username'], $data['email']);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $errors[] = "Ce nom d'utilisateur ou email est déjà utilisé";
        }
    }
    
    if (empty($errors)) {
        try {
            $conn->begin_transaction();
            
            // Utiliser la classe Auth pour créer l'utilisateur
            require_once '../../includes/auth.php';
            $auth = new Auth();
            
            $result = $auth->createUser([
                'username' => $data['username'],
                'password' => $data['password'],
                'email' => $data['email'],
                'role' => $data['role'],
                'full_name' => $data['full_name']
            ], $_SESSION['user_id']);
            
            if ($result['success']) {
                $conn->commit();
                header("Location: list.php?message=Utilisateur créé avec succès&message_type=success");
                exit();
            } else {
                throw new Exception($result['message']);
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
            <h1 class="text-3xl font-bold text-gray-800">Ajouter un utilisateur</h1>
            <p class="text-gray-600 mt-2">Créez un nouveau compte utilisateur</p>
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
                                Nom complet <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   name="full_name" 
                                   value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" 
                                   required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Ex: Jean Dupont">
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
                
                <!-- Informations de connexion -->
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4 pb-2 border-b">
                        <i class="fas fa-key mr-2"></i>Informations de connexion
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Nom d'utilisateur <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   name="username" 
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                                   required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Ex: jean.dupont">
                            <p class="mt-1 text-xs text-gray-500">Doit être unique</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Rôle <span class="text-red-500">*</span>
                            </label>
                            <select name="role" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="admin" <?php echo ($_POST['role'] ?? 'admin') === 'admin' ? 'selected' : ''; ?>>Administrateur</option>
                                <option value="enseignant" <?php echo ($_POST['role'] ?? '') === 'enseignant' ? 'selected' : ''; ?>>Enseignant</option>
                                <?php if ($_SESSION['user_role'] === 'super_admin'): ?>
                                <option value="super_admin" <?php echo ($_POST['role'] ?? '') === 'super_admin' ? 'selected' : ''; ?>>Super Administrateur</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Mot de passe <span class="text-red-500">*</span>
                            </label>
                            <input type="password" 
                                   name="password" 
                                   required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Minimum 6 caractères">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Confirmer le mot de passe <span class="text-red-500">*</span>
                            </label>
                            <input type="password" 
                                   name="confirm_password" 
                                   required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Répétez le mot de passe">
                        </div>
                    </div>
                </div>
                
                <!-- Rôles et permissions -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h4 class="text-sm font-medium text-blue-900 mb-2">
                        <i class="fas fa-shield-alt mr-1"></i>Explications des rôles
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2 text-xs">
                        <div class="bg-white p-2 rounded">
                            <div class="font-medium text-blue-700">Super Admin</div>
                            <div class="text-blue-600 mt-1">• Accès complet<br>• Gestion des admins<br>• Journal d'audit</div>
                        </div>
                        <div class="bg-white p-2 rounded">
                            <div class="font-medium text-green-700">Administrateur</div>
                            <div class="text-green-600 mt-1">• Gestion des données<br>• Création d'utilisateurs<br>• Pas d'audit</div>
                        </div>
                        <div class="bg-white p-2 rounded">
                            <div class="font-medium text-purple-700">Enseignant</div>
                            <div class="text-purple-600 mt-1">• Gestion des notes<br>• Consultation élèves<br>• Profil limité</div>
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
                            <i class="fas fa-user-plus mr-2"></i>Créer l'utilisateur
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
                        <h3 class="text-sm font-medium text-blue-900">Conseils de sécurité</h3>
                    </div>
                </div>
                <ul class="space-y-2 text-sm text-blue-800">
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-blue-500 mt-0.5 mr-2"></i>
                        <span>Utilisez des mots de passe forts (min. 6 caractères)</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-blue-500 mt-0.5 mr-2"></i>
                        <span>Chaque utilisateur doit avoir un email unique</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-blue-500 mt-0.5 mr-2"></i>
                        <span>Attribuez les rôles avec précaution</span>
                    </li>
                </ul>
            </div>
            
            <!-- Statistiques -->
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h3 class="text-sm font-medium text-gray-900 mb-3">Statistiques utilisateurs</h3>
                <div class="space-y-3">
                    <?php
                    $stats = $conn->query("
                        SELECT 
                            COUNT(*) as total_users,
                            SUM(CASE WHEN role = 'super_admin' THEN 1 ELSE 0 END) as super_admins,
                            SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
                            SUM(CASE WHEN role = 'enseignant' THEN 1 ELSE 0 END) as enseignants,
                            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as actifs
                        FROM users
                    ")->fetch_assoc();
                    ?>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Total utilisateurs</span>
                        <span class="font-semibold text-gray-900"><?php echo $stats['total_users'] ?? 0; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Super Admins</span>
                        <span class="font-semibold text-purple-600"><?php echo $stats['super_admins'] ?? 0; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Administrateurs</span>
                        <span class="font-semibold text-red-600"><?php echo $stats['admins'] ?? 0; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Enseignants</span>
                        <span class="font-semibold text-blue-600"><?php echo $stats['enseignants'] ?? 0; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Comptes actifs</span>
                        <span class="font-semibold text-green-600"><?php echo $stats['actifs'] ?? 0; ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Derniers utilisateurs -->
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h3 class="text-sm font-medium text-gray-900 mb-3">Derniers utilisateurs</h3>
                <div class="space-y-3">
                    <?php
                    $recent = $conn->query("
                        SELECT username, full_name, role, created_at 
                        FROM users 
                        ORDER BY created_at DESC 
                        LIMIT 3
                    ");
                    
                    if ($recent->num_rows > 0):
                        while ($row = $recent->fetch_assoc()):
                    ?>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['full_name']); ?></p>
                            <p class="text-xs text-gray-500">@<?php echo htmlspecialchars($row['username']); ?></p>
                        </div>
                        <span class="text-xs px-2 py-1 rounded-full 
                                   <?php echo $row['role'] === 'super_admin' ? 'bg-purple-100 text-purple-800' : 
                                          ($row['role'] === 'admin' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800'); ?>">
                            <?php echo $row['role']; ?>
                        </span>
                    </div>
                    <?php
                        endwhile;
                    else:
                    ?>
                    <p class="text-sm text-gray-500 text-center py-2">Aucun utilisateur</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>