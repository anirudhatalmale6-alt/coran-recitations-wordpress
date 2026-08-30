# Coran — compilation de récitations (thème WordPress)

Thème WordPress autonome, **sans aucun plugin** : un récitateur par fiche, les
114 sourates sur chaque fiche, un lecteur qui enchaîne et qui retient où l'on
s'est arrêté. Interface **française, arabe (droite à gauche) ou anglaise**.

## À installer

`8coran.zip` → Apparence → Thèmes → Ajouter → Téléverser un thème → activer,
puis **Réglages → Permaliens** (enregistrer une fois), puis
**Récitateurs → Importer**.

## Ce qu'il y a ici

| Dossier | Contenu |
|---|---|
| `8coran/` | le thème (source) — sa documentation complète est dans `8coran/README.md` |
| `8coran.zip` | le même thème, prêt à téléverser dans WordPress |
| `outils/` | le générateur des 114 sourates et les trois jeux de contrôles |
| `donnees/` | les instantanés JSON de l'API publique mp3quran.net qui servent de référence |
| `apercus/` | captures d'écran |

## Le point important

**Le site n'héberge aucun fichier audio.** Chaque fiche porte l'adresse d'un
dossier chez la source, et les fichiers sont lus depuis cette source. Pour
héberger les fichiers soi-même, il n'y a qu'un champ à changer par fiche.

## Les contrôles

```bash
python3 outils/tests.py             # 45 contrôles : données, pages, recherche, réglages, audio
python3 outils/essai_navigateur.py  # 26 contrôles dans un vrai navigateur
python3 outils/verifier_serveurs.py serveurs.json
```

Dernier passage : 287 fiches importées, 0 doublon après une seconde passe ;
287 fiches vérifiées sur leur première et leur dernière sourate, **286
répondent** ; le son a été vérifié en le **lisant** dans un navigateur, pas en
lisant le code.
