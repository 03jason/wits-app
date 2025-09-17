# WITS - Mini Inventory Manager

Gestionnaire de stock simple (produits + mouvements), basé sur **PHP (Slim Framework)**, **MySQL** et **Docker**.

## # Stack utilisée
- **Backend** : PHP 8 + Slim 4 (API REST)
- **Base de données** : MySQL 8 (Docker)
- **Environnement** : Docker Compose
- **Tests** : PHPUnit

## # Structure





##  # Installation & Lancement

```bash
# 1. Cloner le dépôt
git clone https://github.com/<ton-user>/wits-app.git
cd wits-app

# 2. Lancer les services Docker
docker compose up -d --build

# 3. Installer les dépendances PHP
docker compose exec app composer install

# 4. Vérifier que l’application répond
curl http://localhost:8080/api/products

# 5. Lancer les tests unitaires
docker compose exec app ./vendor/bin/phpunit
```

# # [Commandes utiles]
### - Voir les logs en live
docker compose logs -f app

docker compose logs -f db

### - Relancer proprement
docker compose down -v

docker compose up -d --build

### - Vérifier les conteneurs
docker compose ps
