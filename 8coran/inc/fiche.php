<?php
/**
 * La fiche d'un recitateur dans l'administration.
 *
 * Un bouton "Tester" demande au serveur d'aller chercher l'en-tete du fichier
 * de la sourate 1 : c'est la seule facon de savoir qu'une adresse marche
 * vraiment. Elle repond la taille du fichier ou le code d'erreur, sans rien
 * telecharger.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * L'editeur classique pour les recitateurs.
 *
 * Une fiche est faite de champs, pas de blocs. Avec l'editeur a blocs, le
 * panneau "L'enregistrement" se retrouvait replie tout en bas de l'ecran :
 * le champ le plus important de la fiche etait celui qu'on ne voyait pas.
 */
function huitcoran_editeur_classique( $utiliser, $type ) {
	return 'recitateur' === $type ? false : $utiliser;
}
add_filter( 'use_block_editor_for_post_type', 'huitcoran_editeur_classique', 10, 2 );

function huitcoran_boite() {
	add_meta_box(
		'huitcoran_audio',
		'L’enregistrement',
		'huitcoran_afficher_boite',
		'recitateur',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'huitcoran_boite' );

function huitcoran_afficher_boite( $post ) {
	wp_nonce_field( 'huitcoran_fiche', 'huitcoran_fiche_nonce' );
	$serveur  = get_post_meta( $post->ID, '_hc_serveur', true );
	$sourates = get_post_meta( $post->ID, '_hc_sourates', true );
	$source   = get_post_meta( $post->ID, '_hc_source', true );
	$src_url  = get_post_meta( $post->ID, '_hc_source_url', true );
	$moshaf   = get_post_meta( $post->ID, '_hc_moshaf', true );
	$lettre   = get_post_meta( $post->ID, '_hc_lettre', true );
	$exemple  = huitcoran_url_sourate( $serveur ? $serveur : 'https://server6.mp3quran.net/akdr/', 1 );
	?>
	<style>
		.hc-champ { margin: 0 0 18px; }
		.hc-champ label { display:block; font-weight:600; margin-bottom:4px; }
		.hc-champ .description { margin-top:4px; }
		#hc-resultat { margin-inline-start:10px; font-weight:600; }
	</style>

	<p class="hc-champ">
		<label for="hc_serveur">Adresse du dossier audio</label>
		<input type="url" class="large-text code" id="hc_serveur" name="hc_serveur"
			value="<?php echo esc_attr( $serveur ); ?>" placeholder="https://server6.mp3quran.net/akdr/">
		<span class="description">
			Le dossier qui contient les 114 fichiers. Le fichier d’une sourate est le
			numéro sur trois chiffres suivi de .mp3 — pour cette adresse :
			<code id="hc_exemple"><?php echo esc_html( $exemple ); ?></code>
		</span>
	</p>

	<p class="hc-champ">
		<button type="button" class="button" id="hc-tester">Tester l’adresse</button>
		<span id="hc-resultat"></span>
		<span class="description" style="display:block;margin-top:6px;">
			Le test va lire l’en-tête du fichier de la sourate 1 sur le serveur de la source.
			Il ne télécharge pas le fichier.
		</span>
	</p>

	<p class="hc-champ">
		<label for="hc_sourates">Sourates disponibles</label>
		<input type="text" class="large-text code" id="hc_sourates" name="hc_sourates"
			value="<?php echo esc_attr( $sourates ); ?>" placeholder="1,2,3,…,114">
		<span class="description">Numéros séparés par des virgules. Vide = les 114 sourates.</span>
	</p>

	<p class="hc-champ">
		<label for="hc_moshaf">Nom de l’enregistrement</label>
		<input type="text" class="large-text" id="hc_moshaf" name="hc_moshaf"
			value="<?php echo esc_attr( $moshaf ); ?>" placeholder="Rewayat Hafs A'n Assem">
		<span class="description">Affiché sous le nom du récitateur. Un même récitateur peut avoir plusieurs enregistrements : une fiche par enregistrement.</span>
	</p>

	<div style="display:flex;gap:20px;flex-wrap:wrap;">
		<p class="hc-champ" style="flex:1 1 260px;">
			<label for="hc_source">Nom de la source</label>
			<input type="text" class="widefat" id="hc_source" name="hc_source"
				value="<?php echo esc_attr( $source ); ?>" placeholder="mp3quran.net">
		</p>
		<p class="hc-champ" style="flex:1 1 260px;">
			<label for="hc_source_url">Adresse de la source</label>
			<input type="url" class="widefat code" id="hc_source_url" name="hc_source_url"
				value="<?php echo esc_attr( $src_url ); ?>" placeholder="https://mp3quran.net/">
		</p>
		<p class="hc-champ" style="flex:0 0 120px;">
			<label for="hc_lettre">Lettre</label>
			<input type="text" maxlength="1" class="widefat" id="hc_lettre" name="hc_lettre"
				value="<?php echo esc_attr( $lettre ); ?>">
		</p>
	</div>

	<script>
	( function () {
		var champ = document.getElementById( 'hc_serveur' );
		var ex    = document.getElementById( 'hc_exemple' );
		var btn   = document.getElementById( 'hc-tester' );
		var res   = document.getElementById( 'hc-resultat' );
		function majExemple() {
			var v = champ.value.trim() || 'https://server6.mp3quran.net/akdr/';
			if ( v.slice( -1 ) !== '/' ) { v += '/'; }
			ex.textContent = v + '001.mp3';
		}
		champ.addEventListener( 'input', majExemple );
		btn.addEventListener( 'click', function () {
			res.textContent = 'Test en cours…';
			res.style.color = '';
			var corps = new FormData();
			corps.append( 'action', 'huitcoran_tester' );
			corps.append( 'serveur', champ.value );
			corps.append( 'nonce', '<?php echo esc_js( wp_create_nonce( 'huitcoran_tester' ) ); ?>' );
			fetch( ajaxurl, { method: 'POST', body: corps, credentials: 'same-origin' } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( r ) {
					res.textContent = r.data.message;
					res.style.color = r.success ? '#116611' : '#a00';
				} )
				.catch( function () {
					res.textContent = 'Le test n’a pas pu être lancé.';
					res.style.color = '#a00';
				} );
		} );
	} )();
	</script>
	<?php
}

function huitcoran_sauver_fiche( $post_id ) {
	if ( ! isset( $_POST['huitcoran_fiche_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['huitcoran_fiche_nonce'] ) ), 'huitcoran_fiche' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$carte = array(
		'_hc_serveur'    => array( 'hc_serveur', 'url' ),
		'_hc_sourates'   => array( 'hc_sourates', 'texte' ),
		'_hc_source'     => array( 'hc_source', 'texte' ),
		'_hc_source_url' => array( 'hc_source_url', 'url' ),
		'_hc_moshaf'     => array( 'hc_moshaf', 'texte' ),
		'_hc_lettre'     => array( 'hc_lettre', 'texte' ),
	);
	foreach ( $carte as $meta => $def ) {
		list( $champ, $genre ) = $def;
		if ( ! isset( $_POST[ $champ ] ) ) {
			continue;
		}
		$valeur = sanitize_text_field( wp_unslash( $_POST[ $champ ] ) );
		if ( 'url' === $genre ) {
			$valeur = esc_url_raw( $valeur );
		}
		if ( '_hc_lettre' === $meta ) {
			$valeur = mb_strtoupper( mb_substr( $valeur, 0, 1, 'UTF-8' ), 'UTF-8' );
		}
		update_post_meta( $post_id, $meta, $valeur );
	}
}
add_action( 'save_post_recitateur', 'huitcoran_sauver_fiche', 10, 1 );

/**
 * Le test d'une adresse : une requete HEAD sur la sourate 1.
 */
function huitcoran_ajax_tester() {
	check_ajax_referer( 'huitcoran_tester', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'Droits insuffisants.' ) );
	}
	$serveur = isset( $_POST['serveur'] ) ? esc_url_raw( wp_unslash( $_POST['serveur'] ) ) : '';
	if ( '' === $serveur ) {
		wp_send_json_error( array( 'message' => 'Renseignez d’abord une adresse.' ) );
	}
	$url = huitcoran_url_sourate( $serveur, 1 );
	$rep = wp_remote_head( $url, array( 'timeout' => 15, 'redirection' => 5 ) );
	if ( is_wp_error( $rep ) ) {
		wp_send_json_error( array( 'message' => 'Serveur injoignable : ' . $rep->get_error_message() ) );
	}
	$code = (int) wp_remote_retrieve_response_code( $rep );
	$type = wp_remote_retrieve_header( $rep, 'content-type' );
	$taille = (int) wp_remote_retrieve_header( $rep, 'content-length' );
	if ( 200 !== $code ) {
		wp_send_json_error( array( 'message' => sprintf( 'Le serveur répond %d pour %s', $code, $url ) ) );
	}
	if ( false === strpos( (string) $type, 'audio' ) ) {
		wp_send_json_error( array( 'message' => sprintf( 'Le serveur répond 200 mais ce n’est pas de l’audio (%s).', $type ? $type : 'type inconnu' ) ) );
	}
	wp_send_json_success(
		array(
			'message' => sprintf(
				'Adresse valide : sourate 1 lue, %s, %s Ko.',
				$type,
				number_format_i18n( $taille / 1024 )
			),
		)
	);
}
add_action( 'wp_ajax_huitcoran_tester', 'huitcoran_ajax_tester' );
