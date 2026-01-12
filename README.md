# 🎮 SWU - Sly Wrestling Universe - Feuille de route

## 📋 Phase 1 : Configuration initiale du Back Office

### Ticket #1 : Configuration générale du site

**Objectif :** Configurer les paramètres de base de Craft CMS

**Actions :**

- Paramètres → Général
   - Nom du système : "Sly Wrestling Universe"
   - Fuseau horaire : Europe/Paris
   - Langue : Français
- Paramètres → Sites
   - Nom du site : "SWU"
   - URL de base : ton URL locale/production
   - Langue : Français

---

### Ticket #2 : Créer le volume Assets pour les images

**Objectif :** Configurer le stockage des médias

**Actions :**

- Paramètres → Fichiers → Volumes → Nouveau volume
   - **Nom :** Images
   - **Identificateur :** images
   - **Système de fichiers :** Local
   - **Chemin de base :** `@webroot/uploads/images`
   - **URL de base :** `@web/uploads/images`
- Créer un second volume pour les vidéos :
   - **Nom :** Vidéos
   - **Identificateur :** videos
   - **Chemin de base :** `@webroot/uploads/videos`
   - **URL de base :** `@web/uploads/videos`

**Terminal :**

```bash
mkdir -p web/uploads/images
mkdir -p web/uploads/videos
chmod 755 web/uploads
```

---

## 📋 Phase 2 : Création des champs personnalisés

### Ticket #3 : Créer les champs de base

**Objectif :** Créer tous les champs réutilisables

**Actions :** Paramètres → Champs → Nouveau champ

**Champs à créer :**

1. **Image de profil**
   - Type : Ressources (Assets)
   - Identificateur : `profileImage`
   - Volume : Images
   - Limite : 1 fichier
   - Types de fichiers : Images uniquement

2. **Photo principale**
   - Type : Ressources (Assets)
   - Identificateur : `mainImage`
   - Volume : Images
   - Limite : 1 fichier

3. **Galerie d'images**
   - Type : Ressources (Assets)
   - Identificateur : `imageGallery`
   - Volume : Images
   - Limite : illimité

4. **Description courte**
   - Type : Texte
   - Identificateur : `shortDescription`
   - Multiligne : Oui
   - Limite de caractères : 200

5. **Contenu riche**
   - Type : CKEditor (ou Redactor si installé)
   - Identificateur : `richContent`

6. **Date et heure**
   - Type : Date/Heure
   - Identificateur : `eventDateTime`
   - Afficher le fuseau horaire : Non

7. **Lien externe**
   - Type : URL
   - Identificateur : `externalLink`

8. **Statut actif/inactif**
   - Type : Lightswitch
   - Identificateur : `isActive`
   - Activé par défaut : Oui

9. **Vidéo**
   - Type : Ressources (Assets)
   - Identificateur : `video`
   - Volume : Vidéos
   - Limite : 1 fichier

10.   **Lien vidéo externe** (YouTube/Twitch)
      - Type : URL
      - Identificateur : `videoUrl`

---

### Ticket #4 : Créer les champs spécifiques Superstars

**Objectif :** Champs pour les profils de superstars

**Actions :** Paramètres → Champs

**Champs à créer :**

1. **Nom de ring**
   - Type : Texte
   - Identificateur : `ringName`

2. **Taille**
   - Type : Texte
   - Identificateur : `height`
   - Placeholder : "Ex: 1m85"

3. **Poids**
   - Type : Texte
   - Identificateur : `weight`
   - Placeholder : "Ex: 95kg"

4. **Ville d'origine**
   - Type : Texte
   - Identificateur : `hometown`

5. **Finisher** (prise de finition)
   - Type : Texte
   - Identificateur : `finisher`

6. **Roster**
   - Type : Liste déroulante (Dropdown)
   - Identificateur : `roster`
   - Options :
      - Raw
      - SmackDown
      - NXT
      - Free Agent

7. **Statut (Face/Heel)**
   - Type : Liste déroulante
   - Identificateur : `alignment`
   - Options :
      - Face (Gentil)
      - Heel (Méchant)
      - Tweener (Neutre)

8. **Palmarès** (Titres remportés)
   - Type : Matrice (Matrix)
   - Identificateur : `championships`
   - Champs dans la matrice :
      - Nom du titre (Texte)
      - Nombre de règnes (Nombre)
      - Date de gain (Date)

---

### Ticket #5 : Créer les champs pour les Shows

**Objectif :** Champs pour les événements/shows

