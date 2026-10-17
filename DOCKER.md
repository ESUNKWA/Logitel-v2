# Hôtel Evasion — Déploiement avec Docker

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

### 1.5 Premier compte : l'administrateur par défaut

Au **premier démarrage**, sur une base **vide**, un compte **Gérant** est créé automatiquement :

- **E-mail** : `ADMIN_EMAIL` de `.env.production` (par défaut `admin@espace-katioka.local`)
- **Mot de passe** : `ADMIN_PASSWORD` de `.env.production` s'il est rempli ; **sinon un mot de passe aléatoire est généré** et affiché une seule fois dans les journaux :

```bash
docker compose logs app | grep -A4 "Compte administrateur par défaut"
```

À la première connexion, l'application **oblige à changer ce mot de passe**. Dès qu'un compte existe, plus rien n'est créé ni modifié aux redémarrages suivants.

> ℹ️ Un mot de passe fourni dans `ADMIN_PASSWORD` doit faire au moins 4 caractères ; sinon le compte n'est pas créé et un message l'indique dans les journaux. Une fois connecté, vous pouvez vider `ADMIN_PASSWORD` dans `.env.production`.

Si l'application était déjà installée **avant** cette fonctionnalité avec une base vide, il suffit de la mettre à jour (`docker compose up -d --build`) : le compte est créé au redémarrage.

Pour créer un autre gérant ou **réinitialiser un mot de passe oublié** :

```bash
docker compose exec app php artisan hotel:create-manager
```

**Ne lancez pas `db:seed` en production** (il crée un compte avec le mot de passe `password` et des chambres de démonstration).

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

**Site et application sur deux sous-domaines** (recommandé) : créez deux enregistrements A (ex. `www.hotel-evasion.ci` et `gestion.hotel-evasion.ci`), puis dans `.env.production` :
   ```
   SERVER_NAME="www.hotel-evasion.ci, gestion.hotel-evasion.ci"
   SITE_DOMAIN=www.hotel-evasion.ci
   ADMIN_DOMAIN=gestion.hotel-evasion.ci
   APP_URL=https://gestion.hotel-evasion.ci
   ```
   Le site répond alors sur `www…` et l'application à la racine de `gestion…` (plus de `/admin`). **Réimprimez l'affiche QR code** : l'adresse de la page de demande change.

Caddy obtient et renouvelle **automatiquement** un certificat Let's Encrypt gratuit (les ports 80 et 443 doivent être ouverts). Le HTTPS est fortement recommandé : la page `/demande` est ouverte par les clients sur leur téléphone et la connexion du personnel transporte des mots de passe.

---

## 4. Application mobile (Android / iPhone) pour le personnel

Seule **l'application de gestion** (`/admin`) s'installe sur les téléphones ; le site public et la page `/demande` des clients restent dans le navigateur. Prérequis : **HTTPS** (un domaine, même provisoire comme `….sslip.io` fourni par Coolify).

### 4.1 Installer sans APK (Android, iPhone, ordinateur)

- **Android / Chrome / Edge** : ouvrez `https://VOTRE-DOMAINE/admin` et touchez **« Installer l'application sur cet appareil »** (sous le bouton de connexion, ou en haut de l'écran une fois connecté). Si le navigateur ne propose pas l'installation directement, le bouton affiche les étapes (menu ⋮ → *Installer l'application* / *Ajouter à l'écran d'accueil*). Le bouton disparaît quand l'application est ouverte depuis son icône.
- **iPhone / iPad** : dans **Safari**, touchez **Partager** → **Sur l'écran d'accueil** → **Ajouter** (le bouton « Installer l'application » rappelle ces étapes).

L'icône « HE » apparaît sur l'écran d'accueil ; l'application s'ouvre en plein écran. Sans Internet, elle affiche « Pas de connexion » (aucune donnée n'est gardée sur le téléphone).

### 4.2 Générer un vrai fichier APK (Android)

1. Allez sur **https://www.pwabuilder.com**, saisissez `https://VOTRE-DOMAINE/admin/login` et lancez l'analyse.
2. Choisissez **Package for stores → Android**, puis **Generate**. Gardez les options proposées ; notez le **Package ID** (ex. `ci.hotelevasion.gestion`).
3. Téléchargez le ZIP. Il contient l'**APK** (pour installer directement), l'**AAB** (pour le Play Store), la **clé de signature** (`signing.keystore` + mots de passe) et un fichier `assetlinks.json`.
   > 🔐 **Gardez précieusement la clé de signature et ses mots de passe** (hors du serveur) : sans elle, impossible de publier une mise à jour du même APK.
