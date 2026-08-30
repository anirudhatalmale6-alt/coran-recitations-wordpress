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

	function demarrer() {
		poserTaille();
		poserReprise();
		poserLecteur();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', demarrer );
	} else {
		demarrer();
	}
} )();