**Actions :** Paramètres → Champs

**Champs à créer :**

1. **Type de show**
   - Type : Liste déroulante
   - Identificateur : `showType`
   - Options :
      - Weekly Show (RAW/SmackDown)
      - Premium Live Event (PPV)
      - Special Event

2. **Carte des matchs**
   - Type : Matrice (Matrix)
   - Identificateur : `matchCard`
   - Champs dans la matrice :
      - Titre du match (Texte)
      - Participants (Entrées - relation vers Superstars)
      - Type de match (Texte) - Ex: "Singles Match", "Tag Team"
      - Stipulation (Texte) - Ex: "Steel Cage", "Ladder Match"
      - Résultat (Texte enrichi)
      - Vainqueur (Entrées - relation vers Superstars)

3. **Durée estimée**
   - Type : Texte
   - Identificateur : `duration`
   - Placeholder : "Ex: 3h00"

---

## 📋 Phase 3 : Création des Sections

### Ticket #6 : Créer la section "Page d'accueil" (Single)

**Objectif :** Page d'accueil unique éditable

**Actions :**

- Paramètres → Entrées → Nouvelle section
   - **Nom :** Page d'accueil
   - **Identificateur :** homepage
   - **Type :** Single
   - **URI :** `__home__`
   - **Template :** `index`

**Modèle de champs à ajouter :**

- Titre Hero (Texte) - nouveau champ : `heroTitle`
- Description Hero (Texte multiligne) - nouveau champ : `heroDescription`
- Image Hero (utiliser : `mainImage`)
- Lien boutique (utiliser : `externalLink`) - nouveau champ : `shopLink`
- Lien Twitch (URL) - nouveau champ : `twitchLink`
- Lien Instagram (URL) - nouveau champ : `instagramLink`
- Réseaux sociaux (Matrix) - nouveau champ : `socialLinks`
   - Plateforme (Texte)
   - URL (URL)
   - Icône (Assets)

---

### Ticket #7 : Créer la section "Superstars" (Channel)

**Objectif :** Gérer toutes les superstars

**Actions :**

- Paramètres → Entrées → Nouvelle section
   - **Nom :** Superstars
   - **Identificateur :** superstars
   - **Type :** Canal (Channel)
   - **Format d'URI :** `superstars/{slug}`
   - **Template :** `_pages/superstars/_entry`

**Types d'entrée → Superstar :**

**Modèle de champs :**

- Nom de ring (utiliser : `ringName`)
- Photo de profil (utiliser : `profileImage`)
- Photos additionnelles (utiliser : `imageGallery`)
- Biographie (utiliser : `richContent`)
- Description courte (utiliser : `shortDescription`)
- Taille (utiliser : `height`)
- Poids (utiliser : `weight`)
- Ville d'origine (utiliser : `hometown`)
- Finisher (utiliser : `finisher`)
- Roster (utiliser : `roster`)
- Statut Face/Heel (utiliser : `alignment`)
- Palmarès (utiliser : `championships`)
- Statut actif (utiliser : `isActive`)

---

### Ticket #8 (modifié) : Créer la section "Shows" (Channel)\*\*

**Actions :**

- Paramètres → Entrées → Nouvelle section
   - **Nom :** Shows
   - **Identificateur :** shows
   - **Type :** Canal
   - **Format d'URI :** `shows/{slug}`
   - **Template :** `_pages/shows/_entry`

**Modèle de champs :**

- **Statut du show** (Liste déroulante) - nouveau champ : `showStatus`
   - Options :
      - À venir
      - Terminé
      - En cours (optionnel)
- Date du show (utiliser : `eventDateTime`)
- Type de show (utiliser : `showType`)
- Image principale (utiliser : `mainImage`)
- Description (utiliser : `richContent`)
- Carte des matchs (utiliser : `matchCard`)
- Vidéo du show (utiliser : `videoUrl`)
- Lien stream (utiliser : `twitchLink`)

---

### Ticket #10 : Créer la section "Médiathèque" (Channel)

**Objectif :** Galerie de photos et vidéos

**Actions :**

- Paramètres → Entrées → Nouvelle section
   - **Nom :** Médiathèque
   - **Identificateur :** media
   - **Type :** Canal
   - **Format d'URI :** `mediatheque/{slug}`
   - **Template :** `_pages/media/_entry`

**Modèle de champs :**

- Type de média (Liste déroulante) - nouveau champ : `mediaType`
   - Options : Photo, Vidéo, Galerie
