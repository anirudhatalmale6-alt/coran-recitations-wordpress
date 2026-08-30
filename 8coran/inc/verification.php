<?php
/**
 * Recitateurs > Verifier les adresses.
 *
 * Pour chaque fiche, deux requetes HEAD : la PREMIERE et la DERNIERE sourate
 * de sa propre liste. Pas la sourate 1 - une recitation partielle ne la
 * contient pas forcement, et la tester donnerait un echec qui n'en est pas.
 *
 * Un 200 ne suffit pas : une page d'erreur repond 200. Il faut un type audio
 * et une taille non nulle. Rien n'est telecharge.
 *
 * Le travail se fait par paquets de dix, en tache de fond du navigateur, pour
 * ne pas tenir une requete PHP ouverte pendant des minutes.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'HUITCORAN_PAQUET', 10 );

function huitcoran_menu_verif() {
	add_submenu_page(
		'edit.php?post_type=recitateur',
		'Vérifier les adresses',
		'Vérifier les adresses',
		'manage_options',
		'huitcoran-verif',
		'huitcoran_page_verif'
	);
}
add_action( 'admin_menu', 'huitcoran_menu_verif' );

/** Une adresse : (ok, detail). */
function huitcoran_tester_url( $url ) {
	if ( '' === $url ) {
		return array( false, 'adresse vide' );
	}
	$rep = wp_remote_head( $url, array( 'timeout' => 20, 'redirection' => 5 ) );
	if ( is_wp_error( $rep ) ) {
		return array( false, $rep->get_error_message() );
	}
	$code = (int) wp_remote_retrieve_response_code( $rep );
	if ( 200 !== $code ) {
		return array( false, 'HTTP ' . $code );
	}
	$type = (string) wp_remote_retrieve_header( $rep, 'content-type' );
	if ( false === strpos( $type, 'audio' ) ) {
		return array( false, '200 mais type ' . ( $type ? $type : 'inconnu' ) );
	}
	$taille = (int) wp_remote_retrieve_header( $rep, 'content-length' );
	if ( $taille <= 0 ) {
		return array( false, '200 mais taille nulle' );
	}
	return array( true, size_format( $taille ) );
}

function huitcoran_ajax_verif() {
	check_ajax_referer( 'huitcoran_verif', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Droits insuffisants.' ) );
	}
	$debut = isset( $_POST['debut'] ) ? max( 0, (int) $_POST['debut'] ) : 0;

	$ids = get_posts(
		array(
			'post_type'      => 'recitateur',
			'post_status'    => 'any',
			'posts_per_page' => HUITCORAN_PAQUET,
			'offset'         => $debut,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'fields'         => 'ids',
		)
	);

	$lignes = array();
	foreach ( $ids as $id ) {
		$serveur = get_post_meta( $id, '_hc_serveur', true );
		$nums    = huitcoran_liste_sourates( $id );
		$premier = $nums ? reset( $nums ) : 0;
		$dernier = $nums ? end( $nums ) : 0;

		if ( '' === trim( (string) $serveur ) ) {
			$lignes[] = array(
				'titre'  => get_the_title( $id ),
				'lien'   => get_edit_post_link( $id, 'raw' ),
				'ok'     => false,
				'detail' => 'aucune adresse de dossier',
			);
			continue;
		}
		list( $ok1, $d1 ) = huitcoran_tester_url( huitcoran_url_sourate( $serveur, $premier ) );
		list( $ok2, $d2 ) = huitcoran_tester_url( huitcoran_url_sourate( $serveur, $dernier ) );
		$lignes[] = array(
			'titre'  => get_the_title( $id ),
			'lien'   => get_edit_post_link( $id, 'raw' ),
			'ok'     => ( $ok1 && $ok2 ),
			'detail' => sprintf(
				'sourate %d : %s — sourate %d : %s',
				$premier,
				$d1,
				$dernier,
				$d2
			),
		);
	}

	wp_send_json_success(
		array(
			'lignes' => $lignes,
			'faits'  => count( $ids ),
		)
	);
}
add_action( 'wp_ajax_huitcoran_verif', 'huitcoran_ajax_verif' );

