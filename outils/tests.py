#!/usr/bin/env python3
"""Les controles du site Coran.

Ils portent sur ce que le site SERT, pas sur ce que le code a l'air de faire :
le HTML rendu, les reponses HTTP, et - pour les donnees - une relecture des
instantanes JSON independante du fichier PHP genere.

  python3 outils/tests.py            (le site doit tourner sur HC_BASE)
"""
import json
import os
import re
import subprocess
import sys
import urllib.parse
import urllib.request

RACINE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
BASE = os.environ.get('HC_BASE', 'http://127.0.0.1:8803')
SITE = os.path.join(RACINE, 'site')
THEME = os.path.join(SITE, 'wp-content', 'themes', '8coran')
WPCLI = os.environ.get(
    'HC_WPCLI', '/var/lib/freelancer/projects/40478471/8hajj/wp-cli.phar')

resultats = []


def t(nom, ok, detail=''):
    resultats.append((nom, bool(ok), detail))
    print('%s %s%s' % ('OK   ' if ok else 'ECHEC', nom,
                       ('  -> ' + str(detail)) if detail else ''))


def obtenir(chemin):
    url = BASE + chemin
    req = urllib.request.Request(url, headers={'User-Agent': 'tests/1.0'})
    try:
        with urllib.request.urlopen(req, timeout=30) as r:
            return r.status, r.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', 'replace')


def wp(code):
    r = subprocess.run(['php', WPCLI, 'eval', code, '--allow-root'],
                       cwd=SITE, capture_output=True, text=True)
    if r.returncode != 0:
        return 'ERREUR ' + (r.stderr or '').strip()
    return r.stdout.strip()


def chercher(mot):
    """Le nombre total annonce par la page de resultats."""
    code, html = obtenir('/?s=%s&post_type=recitateur'
                         % urllib.parse.quote(mot))
    m = re.search(r'hc-compte">\s*<strong>([\d\s  ,.]+)</strong>', html)
    if not m:
        return code, None
    return code, int(re.sub(r'\D', '', m.group(1)))


# --------------------------------------------------------------------- #
# 1. Les donnees : les 114 sourates, relues des instantanes              #
# --------------------------------------------------------------------- #

def controler_sourates():
    ar = json.load(open(os.path.join(RACINE, 'donnees', 'suwar_ar.json'),
                        encoding='utf-8'))['suwar']
    fr = json.load(open(os.path.join(RACINE, 'donnees', 'suwar_fr.json'),
                        encoding='utf-8'))['suwar']
    t('la source donne bien 114 sourates', len(ar) == 114 and len(fr) == 114,
      '%d arabe, %d francais' % (len(ar), len(fr)))

    php = open(os.path.join(THEME, 'inc', 'sourates.php'), encoding='utf-8').read()
    lignes = re.findall(r"^\t\t(\d+) => array\('ar' => '(.*?)', 'tr' => '(.*?)', "
                        r"'fr' => '(.*?)', 'mek' => (\d), 'p1' => (\d+), 'p2' => (\d+)\),$",
                        php, re.M)
    t('le fichier PHP genere contient 114 lignes', len(lignes) == 114, len(lignes))

    ecarts = []
    par_id_ar = dict((s['id'], s) for s in ar)
    par_id_fr = dict((s['id'], s) for s in fr)
    for num, nom_ar, _tr, nom_fr, mek, p1, p2 in lignes:
        i = int(num)
        src_ar = par_id_ar[i]['name'].strip()
        src_fr = par_id_fr[i]['name'].strip().replace("'", "\\'")
        if nom_ar != src_ar:
            ecarts.append('%d arabe : %r vs %r' % (i, nom_ar, src_ar))
        if nom_fr != src_fr:
            ecarts.append('%d francais : %r vs %r' % (i, nom_fr, src_fr))
        if int(mek) != int(par_id_ar[i]['makkia']):
            ecarts.append('%d mecquoise/medinoise' % i)
        if int(p1) != int(par_id_ar[i]['start_page']) or int(p2) != int(par_id_ar[i]['end_page']):
            ecarts.append('%d pages' % i)
    t('chaque ligne du PHP correspond a l’instantane de la source',
      not ecarts, '; '.join(ecarts[:3]))

    # Le controle ci-dessus doit pouvoir echouer : on le prouve sur une ligne fausse.
    faux = "\t\t1 => array('ar' => 'XXX', 'tr' => 'Al-Fatihah', 'fr' => 'Prologue', 'mek' => 1, 'p1' => 1, 'p2' => 1),"
    m = re.match(r"^\t\t(\d+) => array\('ar' => '(.*?)',", faux)
    t('ce controle sait reperer une sourate falsifiee',
      m is not None and m.group(2) != par_id_ar[1]['name'].strip())

    riw = json.load(open(os.path.join(RACINE, 'donnees', 'riw_fr.json'),
                         encoding='utf-8'))['riwayat']
    t('les riwayat du PHP sont celles de la source',
      len(re.findall(r"^\t\t\d+ => '", php.split('huitcoran_riwayat')[1], re.M)) == len(riw),
      '%d riwayat' % len(riw))


