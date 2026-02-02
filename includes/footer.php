<?php
if (isset($_SESSION['user_id'])): ?>
                </main>
                
                <!-- Footer -->
                <footer class="bg-white border-t border-gray-200 px-6 py-4">
                    <div class="flex flex-col md:flex-row justify-between items-center space-y-2 md:space-y-0">
                        <div class="text-sm text-gray-600">
                            © <?php echo date('Y'); ?> <span class="font-semibold text-gray-800"><?php echo APP_NAME; ?></span> 
                            - Tous droits réservés
                        </div>
                        
                        <div class="flex items-center space-x-6">
                            <div class="flex items-center space-x-2 text-sm">
                                <span class="flex h-2 w-2 relative">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full <?php echo ENV === 'production' ? 'bg-green-400' : 'bg-yellow-400'; ?> opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 <?php echo ENV === 'production' ? 'bg-green-500' : 'bg-yellow-500'; ?>"></span>
                                </span>
                                <span class="text-gray-600">
                                    <?php echo ENV === 'production' ? 'Production' : 'Développement'; ?>
                                </span>
                            </div>
                            
                            <div class="text-sm text-gray-600">
                                <span class="hidden md:inline">Version</span> 
                                <span class="font-semibold text-gray-800"><?php echo APP_VERSION; ?></span>
                            </div>
                            
                            <div class="text-sm text-gray-600 hidden md:block">
                                <?php
                                // Calculer le temps d'exécution
                                $execution_time = microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"];
                                echo "Temps: " . number_format($execution_time, 3) . "s";
                                ?>
                            </div>
                            
                            <a href="#" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                                <i class="fas fa-question-circle mr-1"></i>Aide
                            </a>
                        </div>
                    </div>
                </footer>
            </div>
        
        <!-- JavaScript -->
        <script>
            // Toggle sidebar mobile
            document.getElementById('mobileMenuToggle').addEventListener('click', function() {
                document.getElementById('sidebar').classList.toggle('active');
            });
            
            // Toggle sidebar collapse
            let sidebarCollapsed = false;
            document.getElementById('toggleSidebar').addEventListener('click', function() {
                sidebarCollapsed = !sidebarCollapsed;
                const sidebar = document.getElementById('sidebar');
                const mainContent = document.getElementById('mainContent');
                const sidebarIcon = document.getElementById('sidebarIcon');
                
                if (sidebarCollapsed) {
                    sidebar.classList.add('collapsed');
                    sidebarIcon.classList.remove('fa-chevron-left');
                    sidebarIcon.classList.add('fa-chevron-right');
                    this.querySelector('.sidebar-text').textContent = 'Agrandir menu';
                } else {
                    sidebar.classList.remove('collapsed');
                    sidebarIcon.classList.remove('fa-chevron-right');
                    sidebarIcon.classList.add('fa-chevron-left');
                    this.querySelector('.sidebar-text').textContent = 'Réduire menu';
                }
            });
            
            // Toggle notifications dropdown
            document.getElementById('notificationsButton').addEventListener('click', function(e) {
                e.stopPropagation();
                document.getElementById('notificationsDropdown').classList.toggle('hidden');
            });
            
            // Toggle user menu
            document.getElementById('userMenuButton').addEventListener('click', function(e) {
                e.stopPropagation();
                document.getElementById('userMenu').classList.toggle('hidden');
            });
            
            // Close dropdowns when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('#notificationsButton')) {
                    document.getElementById('notificationsDropdown').classList.add('hidden');
                }
                if (!e.target.closest('#userMenuButton')) {
                    document.getElementById('userMenu').classList.add('hidden');
                }
            });
            
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert-auto-hide');
                alerts.forEach(alert => {
                    alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => alert.remove(), 500);
                });
            }, 5000);
            
            // Chart configuration for dashboard
            function initDashboardCharts() {
                // Performance chart
                const ctx1 = document.getElementById('performanceChart');
                if (ctx1) {
                    new Chart(ctx1, {
                        type: 'line',
                        data: {
                            labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun'],
                            datasets: [{
                                label: 'Moyenne générale',
                                data: [12, 11.5, 13, 12.8, 13.5, 14],
                                borderColor: '#3b82f6',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                tension: 0.4,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: false,
                                    min: 10,
                                    max: 20
                                }
                            }
                        }
                    });
                }
                
                // Répartition par classe chart
                const ctx2 = document.getElementById('classesChart');
                if (ctx2) {
                    new Chart(ctx2, {
                        type: 'doughnut',
                        data: {
                            labels: ['Terminale', 'Première', 'Seconde'],
                            datasets: [{
                                data: [120, 150, 180],
                                backgroundColor: [
                                    '#8b5cf6',
                                    '#3b82f6',
                                    '#10b981'
                                ],
                                borderWidth: 0
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }
                    });
                }
            }
            
            // Initialize charts when page loads
            document.addEventListener('DOMContentLoaded', initDashboardCharts);
            
            // Loader pour les requêtes
            function showLoader() {
                const loader = document.createElement('div');
                loader.id = 'globalLoader';
                loader.innerHTML = `
                    <div class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50">
                        <div class="bg-white p-6 rounded-xl shadow-2xl">
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin"></div>
                                <span class="text-gray-800 font-medium">Chargement...</span>
                            </div>
                        </div>
                    </div>
                `;
                document.body.appendChild(loader);
            }
            
            // Confirmation dialog personnalisé
            function confirmAction(message, callback) {
                const modal = document.createElement('div');
                modal.className = 'fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50';
                modal.innerHTML = `
                    <div class="bg-white p-6 rounded-xl shadow-2xl max-w-sm w-full mx-4">
                        <div class="text-center">
                            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                                <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Confirmation</h3>
                            <p class="text-sm text-gray-500 mb-6">${message}</p>
                            <div class="flex space-x-3">
                                <button onclick="this.closest('.fixed').remove()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                                    Annuler
                                </button>
                                <button onclick="callback(); this.closest('.fixed').remove()" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                                    Confirmer
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
            }
        </script>
    </body>
    </html>
<?php endif; ?>