# Installation locale

Craft CMS 5 sous DDEV (nginx-fpm, PHP 8.4, MariaDB).

## Prerequis

- [DDEV](https://ddev.readthedocs.io/en/stable/users/install/ddev-installation/) et Docker
- Node 20+ (fourni par le conteneur DDEV, pas besoin en local)

## Etapes

```bash
git clone git@github.com:CallMeTrinity/sly-verse-site.git
cd sly-verse-site

cp .env.example .env      # puis renseigner CRAFT_APP_ID et CRAFT_SECURITY_KEY

ddev start
ddev composer install
ddev npm install
```

## Base de donnees

Le contenu vit en base, qui n'est pas versionnee. Deux cas :

**Reprise du site existant** — recuperer un dump depuis la prod Infomaniak, puis :

```bash
ddev import-db --file=dump.sql
```

La `CRAFT_SECURITY_KEY` du `.env` doit etre celle qui a servi a chiffrer ce dump,
sinon Craft ne pourra pas relire les donnees sensibles (licences plugins).
Elle est identique en local et en prod : la conserver dans un gestionnaire de mots de passe.

**Projet neuf** :

```bash
ddev craft setup/app-id
ddev craft setup/security-key
ddev craft install
```

## Config de contenu

Les sections, champs et volumes sont versionnes dans `config/project/`
(Project Config, 95 fichiers). Apres un import de base :

```bash
ddev craft up
```

## Lancer le front

```bash
ddev npm run dev     # Vite en HMR, expose via .ddev/docker-compose.vite.yaml
```

Le site repond sur https://sly-verse-site.ddev.site:8443

## Deploiement

Voir `deploy.sh` et `.env.production.example` pour la cible Infomaniak.