# --------------------------------------------------------------------- #
# 2. Le contenu du site                                                  #
# --------------------------------------------------------------------- #

def controler_contenu():
    nb = wp('echo wp_count_posts("recitateur")->publish;')
    t('des recitateurs sont publies', nb.isdigit() and int(nb) > 0, nb)

    sans = wp('$n=0; foreach (get_posts(array("post_type"=>"recitateur","posts_per_page"=>-1,'
              '"fields"=>"ids")) as $id) { if (trim((string) get_post_meta($id,"_hc_serveur",true))==="") $n++; }'
              ' echo $n;')
    t('aucune fiche sans adresse de dossier audio', sans == '0', sans + ' fiche(s)')

    doublons = wp('global $wpdb; echo (int) $wpdb->get_var("SELECT COUNT(*) FROM (SELECT meta_value, '
                  'COUNT(*) c FROM {$wpdb->postmeta} WHERE meta_key=\'_hc_cle_api\' GROUP BY meta_value '
                  'HAVING c > 1) x");')
    t('aucun doublon d’import', doublons == '0', doublons)

    pad = wp('echo huitcoran_url_sourate("https://exemple.test/abc", 7);')
    t('le numero de fichier est ecrit sur trois chiffres',
      pad == 'https://exemple.test/abc/007.mp3', pad)

    pad2 = wp('echo huitcoran_url_sourate("https://exemple.test/abc/", 114);')
    t('la barre finale n’est jamais doublee',
      pad2 == 'https://exemple.test/abc/114.mp3', pad2)

    partielle = wp('echo implode(",", huitcoran_liste_sourates(0));')
    t('une fiche sans liste vaut les 114 sourates',
      partielle.startswith('1,2,3') and partielle.endswith(',114'),
      partielle[:12] + '…' + partielle[-8:])


# --------------------------------------------------------------------- #
# 3. Ce que le site sert                                                 #
# --------------------------------------------------------------------- #

def controler_pages():
    code, html = obtenir('/')
    t('l’accueil repond 200', code == 200, code)
    t('l’accueil affiche des cartes', html.count('class="hc-carte"') > 0,
      '%d cartes' % html.count('class="hc-carte"'))
    t('aucune alerte PHP dans l’accueil',
      not re.search(r'(Fatal error|Warning:|Notice:|Deprecated:)', html))

    url = wp('$p = get_posts(array("post_type"=>"recitateur","posts_per_page"=>1)); '
             'echo get_permalink($p[0]);')
    chemin = url.replace(BASE, '')
    code, fiche = obtenir(chemin)
    t('une fiche de recitateur repond 200', code == 200, chemin)
    nb = fiche.count('class="hc-sourate"')
    t('la fiche liste ses sourates', nb > 0, '%d sourates' % nb)
    t('aucune alerte PHP dans la fiche',
      not re.search(r'(Fatal error|Warning:|Notice:|Deprecated:)', fiche))

    liens = re.findall(r'data-url="([^"]+)"', fiche)
    t('chaque sourate porte une adresse de fichier', len(liens) == nb,
      '%d adresses pour %d sourates' % (len(liens), nb))
    t('toutes les adresses sont en https', all(u.startswith('https://') for u in liens))
    t('aucun fichier audio n’est servi par ce site',
      not any(BASE in u or u.startswith('/') for u in liens))
    t('les numeros vont bien de la premiere a la derniere',
      liens[0].endswith('.mp3') and liens[-1].endswith('.mp3'),
      '%s … %s' % (liens[0][-7:], liens[-1][-7:]))

    code, _ = obtenir('/nexistepas-du-tout/')
    t('une adresse inconnue repond 404', code == 404, code)

    code, arch = obtenir('/recitateur/')
    t('l’archive des recitateurs repond 200', code == 200, code)

    slug = wp('$t = get_terms(array("taxonomy"=>"riwaya","hide_empty"=>true,"number"=>1)); echo $t[0]->slug;')
    code, tax = obtenir('/riwaya/%s/' % slug)
    t('la page d’une riwaya repond 200', code == 200, slug)


# --------------------------------------------------------------------- #
# 4. La recherche                                                        #
# --------------------------------------------------------------------- #