- Galerie (utiliser : `imageGallery`)
- Vidéo (utiliser : `video` ou `videoUrl`)
- Description (utiliser : `shortDescription`)
- Show associé (Entrées - relation vers Shows)

---

### Ticket #11 : Créer la section "Comment participer" (Single)

**Objectif :** Page expliquant comment créer sa superstar

**Actions :**

- Paramètres → Entrées → Nouvelle section
   - **Nom :** Comment Participer
   - **Identificateur :** howToParticipate
   - **Type :** Single
   - **URI :** `comment-participer`
   - **Template :** `_pages/participate`

**Modèle de champs :**

- Titre (Texte)
- Instructions (utiliser : `richContent`)
- Formulaire de contact (URL) - nouveau champ : `formLink`
- Règles (Texte enrichi) - nouveau champ : `rules`

---

### Ticket #12 : Créer la section "Planning Streams" (Single)

**Objectif :** Afficher le calendrier des streams 2K

**Actions :**

- Paramètres → Entrées → Nouvelle section
   - **Nom :** Planning Streams
   - **Identificateur :** streamSchedule
   - **Type :** Single
   - **URI :** `planning`
   - **Template :** `_pages/schedule`

**Modèle de champs :**

- Prochains streams (Matrix) - nouveau champ : `upcomingStreams`
   - Date et heure (Date/Heure)
   - Titre du stream (Texte)
   - Description (Texte)
   - Lien Twitch (URL)

---

## 📋 Phase 4 : Configuration de la navigation

### Ticket #13 : Créer le menu principal

**Objectif :** Structure de navigation du site

**Actions :**

- Créer un champ global pour le menu
- Paramètres → Globaux → Nouveau ensemble global
   - **Nom :** Navigation
   - **Identificateur :** navigation

**Champs à ajouter :**

- Menu principal (Matrix) - nouveau champ : `mainMenu`
   - Libellé (Texte)
   - Type de lien (Dropdown)
      - Page interne (Entrées)
      - URL externe (URL)
   - Icône (Assets - optionnel)

**Ou utiliser un plugin de navigation comme "Navigation" de Verbb**

---

## 📋 Phase 5 : Routes personnalisées

### Ticket #14 : Créer les routes de listing

**Objectif :** Pages index pour les sections

**Actions :**

- Paramètres → Routes → Nouvelle route

**Routes à créer :**

1. **Liste des Superstars**
   - URI : `superstars`
   - Template : `_pages/superstars/index`

2. **Résultats**
   - URI : `resultats`
   - Template : `_pages/shows/past-index`

3. **Shows à venir**
   - URI : `shows-a-venir`
   - Template : `_pages/shows/upcoming-index`

4. **Médiathèque**
   - URI : `mediatheque`
   - Template : `_pages/media/index`

---

## 📋 Phase 6 : Contenu de test

### Ticket #15 : Créer du contenu de démonstration

**Objectif :** Remplir le BO avec des données de test

**Actions :**

1. **Page d'accueil :**
   - Remplir tous les champs
   - Ajouter des liens réseaux sociaux

2. **Superstars (créer 5-10 superstars) :**
   - Nom, bio, stats
   - Photos de profil
   - Palmarès
   - Assigner à des rosters

3. **Shows passés (3-5 shows) :**
   - Dates passées
   - Carte complète avec résultats
   - Photos/vidéos

4. **Shows à venir (2-3 shows) :**
   - Dates futures
   - Carte annoncée
   - Liens Twitch

5. **Médiathèque (10-15 entrées) :**
   - Photos de matchs
   - Vidéos highlights
   - Galeries

6. **Planning Streams :**
   - Remplir avec 4-5 prochains streams

7. **Comment Participer :**
   - Rédiger les instructions
   - Ajouter les règles

---

## 📋 Phase 7 : Préparation Front-End

### Ticket #16 : Structure des templates Twig

**Objectif :** Créer l'arborescence des templates

**Actions :**
Créer la structure de fichiers suivante dans `templates/` :

```
templates/
├── _layout.twig (template de base)
├── _partials/
│   ├── _header.twig
│   ├── _footer.twig
│   ├── _nav.twig
│   └── _superstar-card.twig
├── index.twig (homepage)
├── _pages/
│   ├── superstars/
│   │   ├── index.twig (liste)
│   │   └── _entry.twig (détail)
│   ├── shows/
│   │   ├── past-index.twig
│   │   ├── upcoming-index.twig
│   │   ├── _past.twig
│   │   └── _upcoming.twig
│   ├── media/
│   │   ├── index.twig
│   │   └── _entry.twig
│   ├── participate.twig
│   └── schedule.twig
```

