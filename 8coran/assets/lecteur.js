/**
 * Le lecteur.
 *
 * Il ne fabrique aucune adresse : il lit celles que le serveur a deja ecrites
 * dans data-url. Sans lui, chaque sourate reste un lien cliquable.
 *
 * Ce qu'il ajoute : enchainer les sourates, retenir ou l'on s'est arrete
 * (dans le navigateur, rien n'est envoye), repeter une sourate ou la serie,
 * et proposer la reprise sur l'accueil.
 */
( function () {
	'use strict';

	var MEMOIRE = 'hc-reprise';
	var T = window.HC || {};

	function txt( cle, defaut ) {
		return T[ cle ] || defaut;
	}

	function minutes( s ) {
		if ( ! isFinite( s ) || s < 0 ) { return '0:00'; }
		var m = Math.floor( s / 60 );
		var r = Math.floor( s % 60 );
		return m + ':' + ( r < 10 ? '0' : '' ) + r;
	}

	function lire( cle ) {
		try {
			var v = window.localStorage.getItem( cle );
			return v ? JSON.parse( v ) : null;
		} catch ( e ) {
			return null;
		}
	}

	function ecrire( cle, valeur ) {
		try {
			window.localStorage.setItem( cle, JSON.stringify( valeur ) );
		} catch ( e ) {
			/* navigation privee, quota : la reprise est un confort, pas une fonction. */
		}
	}

	/* ------------------------------------------------------------------ */
	/* La reprise, proposee sur l'annuaire                                  */
	/* ------------------------------------------------------------------ */

	function poserReprise() {
		var bloc = document.getElementById( 'hc-reprise' );
		var lien = document.getElementById( 'hc-reprise-lien' );
		if ( ! bloc || ! lien ) { return; }
		var m = lire( MEMOIRE );
		if ( ! m || ! m.page || ! m.num ) { return; }
		lien.href = m.page + ( m.page.indexOf( '?' ) === -1 ? '?' : '&' ) + 'sourate=' + m.num;
		lien.textContent = txt( 'reprendre', 'Reprendre' ) + ' — ' +
			( m.recitateur || '' ) + ' · ' + txt( 'sourate', 'Sourate' ) + ' ' + m.num +
			( m.ar ? ' ' + m.ar : '' );
		bloc.hidden = false;
	}

	/* ------------------------------------------------------------------ */
	/* Le lecteur d'une page de recitateur                                  */
	/* ------------------------------------------------------------------ */

	function poserLecteur() {
		var boite = document.getElementById( 'hc-lecteur' );
		var liste = document.getElementById( 'hc-liste' );
		if ( ! boite || ! liste ) { return; }

		var audio   = document.getElementById( 'hc-audio' );
		var bLire   = document.getElementById( 'hc-lire' );
		var bPrec   = document.getElementById( 'hc-prec' );
		var bSuiv   = document.getElementById( 'hc-suiv' );
		var bRep    = document.getElementById( 'hc-repeter' );
		var barre   = document.getElementById( 'hc-barre' );
		var tTemps  = document.getElementById( 'hc-temps' );
		var tDuree  = document.getElementById( 'hc-duree' );
		var eNum    = document.getElementById( 'hc-num' );
		var eAr     = document.getElementById( 'hc-ar' );
		var eTr     = document.getElementById( 'hc-tr' );
		var erreur  = document.getElementById( 'hc-erreur' );

		var lignes = [].slice.call( liste.querySelectorAll( '.hc-sourate' ) );
		if ( ! lignes.length ) { return; }

		var recitateur = boite.getAttribute( 'data-recitateur' ) || '';
		var page       = boite.getAttribute( 'data-page' ) || window.location.href.split( '?' )[ 0 ];
		var courant    = -1;
		var glisse     = false;
		var repet      = 0; /* 0 = non, 1 = cette sourate, 2 = toute la serie */

		var libellesRepet = [
			txt( 'repeter_non', 'Ne pas répéter' ),
			txt( 'repeter_une', 'Répéter cette sourate' ),
			txt( 'repeter_tout', 'Répéter tout' )
		];

		function marquer( i ) {
			for ( var k = 0; k < lignes.length; k++ ) {
				lignes[ k ].classList.toggle( 'en-cours', k === i );
			}
		}

		function charger( i, jouer, position ) {
			if ( i < 0 || i >= lignes.length ) { return; }
			courant = i;
			var li = lignes[ i ];
			audio.src = li.getAttribute( 'data-url' );
			eNum.textContent = li.getAttribute( 'data-num' );
			eAr.textContent  = li.getAttribute( 'data-ar' ) || '';
			eTr.textContent  = li.getAttribute( 'data-tr' ) || '';
			erreur.hidden = true;
			marquer( i );
			if ( position ) {
				audio.addEventListener( 'loadedmetadata', function place() {
					audio.removeEventListener( 'loadedmetadata', place );
					try { audio.currentTime = position; } catch ( e ) {}
				} );
			}
			if ( jouer ) {
				var p = audio.play();
				if ( p && p.catch ) { p.catch( function () { /* lecture refusee tant que rien n'est clique */ } ); }
			}
			retenir();
		}

		function retenir() {
			if ( courant < 0 ) { return; }
			var li = lignes[ courant ];
			ecrire( MEMOIRE, {
				page: page,
				recitateur: recitateur,
				num: li.getAttribute( 'data-num' ),
				ar: li.getAttribute( 'data-ar' ),
				position: Math.floor( audio.currentTime || 0 )
			} );
		}

		bLire.addEventListener( 'click', function () {
			if ( courant < 0 ) { charger( 0, true ); return; }
			if ( audio.paused ) { audio.play(); } else { audio.pause(); }
		} );

		bPrec.addEventListener( 'click', function () {
			charger( courant <= 0 ? lignes.length - 1 : courant - 1, true );
		} );

		bSuiv.addEventListener( 'click', function () {
			charger( courant >= lignes.length - 1 ? 0 : courant + 1, true );
		} );

		bRep.addEventListener( 'click', function () {
			repet = ( repet + 1 ) % 3;
			bRep.textContent = libellesRepet[ repet ];
			bRep.setAttribute( 'aria-pressed', repet ? 'true' : 'false' );
			bRep.classList.toggle( 'actif', repet > 0 );
		} );

		audio.addEventListener( 'play', function () {
			bLire.textContent = '⏸';
			bLire.setAttribute( 'aria-label', txt( 'pause', 'Pause' ) );
		} );
		audio.addEventListener( 'pause', function () {
			bLire.textContent = '▶';
			bLire.setAttribute( 'aria-label', txt( 'lire', 'Lire' ) );
			retenir();
		} );
		audio.addEventListener( 'timeupdate', function () {
			if ( ! glisse && audio.duration ) {
				barre.value = Math.round( ( audio.currentTime / audio.duration ) * 1000 );
			}
			tTemps.textContent = minutes( audio.currentTime );
			if ( Math.floor( audio.currentTime ) % 5 === 0 ) { retenir(); }
		} );
		audio.addEventListener( 'loadedmetadata', function () {
			tDuree.textContent = minutes( audio.duration );
		} );
		audio.addEventListener( 'error', function () {
			if ( ! audio.src ) { return; }
			erreur.hidden = false;
		} );
		audio.addEventListener( 'ended', function () {
			if ( 1 === repet ) { charger( courant, true ); return; }
			if ( courant < lignes.length - 1 ) { charger( courant + 1, true ); return; }
			if ( 2 === repet ) { charger( 0, true ); }
		} );

		barre.addEventListener( 'input', function () { glisse = true; } );
		barre.addEventListener( 'change', function () {
			glisse = false;
			if ( audio.duration ) {
				audio.currentTime = ( barre.value / 1000 ) * audio.duration;
			}
		} );

		liste.addEventListener( 'click', function ( ev ) {
			var dl = ev.target.closest ? ev.target.closest( '.hc-dl' ) : null;
			if ( dl ) { return; }
			var li = ev.target.closest ? ev.target.closest( '.hc-sourate' ) : null;
			if ( ! li ) { return; }
			ev.preventDefault();
			charger( lignes.indexOf( li ), true );
		} );

		document.addEventListener( 'keydown', function ( ev ) {
			var cible = ev.target;
			if ( cible && /^(INPUT|TEXTAREA|SELECT)$/.test( cible.tagName ) ) { return; }
			if ( ' ' === ev.key || 'Spacebar' === ev.key ) {
				ev.preventDefault();
				bLire.click();
			}
		} );

		window.addEventListener( 'beforeunload', retenir );

		bRep.textContent = libellesRepet[ 0 ];

		/* Reprise : ?sourate=N dans l'adresse, sinon la memoire du navigateur. */
		var demande = new RegExp( '[?&]sourate=(\\d+)' ).exec( window.location.search );
		if ( demande ) {
			for ( var i = 0; i < lignes.length; i++ ) {
				if ( lignes[ i ].getAttribute( 'data-num' ) === demande[ 1 ] ) {
					var m = lire( MEMOIRE );
					var pos = ( m && m.page === page && m.num === demande[ 1 ] ) ? m.position : 0;
					charger( i, false, pos );
					lignes[ i ].scrollIntoView( { block: 'center' } );
					break;
				}
			}
		} else {
			var mem = lire( MEMOIRE );
			if ( mem && mem.page === page ) {
				for ( var j = 0; j < lignes.length; j++ ) {
					if ( lignes[ j ].getAttribute( 'data-num' ) === mem.num ) {
						charger( j, false, mem.position );
						break;
					}
				}
			}
		}
	}

	/* ------------------------------------------------------------------ */
	/* Le bouton "texte plus grand"                                         */
	/* ------------------------------------------------------------------ */

	function poserTaille() {
		var b = document.getElementById( 'hc-taille' );
		if ( ! b ) { return; }
		var grand = lire( 'hc-grand' ) === true;
		function poser() {
			document.body.classList.toggle( 'hc-grand', grand );
			b.setAttribute( 'aria-pressed', grand ? 'true' : 'false' );
		}
		poser();
		b.addEventListener( 'click', function () {
			grand = ! grand;
			ecrire( 'hc-grand', grand );
			poser();
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Le telechargement d'une sourate                                      */
	/* ------------------------------------------------------------------ */
	/*
	 * L'attribut download d'un lien n'a AUCUN effet quand le fichier vient
	 * d'un autre domaine : le navigateur l'ignore en silence et se contente
	 * d'ouvrir le mp3. Verifie dans un vrai navigateur : le clic quittait la
	 * fiche pour la source, et il fallait un clic droit pour obtenir un
	 * fichier - nomme 001.mp3, comme les 113 autres.
	 *
	 * On recupere donc le fichier nous-memes et on le rend au navigateur
	 * comme un objet local, ou download est respecte. La source autorise la
	 * lecture depuis un autre domaine ; si un jour elle ne l'autorise plus,
	 * le lien d'origine s'ouvre a la place et rien n'est perdu.
	 */

	function etiquette( lien, texte, aria ) {
		var signe = lien.querySelector( '.hc-dl-signe' );
		if ( signe ) { signe.textContent = texte; }
		lien.setAttribute( 'aria-label', aria );
		lien.setAttribute( 'title', aria );
	}

	function remettre( lien ) {
		lien.classList.remove( 'en-cours' );
		etiquette( lien, '↓', lien.getAttribute( 'data-dit' ) || txt( 'telecharger', 'Télécharger' ) );
	}

	function enregistrer( blob, nom ) {
		var url = window.URL.createObjectURL( blob );
		var a = document.createElement( 'a' );
		a.href = url;
		a.download = nom;
		document.body.appendChild( a );
		a.click();
		document.body.removeChild( a );
		// Revoquer tout de suite couperait l'enregistrement en cours.
		window.setTimeout( function () { window.URL.revokeObjectURL( url ); }, 60000 );
	}

	function avecProgression( rep, lien ) {
		var total = parseInt( rep.headers.get( 'content-length' ) || '0', 10 );
		if ( ! total || ! rep.body || ! rep.body.getReader ) {
			return rep.blob();
		}
		var lecteur = rep.body.getReader();
		var morceaux = [];
		var recu = 0;
		return ( function pomper() {
			return lecteur.read().then( function ( r ) {
				if ( r.done ) {
					return new Blob( morceaux, { type: 'audio/mpeg' } );
				}
				morceaux.push( r.value );
				recu += r.value.length;
				etiquette( lien, Math.round( ( recu / total ) * 100 ) + '%',
					txt( 'dl_annuler', 'Annuler' ) );
				return pomper();
			} );
		} )();
	}

	function poserTelechargements() {
		var liens = document.querySelectorAll( '.hc-dl' );
		if ( ! liens.length ) { return; }
		// Sans fetch ni Blob, on ne touche a rien : le lien d'origine suffit.
		if ( ! window.fetch || ! window.URL || ! window.URL.createObjectURL ) { return; }

		Array.prototype.forEach.call( liens, function ( lien ) {
			lien.setAttribute( 'data-dit', lien.getAttribute( 'aria-label' ) || '' );

			lien.addEventListener( 'click', function ( ev ) {
				// Ctrl/Cmd/clic du milieu : c'est le navigateur qui decide.
				if ( ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey ) { return; }
				ev.preventDefault();

				// Un deuxieme clic pendant le telechargement l'annule.
				if ( lien.hcCtrl ) {
					lien.hcCtrl.abort();
					return;
				}

				var ctrl = window.AbortController ? new window.AbortController() : null;
				lien.hcCtrl = ctrl;
				lien.classList.remove( 'fait', 'echec' );
				lien.classList.add( 'en-cours' );
				etiquette( lien, '…', txt( 'dl_annuler', 'Annuler' ) );

				window.fetch( lien.href, ctrl ? { signal: ctrl.signal } : undefined )
					.then( function ( rep ) {
						// Un 200 n'est pas un fichier : une page d'erreur repond 200
						// elle aussi, et elle s'enregistrerait sous un nom en .mp3.
						var type = rep.headers.get( 'content-type' ) || '';
						if ( ! rep.ok || type.indexOf( 'audio' ) === -1 ) {
							throw new Error( 'reponse ' + rep.status + ' ' + type );
						}
						return avecProgression( rep, lien );
					} )
					.then( function ( blob ) {
						if ( ! blob || ! blob.size ) { throw new Error( 'fichier vide' ); }
						enregistrer( blob, lien.getAttribute( 'data-fichier' ) ||
							lien.href.split( '/' ).pop() );
						lien.classList.remove( 'en-cours' );
						lien.classList.add( 'fait' );
						etiquette( lien, '✓', txt( 'dl_fait', 'Téléchargée' ) );
					} )
					.catch( function ( e ) {
						lien.classList.remove( 'en-cours' );
						if ( e && 'AbortError' === e.name ) {
							remettre( lien );
							return;
						}
						// Porte de sortie : le fichier s'ouvre, l'utilisateur
						// garde la main. Mieux qu'un bouton qui ne fait rien.
						lien.classList.add( 'echec' );
						etiquette( lien, '↗', txt( 'dl_echec', 'Téléchargement impossible' ) );
						window.open( lien.href, '_blank', 'noopener' );
					} )
					.then( function () { lien.hcCtrl = null; } );
			} );
		} );
	}

	function demarrer() {
		poserTaille();
		poserReprise();
		poserLecteur();
		poserTelechargements();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', demarrer );
	} else {
		demarrer();
	}
} )();
