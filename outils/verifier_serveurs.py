#!/usr/bin/env python3
"""Verifie que chaque fiche pointe sur des fichiers audio qui repondent vraiment.

Deux requetes HEAD par fiche - la PREMIERE et la DERNIERE sourate de la fiche.
Le fichier n'est jamais telecharge : seuls le code, le type et la taille sont lus.

Pourquoi la premiere et la derniere, et pas la sourate 1 : la sourate 1 n'existe
pas dans les recitations partielles. Un premier passage teste sur la sourate 1
avait donne 28 "echecs" qui n'en etaient pas - la question etait mauvaise, pas
les fiches. Tester les deux bornes de la liste reelle repond a la vraie question :
"les adresses que la page va servir existent-elles ?"

Un 200 ne suffit pas : une page d'erreur repond 200 elle aussi. Il faut un type
audio et une taille non nulle.

  export : wp eval 'inclus dans le README' -> serveurs.json
  usage  : python3 verifier_serveurs.py serveurs.json
"""
import concurrent.futures
import json
import sys
import urllib.error
import urllib.request

TIMEOUT = 20
FILS = 12


def url_sourate(serveur, n):
    if not serveur:
        return ''
    if not serveur.endswith('/'):
        serveur += '/'
    return '%s%03d.mp3' % (serveur, n)


def tester(url):
    """(ok, detail) - ok seulement si 200 ET type audio ET taille non nulle."""
    req = urllib.request.Request(url, method='HEAD',
                                 headers={'User-Agent': 'verification/1.0'})
    try:
        with urllib.request.urlopen(req, timeout=TIMEOUT) as r:
            code = r.status
            typ = r.headers.get('Content-Type', '')
            taille = int(r.headers.get('Content-Length') or 0)
    except urllib.error.HTTPError as e:
        return False, 'HTTP %d' % e.code
    except Exception as e:  # reseau, DNS, TLS, delai
        return False, type(e).__name__
    if code != 200:
        return False, 'HTTP %d' % code
    if 'audio' not in typ:
        return False, '200 mais type %r' % typ
    if taille <= 0:
        return False, '200 mais taille nulle'
    return True, '%d o' % taille


def une_fiche(fiche):
    _id, titre, serveur, nb, premiere, derniere = fiche[:6]
    b1, d1 = tester(url_sourate(serveur, premiere))
    b2, d2 = tester(url_sourate(serveur, derniere))
    return {
        'titre': titre, 'serveur': serveur, 'nb': nb,
        'premiere': premiere, 'derniere': derniere,
        'ok': b1 and b2, 'detail_premiere': d1, 'detail_derniere': d2,
    }


def main():
    if len(sys.argv) < 2:
        print(__doc__)
        return 2
    fiches = json.load(open(sys.argv[1]))
    ok, ko = 0, []
    with concurrent.futures.ThreadPoolExecutor(max_workers=FILS) as ex:
        for r in ex.map(une_fiche, fiches):
            if r['ok']:
                ok += 1
            else:
                ko.append(r)
    print('%d fiches testees (premiere et derniere sourate) : %d repondent, '
          '%d en echec' % (len(fiches), ok, len(ko)))
    for r in sorted(ko, key=lambda x: x['titre']):
        print('  ECHEC %-32s %s  [%d sourates, %s..%s]  premiere=%s  derniere=%s'
              % (r['titre'][:32], r['serveur'], r['nb'], r['premiere'],
                 r['derniere'], r['detail_premiere'], r['detail_derniere']))
    return 0 if not ko else 1


if __name__ == '__main__':
    sys.exit(main())