---

### Ticket #17 : Template de base (\_layout.twig)

**Objectif :** Créer le layout principal avec HTML de base

**Template minimal :**

```twig
<!DOCTYPE html>
<html lang="fr">
	<head>
		<meta charset="UTF-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		<title>
			{% block title %}
				SWU - Sly Wrestling Universe
			{% endblock %}
		</title>
		<link rel="stylesheet" href="/dist/css/app.css" />
	</head>
	<body>
		{% include '_partials/_header' %}
		{% include '_partials/_nav' %}

		<main>
			{% block content %}

			{% endblock %}
		</main>

		{% include '_partials/_footer' %}

		<script src="/dist/js/app.js"></script>
	</body>
</html>
```

---

### Ticket #18 : Templates des pages principales (structure de base)

**Objectif :** Créer tous les templates avec dump des données

**Pour chaque template, créer une version basique qui affiche les données :**

```twig
{% extends '_layout' %}

{% block content %}
	{{ dump(entry) }}
	{# ou {{ dump(superstars) }} pour les listes #}
{% endblock %}
```

**Templates à créer :**

- `index.twig`
- `_pages/superstars/index.twig`
- `_pages/superstars/_entry.twig`
- `_pages/shows/past-index.twig`
- `_pages/shows/_past.twig`
- `_pages/shows/upcoming-index.twig`
- `_pages/shows/_upcoming.twig`
- `_pages/media/index.twig`
- `_pages/media/_entry.twig`
- `_pages/participate.twig`
- `_pages/schedule.twig`

---

## 📋 Phase 8 : Front-End & Design

### Ticket #19 : Setup Tailwind CSS

**Objectif :** Installer et configurer Tailwind (déjà dans le boilerplate)

**Vérifier :**

- `npm install` déjà fait
- `tailwind.config.js` configuré
- Lancer `ddev npm run dev` pour le dev

---

### Ticket #20 : Définir la palette de couleurs WWE-inspired

**Objectif :** Créer le design system

**Dans `tailwind.config.js`, ajouter :**

```js
theme: {
  extend: {
    colors: {
      'wwe-red': '#C8102E',
      'wwe-blue': '#0057B8',
      'wwe-gold': '#FFD100',
      'wwe-black': '#0C0C0C',
      'wwe-white': '#FFFFFF',
      'wwe-gray': '#2C2C2C',
    }
  }
}
```

---

### Ticket #21 : Header & Navigation

**Objectif :** Créer le header style WWE

**Dans `_partials/_header.twig` :**

- Logo SWU
- Menu de navigation horizontal
- Liens réseaux sociaux
- Responsive menu burger

**Style :**

- Fond noir (`bg-wwe-black`)
- Liens en blanc avec hover rouge
- Sticky en haut

---

### Ticket #22 : Page d'accueil - Hero Section

**Objectif :** Section hero avec image de fond

**Éléments :**

- Grande image de fond
- Titre "BIENVENUE DANS LE SWU"
- Sous-titre "Sly Wrestling Universe"
- Boutons CTA (Boutique, Twitch)
- Style dramatique WWE

---

### Ticket #23 : Page d'accueil - Planning Streams

**Objectif :** Carte des prochains streams

**Éléments :**

- Liste des prochains streams
- Date/heure visible
- Lien vers Twitch
- Style cards avec hover

---

### Ticket #24 : Liste des Superstars

**Objectif :** Grille de cartes superstars

**Template `_pages/superstars/index.twig` :**

- Grille responsive (3-4 colonnes desktop, 1-2 mobile)
- Carte par superstar :
   - Photo
   - Nom de ring
   - Roster (badge coloré)
   - Statut Face/Heel
   - Lien vers détail

---

### Ticket #25 : Profil Superstar (détail)

**Objectif :** Page détaillée d'une superstar

**Template `_pages/superstars/_entry.twig` :**

- Grande photo hero
- Stats (taille, poids, ville, finisher)
- Biographie complète
- Galerie photos
- Palmarès (liste des titres)
- Style WWE avec sections distinctes

---

### Ticket #26 : Liste des Shows passés

**Objectif :** Timeline des résultats

**Template `_pages/shows/past-index.twig` :**

- Liste chronologique inversée
- Card par show :
   - Image
   - Nom du show
   - Date
   - Type (PPV, Weekly)
   - Lien détails

---

