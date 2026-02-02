<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';

require_auth();

$page_title = "Gestion des élèves";
$current_page = 'eleves';

include '../../includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-800">Gestion des utilisateurs</h1>
    <p class="text-gray-600 mt-2">Module en cours de développement</p>
</div>

<div class="bg-white rounded-xl shadow-md p-8 text-center">
    <i class="fas fa-user-graduate text-4xl text-blue-500 mb-4"></i>
    <h3 class="text-xl font-semibold text-gray-800 mb-2">Module Utilisateurs</h3>
    <p class="text-gray-600 mb-6">Cette fonctionnalité sera bientôt disponible</p>
    <a href="<?php echo BASE_URL; ?>dashboard.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
        <i class="fas fa-arrow-left mr-2"></i>
        Retour au Dashboard
    </a>
</div>

<?php include '../../includes/footer.php'; ?>