\
# Lycée Management System (LMS)

## 📋 Description
Lycée Management System est une application web complète de gestion scolaire développée en PHP avec MySQL. Elle permet aux administrateurs de gérer les élèves, enseignants, classes, notes, et de suivre toutes les activités du système grâce à un journal d'audit détaillé.

## ✨ Fonctionnalités principales

### 🔐 Gestion des accès
- Système d'authentification sécurisé avec rôles (super_admin, admin, enseignant)
- Journalisation des activités (audit log)
- Interface responsive avec Tailwind CSS

### 👥 Gestion des utilisateurs
- Gestion complète des élèves (création, modification, suppression)
- Gestion des enseignants avec leurs spécialités
- Gestion des classes et sections

### 📊 Gestion académique
- Saisie et gestion des notes avec coefficients
- Calcul automatique des moyennes
- Classement des élèves par performance
- Tableaux de bord statistiques

### 🔍 Journal d'audit
- Traçabilité complète des actions utilisateurs
- Filtres avancés de recherche
- Export CSV des logs
- Statistiques d'activité

## 🛠️ Technologies utilisées
- **Backend** : PHP 8.2+
- **Base de données** : MySQL 8.0+
- **Frontend** : Tailwind CSS, Chart.js, Font Awesome
- **Serveur web** : Apache 2.4+

## 📦 Installation sur Ubuntu 24.04 avec LAMP

### Étape 1 : Mettre à jour le système
```bash
sudo apt update && sudo apt upgrade -y
```

### Étape 2 : Installer Apache
```bash
sudo apt install apache2 -y
sudo systemctl enable apache2
sudo systemctl start apache2
```

### Étape 3 : Installer MySQL
```bash
sudo apt install mysql-server -y
sudo systemctl enable mysql
sudo systemctl start mysql
```

Sécuriser l'installation MySQL :
```bash
sudo mysql_secure_installation
```
- Définir un mot de passe root sécurisé
- Répondre "Y" à toutes les questions de sécurité

### Étape 4 : Installer PHP 8.2
```bash
sudo apt install software-properties-common -y
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install php8.2 php8.2-mysql php8.2-curl php8.2-xml php8.2-mbstring php8.2-intl php8.2-zip libapache2-mod-php8.2 -y
```

### Étape 5 : Configurer Apache
```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### Étape 6 : Cloner l'application
```bash
cd /var/www/html
sudo git clone https://github.com/Gorguitech/Lyc-e-Management-System.git lycee
sudo chown -R www-data:www-data lycee/
sudo chmod -R 755 lycee/
```

### Étape 7 : Configurer la base de données
```bash
# Se connecter à MySQL
sudo mysql -u root -p

# Dans MySQL, exécuter :
CREATE DATABASE lycee_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'lycee_user'@'localhost' IDENTIFIED BY 'votre_mot_de_passe_securise';
GRANT ALL PRIVILEGES ON lycee_db.* TO 'lycee_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Étape 8 : Importer la base de données
```bash
cd /var/www/html/lycee
sudo mysql -u root -p lycee_db < lycee_db.sql
```

### Étape 9 : Configuration de l'application

#### 1. Configurer la base de données
```bash
sudo nano config/database.php
```
Modifier les informations de connexion :
```php
$host = 'localhost';
$dbname = 'lycee_db';
$username = 'lycee_user';
$password = 'votre_mot_de_passe_securise';
```

#### 2. Configurer l'URL de base
```bash
sudo nano config/config.php
```
Modifier la constante BASE_URL selon votre configuration :
```php
define('BASE_URL', 'http://votre-domaine-ou-ip/lycee/');
```

### Étape 10 : Configurer les permissions
```bash
sudo chown -R www-data:www-data /var/www/html/lycee/
sudo chmod -R 755 /var/www/html/lycee/
```

### Étape 11 : Configurer Apache (Virtual Host)
```bash
sudo nano /etc/apache2/sites-available/lycee.conf
```

Ajouter la configuration suivante :
```apache
<VirtualHost *:80>
    ServerAdmin admin@votre-domaine.com
    DocumentRoot /var/www/html/lycee
    ServerName votre-domaine.com
    ServerAlias www.votre-domaine.com

    <Directory /var/www/html/lycee>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
```

