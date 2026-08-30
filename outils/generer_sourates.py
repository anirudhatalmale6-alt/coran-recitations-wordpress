#!/usr/bin/env python3
"""Ecrit inc/sourates.php a partir des instantanes JSON de donnees/.

Rien n'est recopie a la main : le fichier PHP est genere, et tests.py
le relit pour le comparer aux memes JSON. Une sourate mal transcrite
serait donc une difference, pas une faute silencieuse.
"""
import json
import os

RACINE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DONNEES = os.path.join(RACINE, 'donnees')
SORTIE = os.path.join(RACINE, 'site', 'wp-content', 'themes', '8coran',
                      'inc', 'sourates.php')


def charger(nom, cle):
    with open(os.path.join(DONNEES, nom), encoding='utf-8') as f:
        return json.load(f)[cle]


def table():
    ar = dict((s['id'], s) for s in charger('suwar_ar.json', 'suwar'))
    fr = dict((s['id'], s) for s in charger('suwar_fr.json', 'suwar'))
    en = dict((s['id'], s) for s in charger('suwar_en.json', 'suwar'))
    assert len(ar) == len(fr) == len(en) == 114, (len(ar), len(fr), len(en))
    lignes = []
    for i in range(1, 115):
        lignes.append({
            'id': i,
            'ar': ar[i]['name'].strip(),
            'tr': en[i]['name'].strip(),
            'fr': fr[i]['name'].strip(),
            'mek': int(ar[i]['makkia']),
            'p1': int(ar[i]['start_page']),
            'p2': int(ar[i]['end_page']),
        })
    return lignes


def php_str(s):
    return "'" + s.replace('\\', '\\\\').replace("'", "\\'") + "'"


def ecrire():
    lignes = table()
    riw = charger('riw_fr.json', 'riwayat')
    out = ["<?php",
           "/**",
           " * Les 114 sourates et les riwayat.",
           " *",
           " * GENERE par outils/generer_sourates.py depuis donnees/suwar_*.json",
           " * (instantane de l'API publique mp3quran.net v3). Ne pas editer a la",
           " * main : relancer le script, puis outils/tests.py qui recompare.",
           " *",
           " * ar = nom arabe, tr = translitteration, fr = sens du titre en francais,",
           " * mek = 1 si mecquoise, 0 si medinoise, p1/p2 = pages du moushaf.",
           " */",
           "",
           "if ( ! defined( 'ABSPATH' ) ) { exit; }",
           "",
           "function huitcoran_sourates() {",
           "\treturn array("]
    for l in lignes:
        out.append("\t\t%d => array('ar' => %s, 'tr' => %s, 'fr' => %s, "
                   "'mek' => %d, 'p1' => %d, 'p2' => %d),"
                   % (l['id'], php_str(l['ar']), php_str(l['tr']),
                      php_str(l['fr']), l['mek'], l['p1'], l['p2']))
    out.append("\t);")
    out.append("}")
    out.append("")
    out.append("function huitcoran_riwayat() {")
    out.append("\treturn array(")
    for r in riw:
        out.append("\t\t%d => %s," % (r['id'], php_str(r['name'])))
    out.append("\t);")
    out.append("}")
    out.append("")
    os.makedirs(os.path.dirname(SORTIE), exist_ok=True)
    with open(SORTIE, 'w', encoding='utf-8') as f:
        f.write("\n".join(out))
    print("%s : %d sourates, %d riwayat" % (SORTIE, len(lignes), len(riw)))


if __name__ == '__main__':
    ecrire()