4. Ouvrez `assetlinks.json` et copiez le **package_name** et l'**empreinte SHA-256** dans Coolify (variables d'environnement de l'application) :
   ```
   PWA_ANDROID_PACKAGE=ci.hotelevasion.gestion
   PWA_ANDROID_SHA256=AB:CD:…:EF
   ```
   Redéployez, puis vérifiez que `https://VOTRE-DOMAINE/.well-known/assetlinks.json` affiche ces valeurs. Sans cela, l'APK s'ouvre avec une barre d'adresse.
5. **Installation directe** : envoyez le fichier `.apk` sur le téléphone (WhatsApp, câble…), ouvrez-le et autorisez l'installation depuis cette source.
   **Ou Play Store** : compte développeur Google (25 $ une fois), publiez l'AAB, éventuellement en test interne réservé à votre équipe.

L'APK affiche toujours la version à jour de l'application : pas besoin de le régénérer après une mise à jour du serveur.

### 4.3 Deux APK : un pour l'UAT (test), un pour la production

Deux APK peuvent être installés **côte à côte** sur le même téléphone s'ils ont **un Package ID différent** (avec le même, l'un remplace l'autre). On garde **la même clé de signature** pour les deux.

| | Production | UAT (test) |
|---|---|---|
| Adresse à donner à PWABuilder | `https://DOMAINE-PROD/admin/login` | `https://ADRESSE-UAT/admin/login` |
| Package ID | `ci.hotelevasion.gestion` | `ci.hotelevasion.gestion.uat` |
| Nom affiché | Hôtel Evasion | Hôtel Evasion **UAT** |
| Variables Coolify de cet environnement | `PWA_ANDROID_PACKAGE=ci.hotelevasion.gestion`<br>`PWA_ANDROID_SHA256=…` | `PWA_ANDROID_PACKAGE=ci.hotelevasion.gestion.uat`<br>`PWA_ANDROID_SHA256=…`<br>`PWA_NAME_SUFFIX=UAT` |

1. **UAT d'abord** : dans Coolify (application UAT), mettez `PWA_NAME_SUFFIX=UAT` et `PWA_ANDROID_PACKAGE=ci.hotelevasion.gestion.uat`, redéployez, vérifiez que `https://ADRESSE-UAT/admin/manifest.webmanifest` affiche « Hôtel Evasion — Gestion UAT ».
2. Sur **pwabuilder.com** : adresse UAT → *Package for stores* → **Android** → *Package ID* `ci.hotelevasion.gestion.uat`, *App name* « Hôtel Evasion UAT », **Signing key : Use mine** (le `signing.keystore` et ses mots de passe gardés la première fois), augmentez *Version code* → *Generate*.
3. Ouvrez l'`assetlinks.json` du ZIP : l'empreinte doit être celle de `PWA_ANDROID_SHA256` (même clé ⇒ même empreinte). Vérifiez `https://ADRESSE-UAT/.well-known/assetlinks.json`.
4. **Production** : mêmes étapes avec l'adresse de production, *Package ID* `ci.hotelevasion.gestion`, *App name* « Hôtel Evasion », **sans** `PWA_NAME_SUFFIX`. Les téléphones qui ont déjà l'APK `ci.hotelevasion.gestion` le mettent à jour par-dessus (sans désinstaller) et passent sur la production.
5. Installez l'APK voulu (WhatsApp, câble…) ; les deux icônes cohabitent, « Hôtel Evasion » et « Hôtel Evasion UAT ».

> Sans `assetlinks.json` correct sur le bon serveur, l'APK s'ouvre avec une barre d'adresse : vérifiez toujours l'étape 3 pour chaque environnement.

### 4.4 Au changement de domaine (domaine provisoire → définitif)

1. Dans Coolify : `APP_URL`, `SERVER_NAME` (et `SITE_DOMAIN` / `ADMIN_DOMAIN` si vous séparez site et application) ; redéployez. Tout le personnel se reconnecte une fois.
2. **Regénérez l'APK** avec PWABuilder sur le nouveau domaine **en réutilisant la même clé de signature** (option *Use existing signing key*), mettez à jour `PWA_ANDROID_SHA256` si besoin, réinstallez ou publiez la mise à jour.
3. Les installations « sans APK » (§ 4.1) sont à refaire depuis la nouvelle adresse.
4. **Réimprimez l'affiche QR code** de la page `/demande` (elle contient l'adresse).

---

## 5. Développement avec Docker

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
