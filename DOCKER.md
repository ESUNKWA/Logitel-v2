# Espace Katioka — Déploiement avec Docker

L'application tourne dans **deux conteneurs** :

| Conteneur | Rôle |
|---|---|
| `app` | L'application Laravel servie par **FrankenPHP** (serveur web Caddy + PHP 8.2 dans un seul processus). Sert les pages, les fichiers CSS/JS et les photos. |
| `db` | **PostgreSQL 16**. N'est pas exposé sur Internet : seul `app` peut le joindre. |

Les données qui doivent survivre aux mises à jour sont dans des **volumes Docker** :

| Volume | Contenu |
|---|---|
| `db_data` | La base de données |
| `storage` | Photos des chambres, logo de l'hôtel, fichiers de session/cache |
| `caddy_data`, `caddy_config` | Certificats HTTPS (le jour où un nom de domaine est configuré) |

Fichiers concernés : `Dockerfile`, `compose.yaml` (production), `compose.dev.yaml` (développement), `docker/` (configuration Caddy, PHP et script de démarrage), `.env.production.example`, `.dockerignore`.

> ⚠️ **Ne lancez jamais `docker compose down -v`** en production : l'option `-v` supprime les volumes, donc **la base et les photos**.

---

## 1. Production sur un VPS

### 1.1 Prérequis (une seule fois)

Un VPS Linux (Ubuntu 22.04/24.04 ou Debian 12), 2 Go de RAM minimum, accès SSH.

```bash
# Installer Docker (script officiel)
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER      # puis se déconnecter / reconnecter

# Pare-feu : SSH + web uniquement
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

### 1.2 Copier l'application sur le VPS

Depuis votre ordinateur (dans le dossier du projet), sans les fichiers locaux inutiles :

```bash
rsync -av --delete \
  --exclude vendor --exclude .env --exclude .env.production \
  --exclude storage/backups --exclude 'storage/logs/*' --exclude 'storage/app/public/*' \
  --exclude node_modules --exclude .DS_Store \
  ./ utilisateur@IP_DU_VPS:~/espace-katioka/
```

### 1.3 Configurer

Sur le VPS :

```bash
cd ~/espace-katioka
cp .env.production.example .env.production
nano .env.production
```

À renseigner :

- `APP_URL=http://IP_DU_VPS` (ex. `http://203.0.113.10`)
- `POSTGRES_PASSWORD=` un mot de passe long et aléatoire, par exemple le résultat de `openssl rand -base64 24`
- `APP_KEY=` la clé de chiffrement, à générer avec :

```bash
docker compose build
docker compose run --rm --no-deps --entrypoint php app artisan key:generate --show
# copiez la ligne base64:… dans APP_KEY=
```

