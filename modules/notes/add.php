<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_auth();

$page_title = "Ajouter une note";
$current_page = 'notes';

// Connexion à la base de données
$db = Database::getInstance();
$conn = $db->getConnection();

// Récupérer les élèves et enseignants pour les selects
$eleves_result = $conn->query("SELECT id, nom, prenom, matricule, classe_id FROM eleves ORDER BY nom, prenom");
$enseignants_result = $conn->query("SELECT id, nom, prenom, matiere FROM enseignants ORDER BY nom, prenom");

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $data = [
        'eleve_id' => $_POST['eleve_id'] ? intval($_POST['eleve_id']) : null,
        'enseignant_id' => $_POST['enseignant_id'] ? intval($_POST['enseignant_id']) : null,
        'matiere' => trim($_POST['matiere'] ?? ''),
        'note' => $_POST['note'] ? floatval(str_replace(',', '.', $_POST['note'])) : null,
        'coefficient' => $_POST['coefficient'] ? floatval(str_replace(',', '.', $_POST['coefficient'])) : 1.00,
        'type_note' => $_POST['type_note'] ?? 'devoir',
        'date_note' => $_POST['date_note'] ?? date('Y-m-d'),
        'commentaire' => trim($_POST['commentaire'] ?? '')
    ];
    
    // Validation
    if (empty($data['eleve_id'])) $errors[] = "Veuillez sélectionner un élève";
    if (empty($data['enseignant_id'])) $errors[] = "Veuillez sélectionner un enseignant";
    if (empty($data['matiere'])) $errors[] = "La matière est obligatoire";
    if ($data['note'] === null) $errors[] = "La note est obligatoire";
    
    if ($data['note'] !== null && ($data['note'] < 0 || $data['note'] > 20)) {
        $errors[] = "La note doit être comprise entre 0 et 20";
    }
    
    if ($data['coefficient'] !== null && ($data['coefficient'] < 0.1 || $data['coefficient'] > 5)) {
        $errors[] = "Le coefficient doit être compris entre 0.1 et 5";
    }
    
    if (empty($errors)) {
        try {
            $conn->begin_transaction();
            
            $stmt = $conn->prepare("INSERT INTO notes (eleve_id, enseignant_id, matiere, note, coefficient, type_note, date_note, commentaire, created_by) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisddsssi", 
                $data['eleve_id'],
                $data['enseignant_id'],
                $data['matiere'],
                $data['note'],
                $data['coefficient'],
                $data['type_note'],
                $data['date_note'],
                $data['commentaire'],
                $_SESSION['user_id']
            );
            
            if ($stmt->execute()) {
                $note_id = $conn->insert_id;
                
                // Récupérer les noms pour le log
                $eleve_stmt = $conn->prepare("SELECT nom, prenom FROM eleves WHERE id = ?");
                $eleve_stmt->bind_param("i", $data['eleve_id']);
                $eleve_stmt->execute();
                $eleve = $eleve_stmt->get_result()->fetch_assoc();
                
                $ens_stmt = $conn->prepare("SELECT nom, prenom FROM enseignants WHERE id = ?");
                $ens_stmt->bind_param("i", $data['enseignant_id']);
                $ens_stmt->execute();
                $enseignant = $ens_stmt->get_result()->fetch_assoc();
                
                // Journaliser l'action
                log_activity($_SESSION['user_id'], 'CREATE', 'notes', $note_id, 
                           "Ajout de note: {$eleve['nom']} {$eleve['prenom']} - {$data['matiere']} - {$data['note']}/20");
                
                $conn->commit();
                
                header("Location: list.php?message=Note ajoutée avec succès&message_type=success");
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
            <h1 class="text-3xl font-bold text-gray-800">Ajouter une note</h1>
            <p class="text-gray-600 mt-2">Attribuez une note à un élève</p>
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
                <!-- Sélection élève et enseignant -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Élève <span class="text-red-500">*</span>
                        </label>
                        <select name="eleve_id" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                onchange="updateEleveInfo(this.value)">
                            <option value="">Sélectionnez un élève</option>
                            <?php while ($eleve = $eleves_result->fetch_assoc()): ?>
                            <option value="<?php echo $eleve['id']; ?>" 
                                    <?php echo ($_POST['eleve_id'] ?? '') == $eleve['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom'] . ' (' . $eleve['matricule'] . ')'); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                        <div id="eleveInfo" class="mt-2 p-2 bg-gray-50 rounded text-sm text-gray-600 hidden">
                            <!-- Info élève chargée dynamiquement -->
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Enseignant <span class="text-red-500">*</span>
                        </label>
                        <select name="enseignant_id" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                onchange="updateEnseignantInfo(this.value)">
                            <option value="">Sélectionnez un enseignant</option>
                            <?php 
                            $enseignants_result->data_seek(0); // Réinitialiser le pointeur
                            while ($enseignant = $enseignants_result->fetch_assoc()): 
                            ?>
                            <option value="<?php echo $enseignant['id']; ?>" 
                                    <?php echo ($_POST['enseignant_id'] ?? '') == $enseignant['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($enseignant['nom'] . ' ' . $enseignant['prenom'] . ' (' . $enseignant['matiere'] . ')'); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                        <div id="enseignantInfo" class="mt-2 p-2 bg-gray-50 rounded text-sm text-gray-600 hidden">
                            <!-- Info enseignant chargée dynamiquement -->
                        </div>
                    </div>
                </div>
                
                <!-- Informations de la note -->
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4 pb-2 border-b">
                        <i class="fas fa-clipboard-check mr-2"></i>Informations de la note
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Matière <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   name="matiere" 
                                   value="<?php echo htmlspecialchars($_POST['matiere'] ?? ''); ?>" 
                                   required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Ex: Mathématiques">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Note /20 <span class="text-red-500">*</span>
                            </label>
                            <input type="number" 
                                   name="note" 
                                   value="<?php echo htmlspecialchars($_POST['note'] ?? ''); ?>" 
                                   required
                                   min="0" 
                                   max="20" 
                                   step="0.1"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="0.0 à 20.0">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Coefficient</label>
                            <input type="number" 
                                   name="coefficient" 
                                   value="<?php echo htmlspecialchars($_POST['coefficient'] ?? '1.00'); ?>" 
                                   min="0.1" 
                                   max="5" 
                                   step="0.1"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Ex: 1.0">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Type de note</label>
                            <select name="type_note" 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="devoir" <?php echo ($_POST['type_note'] ?? 'devoir') === 'devoir' ? 'selected' : ''; ?>>Devoir</option>
                                <option value="examen" <?php echo ($_POST['type_note'] ?? '') === 'examen' ? 'selected' : ''; ?>>Examen</option>
                                <option value="participation" <?php echo ($_POST['type_note'] ?? '') === 'participation' ? 'selected' : ''; ?>>Participation</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date de la note</label>
                            <input type="date" 
                                   name="date_note" 
                                   value="<?php echo htmlspecialchars($_POST['date_note'] ?? date('Y-m-d')); ?>" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>
                </div>
                
                <!-- Commentaire -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Commentaire (optionnel)</label>
                    <textarea name="commentaire" 
                              rows="3"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                              placeholder="Commentaires sur la note..."><?php echo htmlspecialchars($_POST['commentaire'] ?? ''); ?></textarea>
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
                            <i class="fas fa-save mr-2"></i>Enregistrer la note
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
                        <span>La note doit être comprise entre 0 et 20</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-blue-500 mt-0.5 mr-2"></i>
                        <span>Le coefficient par défaut est 1.0</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-blue-500 mt-0.5 mr-2"></i>
                        <span>Un examen a généralement un coefficient plus élevé</span>
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
                            (SELECT COUNT(*) FROM notes) as total_notes,
                            (SELECT AVG(note) FROM notes) as moyenne_generale,
                            (SELECT COUNT(DISTINCT matiere) FROM notes) as nb_matieres
                    ")->fetch_assoc();
                    ?>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Notes totales</span>
                        <span class="font-semibold text-gray-900"><?php echo $stats['total_notes']; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Moyenne générale</span>
                        <span class="font-semibold text-gray-900"><?php echo number_format($stats['moyenne_generale'], 2); ?>/20</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Matières notées</span>
                        <span class="font-semibold text-gray-900"><?php echo $stats['nb_matieres']; ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Dernières notes -->
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h3 class="text-sm font-medium text-gray-900 mb-3">Dernières notes</h3>
                <div class="space-y-3">
                    <?php
                    $recent = $conn->query("
                        SELECT n.note, n.matiere, e.nom, e.prenom 
                        FROM notes n 
                        JOIN eleves e ON n.eleve_id = e.id 
                        ORDER BY n.created_at DESC 
                        LIMIT 3
                    ");
                    
                    if ($recent->num_rows > 0):
                        while ($row = $recent->fetch_assoc()):
                    ?>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['nom'] . ' ' . $row['prenom']); ?></p>
                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($row['matiere']); ?></p>
                        </div>
                        <span class="text-sm font-semibold <?php echo $row['note'] >= 10 ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo $row['note']; ?>/20
                        </span>
                    </div>
                    <?php
                        endwhile;
                    else:
                    ?>
                    <p class="text-sm text-gray-500 text-center py-2">Aucune note récente</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Fonction pour charger les infos de l'élève
function updateEleveInfo(eleveId) {
    if (!eleveId) {
        document.getElementById('eleveInfo').classList.add('hidden');
        return;
    }
    
    fetch(`../../includes/ajax.php?action=get_eleve_info&id=${eleveId}`)
        .then(response => response.json())
        .then(data => {
            const eleveInfo = document.getElementById('eleveInfo');
            if (data.success) {
                eleveInfo.innerHTML = `
                    <div><strong>${data.data.nom} ${data.data.prenom}</strong></div>
                    <div>Matricule: ${data.data.matricule}</div>
                    <div>Classe: ${data.data.nom_classe || 'Non assigné'}</div>
                    <div>Moyenne: ${data.data.moyenne ? data.data.moyenne.toFixed(2) + '/20' : 'Pas de notes'}</div>
                `;
                eleveInfo.classList.remove('hidden');
                
                // Auto-remplir la matière si un enseignant est sélectionné
                const enseignantSelect = document.querySelector('select[name="enseignant_id"]');
                if (enseignantSelect.value) {
                    updateEnseignantInfo(enseignantSelect.value);
                }
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
        });
}

// Fonction pour charger les infos de l'enseignant
function updateEnseignantInfo(enseignantId) {
    if (!enseignantId) {
        document.getElementById('enseignantInfo').classList.add('hidden');
        return;
    }
    
    fetch(`../../includes/ajax.php?action=get_enseignant_info&id=${enseignantId}`)
        .then(response => response.json())
        .then(data => {
            const enseignantInfo = document.getElementById('enseignantInfo');
            if (data.success) {
                enseignantInfo.innerHTML = `
                    <div><strong>${data.data.nom} ${data.data.prenom}</strong></div>
                    <div>Matière: ${data.data.matiere}</div>
                    <div>Spécialité: ${data.data.specialite || 'Non spécifiée'}</div>
                `;
                enseignantInfo.classList.remove('hidden');
                
                // Auto-remplir la matière dans le champ matiere
                const matiereInput = document.querySelector('input[name="matiere"]');
                if (!matiereInput.value && data.data.matiere) {
                    matiereInput.value = data.data.matiere;
                }
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
        });
}

// Initialiser si des valeurs sont présentes
document.addEventListener('DOMContentLoaded', function() {
    const eleveSelect = document.querySelector('select[name="eleve_id"]');
    const enseignantSelect = document.querySelector('select[name="enseignant_id"]');
    
    if (eleveSelect.value) updateEleveInfo(eleveSelect.value);
    if (enseignantSelect.value) updateEnseignantInfo(enseignantSelect.value);
});
</script>

<?php include '../../includes/footer.php'; ?>