function huitcoran_page_verif() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Droits insuffisants.' );
	}
	$total = (int) huitcoran_nombre_recitateurs();
	?>
	<div class="wrap">
		<h1>Vérifier les adresses</h1>
		<p>
			Pour chaque fiche, la <strong>première</strong> et la <strong>dernière</strong> sourate de sa liste sont
			demandées au serveur de la source. Seul l’en-tête est lu : aucun fichier n’est téléchargé.
			Une réponse n’est comptée bonne que si elle est un <em>200</em>, d’un <em>type audio</em>, et d’une <em>taille non nulle</em> —
			une page d’erreur répond 200 elle aussi.
		</p>
		<p>
			<button class="button button-primary" id="hc-v-lancer">Lancer la vérification</button>
			<span id="hc-v-etat" style="margin-inline-start:12px;font-weight:600;"></span>
		</p>
		<table class="widefat striped" id="hc-v-table">
			<thead>
				<tr>
					<th style="width:80px;">État</th>
					<th>Récitateur</th>
					<th>Détail</th>
				</tr>
			</thead>
			<tbody></tbody>
		</table>
	</div>
	<script>
	( function () {
		var total  = <?php echo (int) $total; ?>;
		var nonce  = '<?php echo esc_js( wp_create_nonce( 'huitcoran_verif' ) ); ?>';
		var bouton = document.getElementById( 'hc-v-lancer' );
		var etat   = document.getElementById( 'hc-v-etat' );
		var corps  = document.querySelector( '#hc-v-table tbody' );
		var faits = 0, bons = 0, mauvais = 0;

		function ligne( r ) {
			var tr = document.createElement( 'tr' );
			var td = document.createElement( 'td' );
			td.textContent = r.ok ? 'OK' : 'ÉCHEC';
			td.style.fontWeight = '700';
			td.style.color = r.ok ? '#116611' : '#a00';
			tr.appendChild( td );
			var t2 = document.createElement( 'td' );
			if ( r.lien ) {
				var a = document.createElement( 'a' );
				a.href = r.lien;
				a.textContent = r.titre;
				t2.appendChild( a );
			} else {
				t2.textContent = r.titre;
			}
			tr.appendChild( t2 );
			var t3 = document.createElement( 'td' );
			t3.textContent = r.detail;
			tr.appendChild( t3 );
			if ( ! r.ok ) { corps.insertBefore( tr, corps.firstChild ); } else { corps.appendChild( tr ); }
		}

		function paquet( debut ) {
			var corpsReq = new FormData();
			corpsReq.append( 'action', 'huitcoran_verif' );
			corpsReq.append( 'nonce', nonce );
			corpsReq.append( 'debut', debut );
			fetch( ajaxurl, { method: 'POST', body: corpsReq, credentials: 'same-origin' } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( r ) {
					if ( ! r.success ) { etat.textContent = r.data.message; bouton.disabled = false; return; }
					r.data.lignes.forEach( function ( l ) {
						ligne( l );
						faits++;
						if ( l.ok ) { bons++; } else { mauvais++; }
					} );
					etat.textContent = faits + ' / ' + total + ' — ' + bons + ' répondent, ' + mauvais + ' en échec';
					if ( r.data.faits > 0 && faits < total ) {
						paquet( debut + r.data.faits );
					} else {
						etat.textContent = 'Terminé : ' + faits + ' fiches, ' + bons + ' répondent, ' + mauvais + ' en échec.';
						bouton.disabled = false;
					}
				} )
				.catch( function () {
					etat.textContent = 'La vérification s’est interrompue.';
					bouton.disabled = false;
				} );
		}

		bouton.addEventListener( 'click', function () {
			bouton.disabled = true;
			corps.innerHTML = '';
			faits = 0; bons = 0; mauvais = 0;
			etat.textContent = 'En cours…';
			paquet( 0 );
		} );
	} )();
	</script>
	<?php
}
