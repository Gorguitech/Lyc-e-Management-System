<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_auth();

// Vérifier que seul un admin peut modifier des utilisateurs
if ($_SESSION['user_role'] !== 'super_admin' && $_SESSION['user_role'] !== 'admin') {
    header("Location: " . BASE_URL . "dashboard.php?message=Accès non autorisé&message_type=error");
    exit();
}

$page_title = "Modifier un utilisateur";
$current_page = 'users';

// Vérifier l'ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: list.php?message=ID invalide&message_type=error");
    exit();
}

$id = intval($_GET['id']);

// Connexion à la base de données
$db = Database::getInstance();
$conn = $db->getConnection();

// Récupérer l'utilisateur
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header("Location: list.php?message=Utilisateur non trouvé&message_type=error");
    exit();
}

// Empêcher la modification d'un super_admin par un admin simple
if ($_SESSION['user_role'] !== 'super_admin' && $user['role'] === 'super_admin') {
    header("Location: list.php?message=Vous ne pouvez pas modifier un Super Admin&message_type=error");
    exit();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $data = [
        'username' => trim($_POST['username'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'email' => trim($_POST['email'] ?? ''),
        'role' => $_POST['role'] ?? 'admin',
        'full_name' => trim($_POST['full_name'] ?? ''),
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    ];
    
    // Validation
    if (empty($data['username'])) $errors[] = "Le nom d'utilisateur est obligatoire";
    if (empty($data['email'])) $errors[] = "L'email est obligatoire";
    if (empty($data['full_name'])) $errors[] = "Le nom complet est obligatoire";
    
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'email n'est pas valide";
    }
    
    if (!empty($data['password'])) {
        if ($data['password'] !== $data['confirm_password']) {
            $errors[] = "Les mots de passe ne correspondent pas";
        }
        if (strlen($data['password']) < 6) {
            $errors[] = "Le mot de passe doit contenir au moins 6 caractères";
        }
    }
    
    // Empêcher un admin simple de créer un super_admin
    if ($_SESSION['user_role'] !== 'super_admin' && $data['role'] === 'super_admin') {
        $errors[] = "Vous ne pouvez pas attribuer le rôle Super Admin";
    }
    
    // Vérifier si l'utilisateur existe déjà (pour un autre utilisateur)
    if (empty($errors)) {
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $check_stmt->bind_param("ssi", $data['username'], $data['email'], $id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $errors[] = "Ce nom d'utilisateur ou email est déjà utilisé";
        }
    }
    
    if (empty($errors)) {
        try {
            $conn->begin_transaction();
            
            if (!empty($data['password'])) {
                // Mettre à jour avec mot de passe
                $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET 
                                       username = ?, 
                                       email = ?, 
                                       role = ?, 
                                       full_name = ?, 
                                       is_active = ?,
                                       password = ?
                                       WHERE id = ?");
                $stmt->bind_param("ssssisi", 
                    $data['username'],
                    $data['email'],
                    $data['role'],
                    $data['full_name'],
                    $data['is_active'],
                    $hashed_password,
                    $id
                );
            } else {
                // Mettre à jour sans changer le mot de passe
                $stmt = $conn->prepare("UPDATE users SET 
                                       username = ?, 
                                       email = ?, 
                                       role = ?, 
                                       full_name = ?, 
                                       is_active = ?
                                       WHERE id = ?");
                $stmt->bind_param("ssssii", 
                    $data['username'],
                    $data['email'],
                    $data['role'],
                    $data['full_name'],
                    $data['is_active'],
                    $id
                );
            }
            
            if ($stmt->execute()) {
                // Journaliser l'action
                log_activity($_SESSION['user_id'], 'UPDATE', 'users', $id, 
                           "Modification de l'utilisateur: {$data['username']}");
                
                $conn->commit();
                
                header("Location: list.php?message=Utilisateur modifié avec succès&message_type=success");
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
            <h1 class="text-3xl font-bold text-gray-800">Modifier l'utilisateur</h1>
            <p class="text-gray-600 mt-2">Modifiez les informations de <?php echo htmlspecialchars($user['full_name']); ?></p>
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
                                   value="<?php echo htmlspecialchars($_POST['full_name'] ?? $user['full_name']); ?>" 
                                   required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Email <span class="text-red-500">*</span>
                            </label>
                            <input type="email" 
                                   name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? $user['email']); ?>" 
                                   required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
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
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? $user['username']); ?>" 
                                   required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Rôle <span class="text-red-500">*</span>
                            </label>
                            <select name="role" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="admin" <?php echo ($_POST['role'] ?? $user['role']) === 'admin' ? 'selected' : ''; ?>>Administrateur</option>
                                <option value="enseignant" <?php echo ($_POST['role'] ?? $user['role']) === 'enseignant' ? 'selected' : ''; ?>>Enseignant</option>
                                <?php if ($_SESSION['user_role'] === 'super_admin'): ?>
                                <option value="super_admin" <?php echo ($_POST['role'] ?? $user['role']) === 'super_admin' ? 'selected' : ''; ?>>Super Administrateur</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nouveau mot de passe</label>
                            <input type="password" 
                                   name="password" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Laisser vide pour ne pas changer">
                            <p class="mt-1 text-xs text-gray-500">Minimum 6 caractères</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Confirmer le mot de passe</label>
                            <input type="password" 
                                   name="confirm_password" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Répétez le nouveau mot de passe">
                        </div>
                    </div>
                </div>
                
                <!-- Statut -->
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4 pb-2 border-b">
                        <i class="fas fa-toggle-on mr-2"></i>Statut du compte
                    </h3>
                    <div class="flex items-center">
                        <input type="checkbox" 
                               name="is_active" 
                               id="is_active" 
                               value="1"
                               <?php echo (($_POST['is_active'] ?? $user['is_active']) ? 'checked' : ''); ?>
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="is_active" class="ml-2 text-sm text-gray-900">
                            Compte actif (l'utilisateur peut se connecter)
                        </label>
                    </div>
                    <p class="mt-2 text-xs text-gray-500">
                        Un compte inactif ne pourra pas se connecter au système.
                    </p>
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
            <!-- Infos utilisateur -->
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h3 class="text-sm font-medium text-gray-900 mb-3">Informations actuelles</h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Date création</span>
                        <span class="font-semibold text-gray-900"><?php echo format_date_fr($user['created_at'], 'short'); ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Créé par</span>
                        <span class="font-semibold text-gray-900">
                            <?php 
                            if ($user['created_by']) {
                                $creator_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
                                $creator_stmt->bind_param("i", $user['created_by']);
                                $creator_stmt->execute();
                                $creator = $creator_stmt->get_result()->fetch_assoc();
                                echo htmlspecialchars($creator['username'] ?? 'Système');
                            } else {
                                echo 'Système';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Dernière connexion</span>
                        <span class="font-semibold text-gray-900">
                            <?php echo $user['last_login'] ? format_date_fr($user['last_login'], 'datetime') : 'Jamais'; ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Statut</span>
                        <span class="font-semibold <?php echo $user['is_active'] ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo $user['is_active'] ? 'Actif' : 'Inactif'; ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Historique -->
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h3 class="text-sm font-medium text-gray-900 mb-3">Activité récente</h3>
                <div class="space-y-3">
                    <?php
                    $historique_stmt = $conn->prepare("
                        SELECT action, details, created_at 
                        FROM audit_log 
                        WHERE (table_name = 'users' AND record_id = ?) 
                           OR (user_id = ? AND table_name != 'users')
                        ORDER BY created_at DESC 
                        LIMIT 3
                    ");
                    $historique_stmt->bind_param("ii", $id, $id);
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
                            if (strpos($log['action'], 'LOGIN') !== false) {
                                $icon = 'fa-sign-in-alt';
                                $color = 'text-green-500';
                            } elseif (strpos($log['action'], 'CREATE') !== false) {
                                $icon = 'fa-plus-circle';
                                $color = 'text-blue-500';
                            } elseif (strpos($log['action'], 'UPDATE') !== false) {
                                $icon = 'fa-edit';
                                $color = 'text-yellow-500';
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
                    <p class="text-xs text-gray-500 text-center py-2">Aucune activité récente</p>
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
                    <button onclick="confirmToggleStatus(<?php echo $id; ?>, '<?php echo htmlspecialchars(addslashes($user['username'])); ?>', <?php echo $user['is_active']; ?>)" 
                            class="flex items-center justify-center w-full px-4 py-2 border border-blue-300 text-blue-700 rounded-lg hover:bg-blue-50">
                        <i class="fas <?php echo $user['is_active'] ? 'fa-toggle-on' : 'fa-toggle-off'; ?> mr-2"></i>
                        <?php echo $user['is_active'] ? 'Désactiver' : 'Activer'; ?> le compte
                    </button>
                    <?php if ($id != $_SESSION['user_id'] && $user['role'] != 'super_admin'): ?>
                    <button onclick="confirmDelete(<?php echo $id; ?>, '<?php echo htmlspecialchars(addslashes($user['username'])); ?>')" 
                            class="flex items-center justify-center w-full px-4 py-2 border border-red-300 text-red-700 rounded-lg hover:bg-red-50">
                        <i class="fas fa-trash mr-2"></i>
                        Supprimer l'utilisateur
                    </button>
                    <?php endif; ?>
                    <?php if ($user['role'] === 'enseignant'): ?>
                    <a href="<?php echo BASE_URL; ?>modules/enseignants/add.php?user_id=<?php echo $id; ?>" 
                       class="flex items-center justify-center w-full px-4 py-2 border border-green-300 text-green-700 rounded-lg hover:bg-green-50">
                        <i class="fas fa-user-plus mr-2"></i>
                        Créer profil enseignant
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, username) {
    if (confirm(`Êtes-vous sûr de vouloir supprimer l'utilisateur "${username}" ? Cette action est irréversible.`)) {
        window.location.href = `actions.php?action=delete&id=${id}`;
    }
}

function confirmToggleStatus(id, username, currentStatus) {
    const action = currentStatus ? 'désactiver' : 'activer';
    if (confirm(`Êtes-vous sûr de vouloir ${action} l'utilisateur "${username}" ?`)) {
        window.location.href = `actions.php?action=${currentStatus ? 'deactivate' : 'activate'}&id=${id}`;
    }
}
</script>

<?php include '../../includes/footer.php'; ?>