> 🔒 `.env.production` contient les secrets : ne l'envoyez à personne et ne le copiez jamais dans l'image (le `.dockerignore` l'exclut). **Gardez une copie de `APP_KEY` en lieu sûr** : sans elle, les sessions sont perdues lors d'une réinstallation.

### 1.4 Démarrer

```bash
docker compose up -d --build
docker compose logs -f app        # Ctrl+C pour quitter les logs
```

Au démarrage, le conteneur `app` attend PostgreSQL, **applique les migrations** (création des tables sur la base neuve), puis met en cache la configuration.

### 1.5 Créer le compte du gérant (première fois)

La base est neuve : aucun compte n'existe. **Ne lancez pas `db:seed` en production** (il crée un compte `admin@espace-katioka.local` / `password` et des chambres de démonstration).

```bash
docker compose exec app php artisan hotel:create-manager
```

La commande demande l'e-mail, le nom et le mot de passe (10 caractères minimum, majuscules, minuscules et chiffres). Elle sert aussi à **réinitialiser** le mot de passe d'un gérant qui l'a oublié.

Ouvrez ensuite `http://IP_DU_VPS/admin`, connectez-vous, puis :

1. **Configuration → Hôtel** : nom, téléphone, adresse, logo…
2. **Configuration → Types de chambre**, puis **Chambres**
3. **Configuration → Paramètres** : horaires, largeur du ticket…
4. **Personnel** : comptes des réceptionnistes

La page client pour les demandes de chambre est `http://IP_DU_VPS/demande` (à mettre en QR code à l'accueil).

---

## 2. Opérations courantes (production)

| Action | Commande |
|---|---|
| Voir l'état | `docker compose ps` |
| Voir les journaux | `docker compose logs -f app` |
| Redémarrer | `docker compose restart app` |
| Arrêter / relancer | `docker compose stop` / `docker compose start` |
| Commande Laravel | `docker compose exec app php artisan …` |

### Mettre à jour l'application

```bash
# 1. sauvegarder (voir ci-dessous) ; 2. recopier le code (rsync §1.2) ; 3. sur le VPS :
docker compose up -d --build
```

Les migrations s'appliquent automatiquement au redémarrage. La base et les photos (volumes) sont conservées.

### Sauvegarder

```bash
mkdir -p ~/sauvegardes && cd ~/espace-katioka
# Base de données
docker compose exec -T db sh -c 'pg_dump -U "$POSTGRES_USER" -Fc "$POSTGRES_DB"' > ~/sauvegardes/base-$(date +%F-%H%M).dump
# Photos des chambres et logo
docker compose exec -T app tar czf - -C /app/storage app/public > ~/sauvegardes/fichiers-$(date +%F-%H%M).tar.gz
```

Conseil : copiez régulièrement `~/sauvegardes` **hors du VPS** (sur votre ordinateur, avec `scp` ou `rsync`). Pour une sauvegarde automatique chaque nuit, ajoutez ces deux lignes dans `crontab -e`, préfixées de `0 3 * * * cd ~/espace-katioka &&`.

### Restaurer

```bash
cd ~/espace-katioka
# Base (remplace le contenu actuel !)
docker compose exec -T db sh -c 'pg_restore -U "$POSTGRES_USER" -d "$POSTGRES_DB" --clean --if-exists --no-owner' < ~/sauvegardes/base-AAAA-MM-JJ-HHMM.dump
# Fichiers
docker compose exec -T app tar xzf - -C /app/storage < ~/sauvegardes/fichiers-AAAA-MM-JJ-HHMM.tar.gz
docker compose restart app
```

---

## 3. Passer en HTTPS (quand vous aurez un nom de domaine)

1. Chez le fournisseur du domaine, créez un enregistrement **A** pointant vers l'IP du VPS (ex. `hotel.espace-katioka.ci`).
2. Dans `.env.production` :
   ```
   SERVER_NAME=hotel.espace-katioka.ci
   APP_URL=https://hotel.espace-katioka.ci
   SESSION_SECURE_COOKIE=true
   ```
3. `docker compose up -d`

Caddy obtient et renouvelle **automatiquement** un certificat Let's Encrypt gratuit (les ports 80 et 443 doivent être ouverts). Le HTTPS est fortement recommandé : la page `/demande` est ouverte par les clients sur leur téléphone et la connexion du personnel transporte des mots de passe.

---

## 4. Développement avec Docker

Le code de votre ordinateur est monté dans le conteneur : toute modification est visible immédiatement, sans reconstruire.

```bash
docker compose -f compose.dev.yaml up -d --build
```

- Application : <http://localhost:8080>
- PostgreSQL (pour un client SQL) : `localhost:5433`, utilisateur / mot de passe / base : `katioka`
- La clé `APP_KEY` vient de votre fichier `.env` local ; les réglages de base de données du fichier compose ont la priorité sur ceux du `.env`.
- Données de démonstration (dev uniquement) : `docker compose -f compose.dev.yaml exec app php artisan db:seed`
- Tests : `docker compose -f compose.dev.yaml exec app php artisan test` (ils utilisent toujours une base SQLite en mémoire, jamais PostgreSQL — forcé dans `phpunit.xml`)
- Arrêter : `docker compose -f compose.dev.yaml down` (ajouter `-v` efface la base de dev)

Cette base de dev est **séparée** de votre PostgreSQL local habituel : `php artisan serve` continue de fonctionner comme avant.