### Ticket #27 : Détail Show passé

**Objectif :** Page résultats détaillés

**Template `_pages/shows/_past.twig` :**

- Header avec image et date
- Carte complète des matchs :
   - Type de match
   - Participants (liens vers superstars)
   - Résultat
   - Vainqueur highlighted
- Vidéo embed (si disponible)

---

### Ticket #28 : Shows à venir

**Objectif :** Cards des prochains événements

**Template `_pages/shows/upcoming-index.twig` :**

- Grande carte par show
- Compte à rebours jusqu'au show
- Carte annoncée (sans résultats)
- Bouton "Regarder sur Twitch"

---

### Ticket #29 : Médiathèque

**Objectif :** Galerie photos/vidéos

**Template `_pages/media/index.twig` :**

- Filtres (Photos / Vidéos / Tout)
- Grille de vignettes
- Lightbox pour agrandir
- Liens vidéos externes

---

### Ticket #30 : Page "Comment Participer"

**Objectif :** Instructions et formulaire

**Template `_pages/participate.twig` :**

- Titre accrocheur
- Instructions étape par étape
- Règles clairement listées
- Formulaire ou lien de contact
- CTA pour encourager la participation

---

### Ticket #31 : Footer

**Objectif :** Footer complet

**Dans `_partials/_footer.twig` :**

- Liens réseaux sociaux
- Menu footer (liens rapides)
- Copyright
- Logo SWU
- Style sombre WWE

---

### Ticket #32 : Responsive & Mobile

**Objectif :** Optimisation mobile

**Actions :**

- Tester toutes les pages sur mobile
- Menu burger fonctionnel
- Grilles responsive
- Images optimisées
- Touch-friendly

---

### Ticket #33 : Animations & Transitions

**Objectif :** Ajouter du dynamisme

**Éléments :**

- Hover effects sur cards
- Transitions smoothes
- Animations d'entrée (fade-in, slide)
- Parallax sur hero sections (optionnel)

---

## 📋 Phase 9 : Optimisation & Finitions

### Ticket #34 : SEO & Meta

**Objectif :** Optimiser le référencement

**Actions :**

- Ajouter plugin SEOmatic (optionnel)
- Ou créer champs manuels :
   - Meta description
   - Meta titre
   - Open Graph images
- Sitemap.xml
- Robots.txt

---

### Ticket #35 : Performance

**Objectif :** Optimiser les performances

**Actions :**

- Compresser les images (plugin Imager-X ou ImageOptimize)
- Minifier CSS/JS (`npm run build`)
- Lazy loading images
- Cache Craft activé

---

### Ticket #36 : Tests finaux

**Objectif :** Vérifier tout fonctionne

**Checklist :**

- ✅ Toutes les pages accessibles
- ✅ Toutes les images s'affichent
- ✅ Liens fonctionnels
- ✅ Mobile responsive
- ✅ Navigation intuitive
- ✅ Contenu éditable dans le BO
- ✅ Formulaires testés

---

### Ticket #37 : Documentation

**Objectif :** Guide pour l'admin

**Créer un document :**

- Comment ajouter une superstar
- Comment créer un show
- Comment uploader des médias
- Workflow de publication

---

## 📋 Phase 10 : Déploiement (Optionnel)

### Ticket #38 : Configuration serveur de production

**Objectif :** Préparer l'environnement de prod

**Actions selon l'hébergement choisi**

---

### Ticket #39 : Migration du contenu

**Objectif :** Transférer données de dev vers prod

**Actions :**

- Export/import database
- Upload assets
- Project Config sync

---

### Ticket #40 : Mise en ligne

**Objectif :** Site live !

**Actions :**

- DNS configuré
- SSL actif
- Tests finaux en prod
- Monitoring actif

---

## 🎯 Récapitulatif des priorités

**Phase 1-2 :** Configuration BO (Tickets #1-5) - **1-2 jours**
**Phase 3 :** Création sections (Tickets #6-12) - **1 jour**
**Phase 4-5 :** Navigation & Routes (Tickets #13-14) - **0.5 jour**
**Phase 6 :** Contenu test (Ticket #15) - **0.5 jour**
**Phase 7 :** Structure templates (Tickets #16-18) - **0.5 jour**
**Phase 8 :** Design & Front (Tickets #19-33) - **3-5 jours**
**Phase 9 :** Finitions (Tickets #34-37) - **1 jour**

**Total estimé : 7-10 jours de travail**

---

Bonne chance avec le SWU ! 🎮🤼‍♂️
