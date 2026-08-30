#!/usr/bin/env python3
"""Le parcours reel d'installation, dans le navigateur, sur un WordPress vide.

Ce qui est teste ici n'est pas la fonction d'import mais le CHEMIN que suit la
personne : se connecter, ouvrir Recitateurs > Importer, cocher, envoyer le
formulaire, et retrouver les fiches sur le site public. Une fonction qui marche
en ligne de commande peut tres bien echouer derriere son propre formulaire.

  HC_BASE=http://127.0.0.1:8804 python3 outils/essai_import.py
"""
import os
import sys

from playwright.sync_api import sync_playwright

BASE = os.environ.get('HC_BASE', 'http://127.0.0.1:8804')
SHOTS = os.environ.get('HC_SHOTS', '/var/lib/freelancer/projects/40478471/8coran/apercus')

resultats = []


def t(nom, ok, detail=''):
    resultats.append((nom, bool(ok), detail))
    print('%s %s%s' % ('OK   ' if ok else 'ECHEC', nom,
                       ('  -> ' + str(detail)) if detail else ''))


def main():
    with sync_playwright() as p:
        nav = p.chromium.launch()
        ctx = nav.new_context(viewport={'width': 1280, 'height': 800})
        page = ctx.new_page()

        # Un site vide ne doit pas avoir l'air casse.
        page.goto(BASE + '/', wait_until='domcontentloaded')
        t('un site sans aucune fiche s\'affiche quand meme',
          page.locator('.hc-vide').count() == 1,
          page.locator('.hc-vide').inner_text().strip()[:60])

        page.goto(BASE + '/wp-login.php', wait_until='domcontentloaded')
        page.fill('#user_login', 'admin')
        page.fill('#user_pass', 'admin')
        page.click('#wp-submit')
        page.wait_for_load_state('domcontentloaded')

        page.goto(BASE + '/', wait_until='domcontentloaded')
        t('connecte, le site vide explique quoi faire',
          'Importer' in page.locator('.hc-vide').inner_text())

        page.goto(BASE + '/wp-admin/edit.php?post_type=recitateur&page=huitcoran-import',
                  wait_until='domcontentloaded', timeout=60000)
        lignes = page.locator('table.widefat tbody tr').count()
        t('la page d\'import liste ce que la source propose', lignes > 0, '%d lignes' % lignes)

        cases = page.locator('.hc-case')
        for i in range(3):
            cases.nth(i).check()
        noms = [page.locator('table.widefat tbody tr').nth(i).locator('td').nth(1).inner_text()
                for i in range(3)]
        page.locator('button[name="hc_importer"]').first.click()
        page.wait_for_selector('.notice', timeout=120000)
        message = page.locator('.notice').first.inner_text().strip()
        t('le formulaire d\'import rend compte de ce qu\'il a fait',
          '3 demandés' in message and '3 créés' in message, message)
        page.screenshot(path=os.path.join(SHOTS, '12-import-fait.png'))

        # Deuxieme envoi identique : mise a jour, pas de doublon.
        for i in range(3):
            cases.nth(i).check()
        page.locator('button[name="hc_importer"]').first.click()
        page.wait_for_selector('.notice', timeout=120000)
        message2 = page.locator('.notice').first.inner_text().strip()
        t('un second import identique met a jour au lieu de dupliquer',
          '0 créés' in message2 and '3 mis à jour' in message2, message2)

        page.goto(BASE + '/', wait_until='domcontentloaded')
        cartes = page.locator('.hc-carte').count()
        t('les fiches importees apparaissent sur le site public',
          cartes == 3, '%d cartes pour 3 imports' % cartes)

        page.locator('.hc-carte-nom a').first.click()
        page.wait_for_selector('.hc-sourates')
        nb = page.locator('.hc-sourate').count()
        t('la fiche importee a bien ses sourates', nb > 0, '%s : %d sourates' % (noms[0], nb))
        lien = page.locator('.hc-sourate-lien').first.get_attribute('href')
        t('la sourate pointe sur le fichier de la source',
          lien.startswith('https://') and lien.endswith('.mp3'), lien)

        nav.close()

    rates = [r for r in resultats if not r[1]]
    print('\n%d controles, %d verts, %d rouges'
          % (len(resultats), len(resultats) - len(rates), len(rates)))
    return 1 if rates else 0


if __name__ == '__main__':
    sys.exit(main())
