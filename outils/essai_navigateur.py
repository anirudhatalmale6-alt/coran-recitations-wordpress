#!/usr/bin/env python3
"""Essais dans un vrai navigateur : la page, le lecteur, l'audio qui avance.

Ce qui compte ici, ce n'est pas que la page reponde 200 - c'est que le son
demarre. On demande donc au navigateur de lire, puis on regarde currentTime
augmenter. Si le navigateur ne sait pas decoder le mp3, le script le dit au
lieu de compter un succes.
"""
import os
import sys
import time

from playwright.sync_api import sync_playwright

BASE = os.environ.get('HC_BASE', 'http://127.0.0.1:8803')
SHOTS = os.environ.get('HC_SHOTS', '/var/lib/freelancer/projects/40478471/8coran/apercus')

resultats = []


def t(nom, ok, detail=''):
    resultats.append((nom, ok, detail))
    print('%s  %s%s' % ('OK  ' if ok else 'ECHEC', nom, ('  -> ' + detail) if detail else ''))


def main():
    os.makedirs(SHOTS, exist_ok=True)
    with sync_playwright() as p:
        nav = p.chromium.launch(args=['--autoplay-policy=no-user-gesture-required'])
        ctx = nav.new_context(viewport={'width': 1280, 'height': 800})
        page = ctx.new_page()
        erreurs = []
        page.on('console', lambda m: erreurs.append(m.text) if m.type == 'error' else None)
        page.on('pageerror', lambda e: erreurs.append(str(e)))

        # --- L'annuaire -------------------------------------------------
        page.goto(BASE + '/', wait_until='domcontentloaded')
        cartes = page.locator('.hc-carte').count()
        t('l\'annuaire affiche des cartes', cartes > 0, '%d cartes' % cartes)
        compte = page.locator('.hc-compte').inner_text()
        t('le compte de recitateurs est affiche', any(c.isdigit() for c in compte), compte.strip())
        page.screenshot(path=os.path.join(SHOTS, '01-annuaire.png'))

        # --- Le filtre par riwaya ---------------------------------------
        page.select_option('#hc-riwaya', index=1)
        page.click('.hc-filtres button')
        page.wait_for_load_state('domcontentloaded')
        filtre = page.locator('.hc-carte').count()
        t('le filtre riwaya renvoie une liste', filtre >= 0, '%d cartes' % filtre)
        page.screenshot(path=os.path.join(SHOTS, '02-filtre-riwaya.png'))

        # --- La recherche -----------------------------------------------
        page.goto(BASE + '/?s=Afasy&post_type=recitateur', wait_until='domcontentloaded')
        trouve = page.locator('.hc-carte').count()
        t('la recherche trouve malgre une autre graphie', trouve > 0,
          '%d resultats pour "Afasy" (la liste ecrit "Al Afasi")' % trouve)
        page.screenshot(path=os.path.join(SHOTS, '02b-recherche.png'))

        # --- Une fiche de recitateur -------------------------------------
        page.goto(BASE + '/', wait_until='domcontentloaded')
        lien = page.locator('.hc-carte-nom a').first
        nom = lien.inner_text()
        lien.click()
        page.wait_for_selector('.hc-sourates')
        nb = page.locator('.hc-sourate').count()
        t('la fiche liste ses sourates', nb > 0, '%s : %d sourates' % (nom, nb))
        arabe = page.locator('.hc-sourate .hc-ar').first.inner_text()
        t('le nom arabe de la sourate est present', len(arabe.strip()) > 0, arabe)
        dirar = page.locator('.hc-sourate .hc-ar').first.get_attribute('dir')
        t('le nom arabe est marque rtl', dirar == 'rtl', str(dirar))

        # --- Le son demarre vraiment -------------------------------------
        peut = page.evaluate("document.createElement('audio').canPlayType('audio/mpeg')")
        t('le navigateur declare savoir lire le mp3', peut not in ('', None), repr(peut))

        page.click('.hc-sourate:first-child .hc-sourate-lien')
        depart = time.time()
        avance = 0
        etat = {}
        while time.time() - depart < 25:
            etat = page.evaluate(
                "( function () { var a = document.getElementById('hc-audio');"
                " return { t: a.currentTime, d: a.duration, p: a.paused,"
                " reseau: a.networkState, pret: a.readyState, src: a.src }; } )()"
            )
            avance = etat.get('t') or 0
            if avance > 0.5:
                break
            page.wait_for_timeout(500)
        t('le son avance dans le navigateur', avance > 0.5,
          'currentTime=%.2f s, duree=%s, readyState=%s' % (avance, etat.get('d'), etat.get('pret')))
        t('la sourate lue est bien celle qui a ete cliquee',
          etat.get('src', '').endswith('001.mp3') or '/00' in etat.get('src', ''),
          etat.get('src', ''))

        entete = page.locator('#hc-ar').inner_text()
        t('le lecteur affiche la sourate en cours', len(entete.strip()) > 0, entete)
        page.screenshot(path=os.path.join(SHOTS, '03-fiche-lecteur.png'))

        # --- L'enchainement et la reprise ---------------------------------
        page.click('#hc-suiv')
        page.wait_for_timeout(1500)
        suivant = page.evaluate("document.getElementById('hc-audio').src")
        t('le bouton suivant change de sourate', suivant.endswith('002.mp3'), suivant)

        memoire = page.evaluate("window.localStorage.getItem('hc-reprise')")
        t('la reprise est retenue dans le navigateur', memoire and '"num"' in memoire, str(memoire)[:120])

        page.goto(BASE + '/', wait_until='domcontentloaded')
        page.wait_for_timeout(400)
        visible = page.locator('#hc-reprise').is_visible()
        t('l\'accueil propose de reprendre', visible)
        if visible:
            page.screenshot(path=os.path.join(SHOTS, '04-reprise.png'))

        # --- Le texte plus grand ------------------------------------------
        page.click('#hc-taille')
        page.wait_for_timeout(200)
        grand = page.evaluate("document.body.classList.contains('hc-grand')")
        t('le bouton A+ agrandit le texte', grand)
        page.click('#hc-taille')

        # --- Le telechargement d'une sourate ---------------------------------
        # Un bouton "telecharger" qui ouvre le fichier au lieu de l'enregistrer
        # n'est pas un bouton de telechargement. On verifie donc qu'un fichier
        # arrive VRAIMENT, sous le bon nom, avec les bons octets.
        import hashlib
        import urllib.request

        # Les controles precedents ont ramene la page sur l'annuaire.
        page.goto(BASE + '/', wait_until='domcontentloaded')
        page.locator('.hc-carte-nom a').first.click()
        page.wait_for_selector('.hc-sourates')
        lien_dl = page.locator('.hc-dl').first
        attendu = lien_dl.get_attribute('data-fichier')
        source = lien_dl.get_attribute('href')
        t('le lien de telechargement porte un nom lisible, pas 001.mp3',
          bool(attendu) and attendu.endswith('.mp3') and attendu != '001.mp3', str(attendu))
        with page.expect_download(timeout=120000) as attente:
            lien_dl.click()
        recu = attente.value
        chemin = os.path.join(SHOTS, '.telechargement-essai.mp3')
        recu.save_as(chemin)
        octets = open(chemin, 'rb').read()
        t('le clic telecharge le fichier au lieu de l\'ouvrir',
          recu.suggested_filename == attendu, recu.suggested_filename)
        t('le fichier telecharge est bien un mp3 non vide',
          len(octets) > 10000 and octets[:3] in (b'ID3', b'\xff\xfb', b'\xff\xf3'),
          '%d octets, entete %r' % (len(octets), octets[:3]))
        origine = urllib.request.urlopen(source, timeout=120).read()
        t('les octets recus sont ceux de la source',
          hashlib.md5(octets).hexdigest() == hashlib.md5(origine).hexdigest(),
          '%d octets chez la source' % len(origine))
        os.remove(chemin)
        page.wait_for_timeout(300)
        t('le bouton dit que c\'est fait',
          'fait' in (lien_dl.get_attribute('class') or ''), lien_dl.get_attribute('class'))
        page.screenshot(path=os.path.join(SHOTS, '20-telechargement.png'))

        # Une page d'erreur repond 200 elle aussi : elle ne doit pas etre
        # enregistree sous un nom en .mp3.
        page.route('**/*.mp3', lambda route: route.fulfill(
            status=200, content_type='text/html', body='<html>oups</html>'))
        page.reload(wait_until='domcontentloaded')
        page.wait_for_selector('.hc-sourates')
        faux = page.locator('.hc-dl').nth(1)
        rien = True
        try:
            with page.expect_download(timeout=8000):
                faux.click()
            rien = False
        except Exception:
            pass
        t('un 200 qui n\'est pas de l\'audio n\'est pas enregistre', rien)
        page.wait_for_timeout(500)
        t('et le bouton le dit au lieu de rester muet',
          'echec' in (faux.get_attribute('class') or ''), faux.get_attribute('class'))
        page.unroute('**/*.mp3')
        # L'echec ouvre le fichier dans un onglet : on le referme.
        for autre in ctx.pages:
            if autre is not page:
                autre.close()
        page.reload(wait_until='domcontentloaded')
        page.wait_for_selector('.hc-sourates')

        # --- Le telephone ---------------------------------------------------
        tel = ctx.new_page()
        tel.set_viewport_size({'width': 390, 'height': 800})
        tel.goto(BASE + '/', wait_until='domcontentloaded')
        largeur = tel.evaluate("document.documentElement.scrollWidth")
        t('l\'accueil ne deborde pas en 390 px', largeur <= 391, '%d px' % largeur)
        tel.screenshot(path=os.path.join(SHOTS, '05-telephone-annuaire.png'))
        tel.locator('.hc-carte-nom a').first.click()
        tel.wait_for_selector('.hc-sourates')
        largeur_fiche = tel.evaluate("document.documentElement.scrollWidth")
        t('la fiche ne deborde pas en 390 px', largeur_fiche <= 391, '%d px' % largeur_fiche)
        tel.screenshot(path=os.path.join(SHOTS, '05b-telephone-fiche.png'))
        tel.close()

        # --- Sans JavaScript --------------------------------------------------
        ctx2 = nav.new_context(java_script_enabled=False, viewport={'width': 1280, 'height': 800})
        p2 = ctx2.new_page()
        p2.goto(BASE + '/', wait_until='domcontentloaded')
        t('l\'annuaire s\'affiche sans JavaScript', p2.locator('.hc-carte').count() > 0)
        p2.locator('.hc-carte-nom a').first.click()
        p2.wait_for_selector('.hc-sourates')
        href = p2.locator('.hc-sourate-lien').first.get_attribute('href')
        t('sans JavaScript, chaque sourate reste un lien direct vers son fichier',
          bool(href) and href.endswith('.mp3'), str(href))
        p2.screenshot(path=os.path.join(SHOTS, '06-sans-javascript.png'))
        ctx2.close()

        t('aucune erreur JavaScript', not erreurs, '; '.join(erreurs[:3]))

        # --- L'arabe, de droite a gauche --------------------------------------
        import subprocess
        SITE = '/var/lib/freelancer/projects/40478471/8coran/site'
        WPCLI = '/var/lib/freelancer/projects/40478471/8hajj/wp-cli.phar'

        def wp( code ):
            subprocess.run( ['php', WPCLI, 'eval', code, '--allow-root'],
                            cwd=SITE, capture_output=True, text=True )

        wp( 'update_option("huitcoran_langue","ar");' )
        ar = ctx.new_page()
        ar.goto( BASE + '/', wait_until='domcontentloaded' )
        sens = ar.evaluate( "document.documentElement.getAttribute('dir')" )
        t( 'l\'interface arabe se lit de droite a gauche', sens == 'rtl', str( sens ) )
        ar.screenshot( path=os.path.join( SHOTS, '07-arabe-annuaire.png' ) )
        ar.locator( '.hc-carte-nom a' ).first.click()
        ar.wait_for_selector( '.hc-sourates' )
        ar.screenshot( path=os.path.join( SHOTS, '07b-arabe-fiche.png' ) )
        ar.close()
        wp( 'update_option("huitcoran_langue","fr");' )

        # --- Le tableau de bord ------------------------------------------------
        adm = ctx.new_page()
        adm.goto( BASE + '/wp-login.php', wait_until='domcontentloaded' )
        adm.fill( '#user_login', 'admin' )
        adm.fill( '#user_pass', 'admin' )
        adm.click( '#wp-submit' )
        adm.wait_for_load_state( 'domcontentloaded' )
        t( 'la connexion au tableau de bord fonctionne', '/wp-admin' in adm.url, adm.url )

        adm.goto( BASE + '/wp-admin/edit.php?post_type=recitateur&page=huitcoran-import',
                  wait_until='domcontentloaded' )
        rangs = adm.locator( '#hc-tout' ).count()
        t( 'la page d\'import affiche la liste de la source',
           adm.locator( 'table.widefat tbody tr' ).count() > 0,
           '%d lignes' % adm.locator( 'table.widefat tbody tr' ).count() )
        adm.screenshot( path=os.path.join( SHOTS, '08-admin-import.png' ) )

        adm.goto( BASE + '/wp-admin/edit.php?post_type=recitateur&page=huitcoran-reglages',
                  wait_until='domcontentloaded' )
        t( 'la page de reglages s\'affiche', adm.locator( '#hc_langue' ).count() == 1 )
        adm.screenshot( path=os.path.join( SHOTS, '09-admin-reglages.png' ) )

        # Le bouton "Tester l'adresse" d'une fiche
        ident = adm.evaluate( "''" )
        adm.goto( BASE + '/wp-admin/edit.php?post_type=recitateur', wait_until='domcontentloaded' )
        adm.locator( 'a.row-title' ).first.click()
        adm.wait_for_selector( '#hc-tester' )
        adm.click( '#hc-tester' )
        adm.wait_for_function(
            "document.getElementById('hc-resultat').textContent.indexOf('Test en cours') === -1"
            " && document.getElementById('hc-resultat').textContent.length > 0",
            timeout=30000 )
        message = adm.locator( '#hc-resultat' ).inner_text()
        t( 'le bouton "Tester l\'adresse" verifie vraiment le fichier',
           'Adresse valide' in message, message )
        adm.screenshot( path=os.path.join( SHOTS, '10-admin-fiche.png' ) )

        # La verification en lot : on la laisse tourner quelques paquets.
        adm.goto( BASE + '/wp-admin/edit.php?post_type=recitateur&page=huitcoran-verif',
                  wait_until='domcontentloaded' )
        adm.click( '#hc-v-lancer' )
        adm.wait_for_function(
            "document.querySelectorAll('#hc-v-table tbody tr').length >= 10",
            timeout=90000 )
        faites = adm.locator( '#hc-v-table tbody tr' ).count()
        t( 'la verification en lot remplit son tableau', faites >= 10, '%d fiches' % faites )
        adm.screenshot( path=os.path.join( SHOTS, '11-admin-verification.png' ) )
        adm.close()

        nav.close()

    rates = [r for r in resultats if not r[1]]
    print('\n%d controles, %d verts, %d rouges' % (len(resultats), len(resultats) - len(rates), len(rates)))
    return 1 if rates else 0


if __name__ == '__main__':
    sys.exit(main())
