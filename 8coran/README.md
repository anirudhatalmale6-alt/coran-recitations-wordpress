# 8Coran — thème WordPress pour une compilation de récitations

Un thème WordPress autonome : **aucun plugin requis**. Un récitateur par fiche,
les 114 sourates sur chaque fiche, un lecteur qui enchaîne les sourates et qui
retient où l'on s'est arrêté.

Interface en **français, arabe (lecture de droite à gauche) ou anglais**, au
choix dans le panneau.

---

## Ce que le thème NE fait PAS

**Il n'héberge aucun fichier audio.** Chaque fiche porte l'adresse d'un dossier
chez une source (par défaut `mp3quran.net`), et les fichiers sont lus depuis
cette source. Le site sert des pages, pas des mp3.

C'est un choix, pas une limite technique : le Coran complet d'un seul récitateur
pèse plusieurs gigaoctets, et 200 récitateurs représentent des centaines de
gigaoctets de stockage et de bande passante par mois. Si un jour vous voulez
héberger les fichiers vous-même, il n'y a qu'un champ à changer par fiche —
l'adresse du dossier — et rien d'autre dans le thème ne bouge.

---

## Installation

1. Tableau de bord → **Apparence → Thèmes → Ajouter → Téléverser un thème**
2. Choisir `8coran-1.0.0.zip`, installer, activer.
3. **Réglages → Permaliens** → enregistrer une fois (pour les adresses
   `/recitateur/...` et `/riwaya/...`).
4. Menu **Récitateurs → Importer** → tout cocher → *Importer la sélection*.

Rien d'autre. Pas de plugin, pas de page à créer à la main.

## Le panneau

### Récitateurs
La liste des fiches. Une fiche = **un enregistrement** : un même récitateur qui a
enregistré le Coran en deux riwayat a deux fiches.

Chaque fiche a un bloc « L'enregistrement » avec :

| Champ | À quoi il sert |
|---|---|
| Adresse du dossier audio | le dossier qui contient les 114 fichiers |
| **Tester l'adresse** | va lire l'en-tête du fichier de la sourate 1 chez la source et dit ce qu'il trouve |
| Sourates disponibles | numéros séparés par des virgules ; vide = les 114 |
| Nom de l'enregistrement | affiché sous le nom du récitateur |
| Nom et adresse de la source | affichés en bas de la fiche |
| Lettre | pour le filtre alphabétique ; déduite du nom si laissée vide |

Le nom du fichier d'une sourate est **le numéro sur trois chiffres suivi de
`.mp3`** : `.../akdr/001.mp3`, `.../akdr/114.mp3`.

### Récitateurs → Importer
Récupère la liste publique de `mp3quran.net` (241 récitateurs, 287
enregistrements, dont 202 Corans complets au moment de l'écriture) et crée les
fiches. **Réimporter ne crée pas de doublon** : chaque fiche garde une clé
d'import et une deuxième passe met à jour au lieu d'ajouter.

L'import copie des **noms** et des **adresses de dossiers**. Il ne copie aucun
fichier audio.

### Récitateurs → Vérifier les adresses
Passe sur toutes les fiches et demande à la source la **première** et la
**dernière** sourate de chaque liste.

Pourquoi les deux bornes et pas la sourate 1 : une récitation partielle ne
contient pas forcément la sourate 1. Un premier passage testé sur la sourate 1
avait donné 28 « échecs » qui n'en étaient pas — la question était mauvaise, pas
les fiches.

Une réponse n'est comptée bonne que si elle est un **200**, d'un **type audio**
et d'une **taille non nulle** : une page d'erreur répond 200 elle aussi.

### Récitateurs → Réglages
Langue de l'interface (français / arabe / anglais), titre et texte d'accueil,
nombre de récitateurs par page, affichage du lien de téléchargement.

En arabe, la page entière passe en `dir="rtl"` et le **nom arabe du récitateur
devient le titre** de la fiche.

---

## Le site public

- **L'annuaire** : recherche, filtre par riwaya, filtre alphabétique, pastille
  « Coran complet » ou « N sourates ».
- **La recherche** regarde le titre, le **nom arabe**, les **autres graphies**
  du nom et le nom de l'enregistrement. « Afasy » trouve « Mishary Al Afasi » et
  « Sudais » trouve « Abderrahmane Soudais », parce que les noms sont comparés
  après normalisation (accents enlevés, `ou`→`u`, `y`→`i`, lettres doublées
  ramenées à une). Ce n'est pas une recherche phonétique et ça ne prétend pas
  l'être : c'est un filet **en plus** de la recherche normale.
- **La fiche** : les sourates avec le nom arabe, la translittération, le sens du
  titre (en français seulement), mecquoise/médinoise et les pages du moushaf.
- **Le lecteur** : lecture, sourate précédente/suivante, barre de progression,
  répétition (aucune / cette sourate / tout), enchaînement automatique, barre
  d'espace pour mettre en pause.
- **La reprise** : le site retient la dernière sourate écoutée et sa position,
  **dans le navigateur** (rien n'est envoyé nulle part), et propose « Reprendre
  l'écoute » sur l'accueil.
- **A+** agrandit tout le texte, et le choix est retenu.

**Sans JavaScript**, l'annuaire et les fiches s'affichent normalement et chaque
sourate reste **un lien direct vers son fichier** : le navigateur l'ouvre avec
son propre lecteur. Le lecteur du site n'ajoute que le confort.

---

## Les données

`inc/sourates.php` (les 114 sourates, les 20 riwayat) est **généré**, pas écrit à
la main : `outils/generer_sourates.py` le produit à partir des instantanés JSON
de `donnees/`, et `outils/tests.py` relit ces mêmes JSON pour comparer ligne à
ligne. Une sourate mal recopiée serait une différence, pas une faute silencieuse.

## Les contrôles

```
python3 outils/tests.py             # 45 contrôles : données, pages, recherche, réglages, audio
python3 outils/essai_navigateur.py  # 26 contrôles dans un vrai navigateur
python3 outils/essai_import.py      # 8 contrôles : le parcours réel d'installation
python3 outils/verifier_serveurs.py serveurs.json   # une passe sur toutes les fiches
```

Mesures du dernier passage :

- 287 fiches importées, **0 doublon** après une seconde passe d'import.
- 287 fiches vérifiées (première et dernière sourate) : **286 répondent**.
  La seule en échec est *Abdellah Al-Bourimi* — la source annonce la sourate 114
  dans sa liste, mais le fichier n'existe pas chez elle. C'est une erreur de la
  source, pas du thème ; la fiche est signalée par l'outil de vérification.
- Le son a été vérifié **en le lisant** dans un navigateur, pas en lisant le
  code : `currentTime` avance, la durée est connue, la sourate suivante charge.
- Le `.zip` a été installé sur un WordPress **neuf et vide**, puis l'import a
  été fait **par le formulaire du panneau** : 3 fiches créées, et un second
  envoi identique a donné 0 créées / 3 mises à jour.

## Compatibilité

WordPress 6.0+, PHP 7.4+. Aucun plugin. Aucune requête vers un service tiers
côté visiteur, à part le fichier audio lui-même.