Activer le site :
```bash
sudo a2ensite lycee.conf
sudo a2dissite 000-default.conf
sudo systemctl reload apache2
```

### Étape 12 : Redémarrer les services
```bash
sudo systemctl restart apache2
sudo systemctl restart mysql
```

## 🔧 Configuration finale

### 1. Vérifier l'installation PHP
```bash
php -v
```

### 2. Tester la connexion à la base de données
Accéder à : `http://votre-ip/lycee/test-connection.php`

### 3. Activer les extensions PHP nécessaires
```bash
sudo phpenmod intl
sudo systemctl restart apache2
```

## 👤 Accès à l'application

### Identifiants par défaut :
- **URL** : `http://votre-ip/lycee/login.php`
- **Super Admin** : 
  - Username : `admin@lycee.sn`
  - Password : `password`

## 📁 Structure des fichiers
```
lycee/
├── assets/           # Fichiers statiques
├── config/           # Configuration
│   ├── config.php    # Configuration générale
│   └── database.php  # Connexion DB
├── includes/         # Classes et fonctions
│   ├── auth.php      # Authentification
│   ├── functions.php # Fonctions utilitaires
│   ├── header.php    # Header commun
│   └── footer.php    # Footer commun
├── modules/          # Modules fonctionnels
│   ├── eleves/       # Gestion élèves
│   ├── enseignants/  # Gestion enseignants
│   ├── classes/      # Gestion classes
│   ├── notes/        # Gestion notes
│   └── users/        # Gestion utilisateurs
├── audit.php         # Journal d'audit
├── dashboard.php     # Tableau de bord
├── login.php         # Connexion
├── logout.php        # Déconnexion
├── export_audit.php  # Export logs
└── lycee_db.sql      # Base de données
```

## 🔒 Sécurité recommandée

### 1. Changer les mots de passe par défaut
```sql
-- Dans MySQL
UPDATE users SET password = '$2y$10$votre_hash_secu' WHERE username = 'admin';
```

### 2. Configurer SSL (HTTPS)
```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d votre-domaine.com
```

### 3. Restreindre l'accès aux fichiers sensibles
```apache
# Dans .htaccess
<Files "config.php">
    Order Allow,Deny
    Deny from all
</Files>
<Files "database.php">
    Order Allow,Deny
    Deny from all
</Files>
```

## 🐛 Dépannage

### Problème 1 : Erreur 404
```bash
# Activer mod_rewrite
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### Problème 2 : Connexion MySQL échouée
```bash
# Vérifier le service MySQL
sudo systemctl status mysql
```

### Problème 3 : Permission refusée
```bash
sudo chown -R www-data:www-data /var/www/html/lycee/
sudo chmod -R 755 /var/www/html/lycee/
```

### Problème 4 : Extension PHP manquante
```bash
# Installer l'extension
sudo apt install php8.2-intl
sudo systemctl restart apache2
```

## 📈 Fonctionnalités avancées

### 1. Journal d'audit
- Accès : Menu "Journal d'audit" (Super Admin uniquement)
- Filtres : Action, table, utilisateur, dates
- Export : Format CSV

### 2. Statistiques
- Tableau de bord avec graphiques
- Performance académique
- Répartition par classe

### 3. Gestion des rôles
- Super Admin : Accès complet
- Admin : Gestion standard
- Enseignant : Accès limité

## 🔄 Mise à jour
```bash
cd /var/www/html/lycee
sudo git pull origin main
sudo systemctl restart apache2
```

## 📄 Licence
Ce projet est sous licence MIT. Voir le fichier LICENSE pour plus de détails.

## 👥 Contribution
Les contributions sont les bienvenues ! Merci de créer une issue pour discuter des changements proposés.

## 📞 Support
Pour toute question ou problème, veuillez :
1. Consulter la section dépannage
2. Créer une issue sur GitHub
3. Contacter l'administrateur système

---
**Note** : Ce système est conçu pour un usage éducatif. Adaptez-le selon vos besoins spécifiques en matière de sécurité et de conformité.