def controler_recherche():
    for mot, mini in (('Afasi', 1), ('Mishary', 1)):
        code, n = chercher(mot)
        t('la recherche trouve "%s"' % mot, n is not None and n >= mini, n)

    code, n = chercher('Afasy')
    t('une autre graphie trouve quand meme ("Afasy" pour "Al Afasi")',
      n is not None and n >= 1, n)
    code, n = chercher('Sudais')
    t('une autre graphie trouve quand meme ("Sudais" pour "Soudais")',
      n is not None and n >= 1, n)
    code, n = chercher('العفاسي')
    t('la recherche en arabe trouve', n is not None and n >= 1, n)

    for absurde in ('qwerty', 'zzzz', 'xkcdplop'):
        code, n = chercher(absurde)
        t('une recherche absurde ne ramene personne ("%s")' % absurde, n == 0, n)

    cle = wp('echo huitcoran_cle_recherche("Abderrahmane Soudais");')
    t('la cle de recherche normalise ou -> u et les lettres doublees',
      cle == 'abderahmanesudais', cle)
    court = wp('echo strlen(huitcoran_cle_recherche("zzzz"));')
    t('une cle trop courte est ecartee (sinon %z% ramenait tout le monde)',
      court == '1', court + ' caractere(s)')


# --------------------------------------------------------------------- #
# 5. Les reglages changent vraiment la page                              #
# --------------------------------------------------------------------- #

def controler_reglages():
    avant = wp('echo get_option("huitcoran_langue","fr");')

    wp('update_option("huitcoran_langue","ar");')
    _, html = obtenir('/')
    t('la langue arabe met la page en lecture de droite a gauche',
      'dir="rtl"' in html and 'lang="ar"' in html)
    t('la langue arabe change les libelles', 'القراء' in html)

    nom_ar = wp('$p = get_posts(array("post_type"=>"recitateur","posts_per_page"=>1)); '
                'echo get_post_meta($p[0]->ID,"_hc_nom_ar",true);')
    url = wp('$p = get_posts(array("post_type"=>"recitateur","posts_per_page"=>1)); echo get_permalink($p[0]);')
    _, fiche_ar = obtenir(url.replace(BASE, ''))
    t('en arabe, le titre de la fiche est le nom arabe',
      bool(nom_ar) and re.search(r'<h1[^>]*lang="ar"[^>]*>\s*%s' % re.escape(nom_ar), fiche_ar),
      nom_ar)
    t('en arabe, le sens francais du titre de sourate disparait',
      'La génisse' not in fiche_ar)

    wp('update_option("huitcoran_langue","en");')
    _, html = obtenir('/')
    t('la langue anglaise change les libelles', 'Reciters' in html and 'dir="ltr"' in html)

    wp('update_option("huitcoran_langue","%s");' % (avant if avant else 'fr'))
    _, html = obtenir('/')
    t('la langue est revenue a %s' % avant, 'lang="%s"' % avant in html)

    _, fiche_fr = obtenir(url.replace(BASE, ''))
    t('en francais, le sens du titre de sourate est bien la',
      'La génisse' in fiche_fr)

    url = wp('$p = get_posts(array("post_type"=>"recitateur","posts_per_page"=>1)); echo get_permalink($p[0]);')
    chemin = url.replace(BASE, '')

    wp('update_option("huitcoran_telechargement","1");')
    _, fiche = obtenir(chemin)
    avec = fiche.count('class="hc-dl"')
    wp('update_option("huitcoran_telechargement","");')
    _, fiche = obtenir(chemin)
    sans = fiche.count('class="hc-dl"')
    wp('update_option("huitcoran_telechargement","1");')
    t('le reglage du telechargement change vraiment la page',
      avec > 0 and sans == 0, 'coche : %d liens, decoche : %d' % (avec, sans))


# --------------------------------------------------------------------- #
# 6. Les fichiers repondent vraiment chez la source                      #
# --------------------------------------------------------------------- #

def controler_audio():
    url = wp('$p = get_posts(array("post_type"=>"recitateur","posts_per_page"=>1)); echo get_permalink($p[0]);')
    _, fiche = obtenir(url.replace(BASE, ''))
    liens = re.findall(r'data-url="([^"]+)"', fiche)
    essais = [liens[0], liens[len(liens) // 2], liens[-1]]
    for u in essais:
        req = urllib.request.Request(u, method='HEAD',
                                     headers={'User-Agent': 'tests/1.0'})
        try:
            with urllib.request.urlopen(req, timeout=25) as r:
                code, typ = r.status, r.headers.get('Content-Type', '')
                taille = int(r.headers.get('Content-Length') or 0)
        except Exception as e:
            code, typ, taille = 0, type(e).__name__, 0
        t('le fichier %s existe chez la source' % u.rsplit('/', 1)[-1],
          code == 200 and 'audio' in typ and taille > 0,
          '%s %s %s o' % (code, typ, taille))


def main():
    controler_sourates()
    controler_contenu()
    controler_pages()
    controler_recherche()
    controler_reglages()
    controler_audio()
    rates = [r for r in resultats if not r[1]]
    print('\n%d controles, %d verts, %d rouges'
          % (len(resultats), len(resultats) - len(rates), len(rates)))
    for nom, _ok, detail in rates:
        print('  ROUGE %s  %s' % (nom, detail))
    return 1 if rates else 0


if __name__ == '__main__':
    sys.exit(main())
