<?php

// Vérifier si l'utilisateur est connecté
$is_logged_in = isset($_SESSION['user_id']);
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>

<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME . ' - ' . ($page_title ?? 'Dashboard'); ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js pour les graphiques -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Styles personnalisés -->
    <style>
        :root {
            --primary-color: #3b82f6;
            --secondary-color: #8b5cf6;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
        }
        
        body {
            overflow-x: hidden;
        }
        
        .sidebar {
            width: 256px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.05);
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            z-index: 40;
            background: white;
        }
        
        .sidebar.collapsed {
            width: 70px;
        }
        
        .sidebar.collapsed .sidebar-text {
            display: none;
        }
        
        .sidebar.collapsed .logo-text {
            display: none;
        }
        
        .main-content {
            margin-left: 256px;
            width: calc(100% - 256px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .sidebar.collapsed ~ .main-content {
            margin-left: 70px;
            width: calc(100% - 70px);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }
        }
        
        .menu-item {
            transition: all 0.2s ease;
            border-radius: 10px;
        }
        
        .menu-item:hover {
            transform: translateX(5px);
            background: linear-gradient(90deg, rgba(59, 130, 246, 0.1) 0%, rgba(59, 130, 246, 0) 100%);
        }
        
        .menu-item.active {
            background: linear-gradient(90deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        
        .stat-card {
            transition: all 0.3s ease;
            border-radius: 16px;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .notification-dot {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge-primary {
            background-color: rgba(59, 130, 246, 0.1);
            color: var(--primary-color);
        }
        
        .badge-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }
        
        .badge-warning {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }
        
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
        }
        
        .avatar-blue {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .avatar-green {
            background: linear-gradient(135deg, #10b981 0%, #3b82f6 100%);
        }
        
        .avatar-purple {
            background: linear-gradient(135deg, #8b5cf6 0%, #ec4899 100%);
        }
        
        /* Correction pour éviter les débordements */
        .main-content > * {
            max-width: 100%;
            box-sizing: border-box;
        }
    </style>
</head>
<body class="h-full bg-gray-50">
    <?php if ($is_logged_in): ?>
    <!-- Sidebar -->
    <aside class="sidebar flex flex-col" id="sidebar">
        <!-- Logo et titre -->
        <div class="p-6 border-b border-gray-100">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl flex items-center justify-center">
                    <i class="fas fa-school text-white text-xl"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-800 logo-text"><?php echo APP_NAME; ?></h1>
                    <p class="text-xs text-gray-500 mt-1 logo-text">Gestion Scolaire</p>
                </div>
            </div>
        </div>
        
        <!-- Informations utilisateur -->
        <div class="p-4 border-b border-gray-100">
            <div class="flex items-center space-x-3">
                <div class="avatar avatar-blue">
                    <?php echo strtoupper(substr($_SESSION['full_name'], 0, 2)); ?>
                </div>
                <div class="flex-1">
                    <h3 class="font-medium text-gray-800 text-sm sidebar-text"><?php echo $_SESSION['full_name']; ?></h3>
                    <p class="text-xs text-gray-500 sidebar-text">
                        <span class="badge badge-primary"><?php echo ucfirst(str_replace('_', ' ', $_SESSION['user_role'])); ?></span>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Menu principal -->
        <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
            <!-- Dashboard -->
            <a href="<?php echo BASE_URL; ?>dashboard.php" 
               class="menu-item flex items-center space-x-3 p-3 <?php echo ($current_page == 'dashboard') ? 'active' : 'text-gray-700 hover:text-blue-600'; ?>">
                <i class="fas fa-tachometer-alt w-5 text-center <?php echo ($current_page == 'dashboard') ? 'text-white' : 'text-gray-400'; ?>"></i>
                <span class="sidebar-text font-medium">Dashboard</span>
            </a>
            
            <!-- Gestion des élèves -->
            <a href="<?php echo BASE_URL; ?>modules/eleves/list.php" 
               class="menu-item flex items-center space-x-3 p-3 <?php echo ($current_page == 'eleves' || $current_page == 'list') ? 'active' : 'text-gray-700 hover:text-blue-600'; ?>">
                <i class="fas fa-user-graduate w-5 text-center <?php echo ($current_page == 'eleves' || $current_page == 'list') ? 'text-white' : 'text-gray-400'; ?>"></i>
                <span class="sidebar-text font-medium">Élèves</span>
                <span class="ml-auto badge badge-primary">+32</span>
            </a>
            
            <!-- Gestion des enseignants -->
            <a href="<?php echo BASE_URL; ?>modules/enseignants/list.php" 
               class="menu-item flex items-center space-x-3 p-3 <?php echo ($current_page == 'enseignants') ? 'active' : 'text-gray-700 hover:text-blue-600'; ?>">
                <i class="fas fa-chalkboard-teacher w-5 text-center <?php echo ($current_page == 'enseignants') ? 'text-white' : 'text-gray-400'; ?>"></i>
                <span class="sidebar-text font-medium">Enseignants</span>
            </a>
            
            <!-- Gestion des classes -->
            <a href="<?php echo BASE_URL; ?>modules/classes/list.php" 
               class="menu-item flex items-center space-x-3 p-3 <?php echo ($current_page == 'classes') ? 'active' : 'text-gray-700 hover:text-blue-600'; ?>">
                <i class="fas fa-school w-5 text-center <?php echo ($current_page == 'classes') ? 'text-white' : 'text-gray-400'; ?>"></i>
                <span class="sidebar-text font-medium">Classes</span>
            </a>
            
            <!-- Gestion des notes -->
            <a href="<?php echo BASE_URL; ?>modules/notes/list.php" 
               class="menu-item flex items-center space-x-3 p-3 <?php echo ($current_page == 'notes') ? 'active' : 'text-gray-700 hover:text-blue-600'; ?>">
                <i class="fas fa-clipboard-list w-5 text-center <?php echo ($current_page == 'notes') ? 'text-white' : 'text-gray-400'; ?>"></i>
                <span class="sidebar-text font-medium">Notes</span>
                <span class="ml-auto badge badge-warning">Nouv.</span>
            </a>
            
            <!-- Gestion des utilisateurs (Admin seulement) -->
            <?php if ($_SESSION['user_role'] === 'super_admin' || $_SESSION['user_role'] === 'admin'): ?>
            <div class="pt-4 mt-4 border-t border-gray-100">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 px-3 sidebar-text">Administration</p>
                <a href="<?php echo BASE_URL; ?>modules/users/manage.php" 
                   class="menu-item flex items-center space-x-3 p-3 <?php echo ($current_page == 'users' || $current_page == 'manage') ? 'active' : 'text-gray-700 hover:text-blue-600'; ?>">
                    <i class="fas fa-users-cog w-5 text-center <?php echo ($current_page == 'users' || $current_page == 'manage') ? 'text-white' : 'text-gray-400'; ?>"></i>
                    <span class="sidebar-text font-medium">Utilisateurs</span>
                </a>
            </div>
            <?php endif; ?>
            
            <!-- Super Admin seulement -->
            <?php if ($_SESSION['user_role'] === 'super_admin'): ?>
            <a href="<?php echo BASE_URL; ?>audit.php" 
               class="menu-item flex items-center space-x-3 p-3 <?php echo ($current_page == 'audit') ? 'active' : 'text-gray-700 hover:text-blue-600'; ?>">
                <i class="fas fa-history w-5 text-center <?php echo ($current_page == 'audit') ? 'text-white' : 'text-gray-400'; ?>"></i>
                <span class="sidebar-text font-medium">Journal d'audit</span>
            </a>
            <?php endif; ?>
        </nav>
        
        <!-- Menu bas -->
        <div class="p-4 border-t border-gray-100">
            <!-- Bouton réduction sidebar -->
            <button id="toggleSidebar" class="menu-item flex items-center space-x-3 p-3 w-full text-gray-700 hover:text-blue-600">
                <i class="fas fa-chevron-left w-5 text-center" id="sidebarIcon"></i>
                <span class="sidebar-text font-medium">Réduire menu</span>
            </button>
            
            <!-- Déconnexion -->
            <a href="<?php echo BASE_URL; ?>logout.php" 
               class="menu-item flex items-center space-x-3 p-3 text-red-600 hover:text-red-700 hover:bg-red-50 mt-2 rounded-lg">
                <i class="fas fa-sign-out-alt w-5 text-center"></i>
                <span class="sidebar-text font-medium">Déconnexion</span>
            </a>
            
            <!-- Version -->
            <div class="mt-4 pt-4 border-t border-gray-100">
                <p class="text-xs text-gray-500 text-center sidebar-text">
                    v<?php echo APP_VERSION; ?> • <?php echo date('Y'); ?>
                </p>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Bar -->
        <header class="bg-white border-b border-gray-200 sticky top-0 z-30">
            <div class="px-6 py-4 flex items-center justify-between">
                <!-- Left: Menu mobile et titre -->
                <div class="flex items-center space-x-4">
                    <button id="mobileMenuToggle" class="md:hidden text-gray-600 hover:text-gray-900">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <div>
                        <h1 class="text-xl font-bold text-gray-800"><?php echo $page_title ?? 'Dashboard'; ?></h1>
                        <nav class="flex space-x-1 text-sm text-gray-500 mt-1">
                            <a href="<?php echo BASE_URL; ?>dashboard.php" class="hover:text-blue-600">Dashboard</a>
                            <span class="text-gray-300">/</span>
                            <span class="text-gray-700"><?php echo $page_title ?? 'Accueil'; ?></span>
                        </nav>
                    </div>
                </div>
                
                <!-- Right: Actions et profil -->
                <div class="flex items-center space-x-4">
                    <!-- Recherche -->
                    <div class="relative hidden md:block">
                        <input type="text" 
                               placeholder="Rechercher..." 
                               class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent w-64">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    </div>
                    
                    <!-- Notifications -->
                    <div class="relative">
                        <button id="notificationsButton" class="relative p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg">
                            <i class="fas fa-bell text-lg"></i>
                            <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full notification-dot"></span>
                        </button>
                        <div id="notificationsDropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-xl border border-gray-200 py-2 z-50">
                            <div class="px-4 py-2 border-b">
                                <h3 class="font-semibold text-gray-800">Notifications</h3>
                            </div>
                            <div class="max-h-64 overflow-y-auto">
                                <a href="#" class="flex items-center px-4 py-3 hover:bg-gray-50 border-b border-gray-100">
                                    <div class="flex-shrink-0">
                                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                            <i class="fas fa-user-plus text-blue-600 text-sm"></i>
                                        </div>
                                    </div>
                                    <div class="ml-3 flex-1">
                                        <p class="text-sm text-gray-800">Nouvel élève inscrit</p>
                                        <p class="text-xs text-gray-500 mt-1">Il y a 2 minutes</p>
                                    </div>
                                </a>
                                <a href="#" class="flex items-center px-4 py-3 hover:bg-gray-50">
                                    <div class="flex-shrink-0">
                                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                            <i class="fas fa-chart-line text-green-600 text-sm"></i>
                                        </div>
                                    </div>
                                    <div class="ml-3 flex-1">
                                        <p class="text-sm text-gray-800">Rapport mensuel disponible</p>
                                        <p class="text-xs text-gray-500 mt-1">Il y a 1 heure</p>
                                    </div>
                                </a>
                            </div>
                            <div class="px-4 py-2 border-t">
                                <a href="#" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                                    Voir toutes les notifications
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Profil utilisateur -->
                    <div class="relative">
                        <button id="userMenuButton" class="flex items-center space-x-3 focus:outline-none group">
                            <div class="relative">
                                <div class="avatar avatar-blue">
                                    <?php echo strtoupper(substr($_SESSION['full_name'], 0, 2)); ?>
                                </div>
                                <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-500 border-2 border-white rounded-full"></div>
                            </div>
                            <div class="hidden md:block text-left">
                                <p class="text-sm font-medium text-gray-800"><?php echo $_SESSION['full_name']; ?></p>
                                <p class="text-xs text-gray-500"><?php echo ucfirst($_SESSION['user_role']); ?></p>
                            </div>
                            <i class="fas fa-chevron-down text-gray-400 text-sm"></i>
                        </button>
                        
                        <div id="userMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl border border-gray-200 py-2 z-50">
                            <div class="px-4 py-2 border-b">
                                <p class="text-sm font-medium text-gray-800"><?php echo $_SESSION['full_name']; ?></p>
                                <p class="text-xs text-gray-500"><?php echo $_SESSION['email'] ?? 'admin@lycee.fr'; ?></p>
                            </div>
                            <a href="#" class="flex items-center px-4 py-2 text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-user-circle mr-3 text-gray-400"></i>
                                <span>Mon profil</span>
                            </a>
                            <a href="#" class="flex items-center px-4 py-2 text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-cog mr-3 text-gray-400"></i>
                                <span>Paramètres</span>
                            </a>
                            <div class="border-t my-1"></div>
                            <a href="<?php echo BASE_URL; ?>logout.php" class="flex items-center px-4 py-2 text-red-600 hover:bg-red-50">
                                <i class="fas fa-sign-out-alt mr-3"></i>
                                <span>Déconnexion</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="flex-1 overflow-y-auto p-6">
            <!-- Les alertes seront affichées ici -->
            <?php if (isset($message)): ?>
            <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'; ?> alert-auto-hide">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-red-500'; ?>"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium <?php echo $message_type === 'success' ? 'text-green-800' : 'text-red-800'; ?>">
                            <?php echo $message; ?>
                        </p>
                    </div>
                    <div class="ml-auto pl-3">
                        <button type="button" class="inline-flex text-gray-400 hover:text-gray-500" onclick="this.parentElement.parentElement.remove()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
<?php endif; ?>