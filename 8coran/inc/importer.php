<?php
/**
 * L'import depuis l'API publique de mp3quran.net (v3).
 *
 * Un enregistrement importe est identifie par sa cle _hc_cle_api
 * ("mp3quran-<recitateur>-<moshaf>"). Reimporter met a jour la fiche
 * existante : jamais de doublon, et les textes ecrits a la main dans
 * l'editeur ne sont pas ecrases.
 *
 * Aucun fichier audio n'est copie. Seule la liste des noms et des adresses
 * de dossiers est recuperee ; les fichiers restent chez la source.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'HUITCORAN_API', 'https://www.mp3quran.net/api/v3/reciters' );

function huitcoran_menu_import() {
	add_submenu_page(
		'edit.php?post_type=recitateur',
		'Importer des récitateurs',
		'Importer',
		'manage_options',
		'huitcoran-import',
		'huitcoran_page_import'
	);
}
add_action( 'admin_menu', 'huitcoran_menu_import' );

/**
 * La liste des recitateurs de la source, mise en cache 12 heures.
 * $forcer = true pour ignorer le cache.
 */
function huitcoran_catalogue( $langue = 'fr', $forcer = false ) {
	$langue = in_array( $langue, array( 'ar', 'fr', 'eng' ), true ) ? $langue : 'fr';
	$cle    = 'huitcoran_catalogue_' . $langue;
	if ( ! $forcer ) {
		$cache = get_transient( $cle );
		if ( is_array( $cache ) ) {
			return $cache;
		}
	}
	$rep = wp_remote_get(
		add_query_arg( 'language', $langue, HUITCORAN_API ),
		array( 'timeout' => 30, 'redirection' => 5 )
	);
	if ( is_wp_error( $rep ) ) {
		return new WP_Error( 'huitcoran_reseau', 'La source est injoignable : ' . $rep->get_error_message() );
	}
	$code = (int) wp_remote_retrieve_response_code( $rep );
	if ( 200 !== $code ) {
		return new WP_Error( 'huitcoran_http', sprintf( 'La source répond %d.', $code ) );
	}
	$data = json_decode( wp_remote_retrieve_body( $rep ), true );
	if ( ! is_array( $data ) || ! isset( $data['reciters'] ) || ! is_array( $data['reciters'] ) ) {
		return new WP_Error( 'huitcoran_format', 'La réponse de la source n’a pas le format attendu.' );
	}
	set_transient( $cle, $data['reciters'], 12 * HOUR_IN_SECONDS );
	return $data['reciters'];
}

/**
 * Les autres graphies d'un nom, par identifiant de recitateur.
 *
 * "Afasy" ne trouvait rien parce que la liste francaise ecrit "Al Afasi",
 * et "Soudais" rien non plus. Les noms arabe et anglais de la meme source
 * sont donc gardes a cote de la fiche et rendus cherchables.
 */
function huitcoran_autres_noms() {
	$noms = array();
	foreach ( array( 'ar', 'eng', 'fr' ) as $langue ) {
		$cat = huitcoran_catalogue( $langue );
		if ( is_wp_error( $cat ) ) {
			continue;
		}
		foreach ( $cat as $r ) {
			$id = (int) $r['id'];
			if ( ! isset( $noms[ $id ] ) ) {
				$noms[ $id ] = array();
			}
			$noms[ $id ][ $langue ] = (string) $r['name'];
		}
	}
	return $noms;
}

/** Met la liste a plat : une ligne = un enregistrement (moshaf). */
function huitcoran_aplatir( $recitateurs, $autres = null ) {
	if ( null === $autres ) {
		$autres = huitcoran_autres_noms();
	}
	$lignes = array();
	foreach ( $recitateurs as $r ) {
		if ( empty( $r['moshaf'] ) || ! is_array( $r['moshaf'] ) ) {
			continue;
		}
		$id_rec = (int) $r['id'];
		$alias  = isset( $autres[ $id_rec ] ) ? $autres[ $id_rec ] : array();
		foreach ( $r['moshaf'] as $m ) {
			$lignes[] = array(
				'cle'      => 'mp3quran-' . $id_rec . '-' . (int) $m['id'],
				'nom'      => (string) $r['name'],
				'nom_ar'   => isset( $alias['ar'] ) ? $alias['ar'] : '',
				'alias'    => implode( ' | ', array_unique( array_filter( array_values( $alias ) ) ) ),
				'lettre'   => isset( $r['letter'] ) ? (string) $r['letter'] : '',
				'moshaf'   => (string) $m['name'],
				'riwaya'   => (int) $m['rewaya_id'],
				'serveur'  => (string) $m['server'],
				'sourates' => (string) $m['surah_list'],
				'total'    => (int) $m['surah_total'],
			);
		}
	}
	return $lignes;
}

/**
 * Cree ou met a jour une fiche. Retourne 'cree', 'maj' ou WP_Error.
 */
function huitcoran_importer_ligne( $ligne ) {
	$existants = get_posts(
		array(
			'post_type'      => 'recitateur',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_key'       => '_hc_cle_api',
			'meta_value'     => $ligne['cle'],
			'fields'         => 'ids',
		)
	);
	$titre = trim( $ligne['nom'] );
	if ( '' === $titre ) {
		return new WP_Error( 'huitcoran_titre', 'Nom vide.' );
	}

	if ( $existants ) {
		$post_id = (int) $existants[0];
		wp_update_post( array( 'ID' => $post_id, 'post_title' => $titre ) );
		$etat = 'maj';
	} else {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'recitateur',
				'post_status' => 'publish',
				'post_title'  => $titre,
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		$etat = 'cree';
	}

	update_post_meta( $post_id, '_hc_cle_api', $ligne['cle'] );
	update_post_meta( $post_id, '_hc_serveur', esc_url_raw( $ligne['serveur'] ) );
	update_post_meta( $post_id, '_hc_sourates', $ligne['sourates'] );
	update_post_meta( $post_id, '_hc_moshaf', $ligne['moshaf'] );
	update_post_meta( $post_id, '_hc_source', 'mp3quran.net' );
	update_post_meta( $post_id, '_hc_source_url', 'https://mp3quran.net/' );
	if ( isset( $ligne['nom_ar'] ) && '' !== $ligne['nom_ar'] ) {
		update_post_meta( $post_id, '_hc_nom_ar', $ligne['nom_ar'] );
	}
	if ( isset( $ligne['alias'] ) && '' !== $ligne['alias'] ) {
		update_post_meta( $post_id, '_hc_alias', $ligne['alias'] );
	}
	if ( '' !== $ligne['lettre'] ) {
		update_post_meta( $post_id, '_hc_lettre', mb_strtoupper( $ligne['lettre'], 'UTF-8' ) );
	}

	$riwayat = huitcoran_riwayat();
	$nom_riwaya = isset( $riwayat[ $ligne['riwaya'] ] ) ? $riwayat[ $ligne['riwaya'] ] : '';
	if ( '' === $nom_riwaya ) {
		// La source a ajoute une riwaya que la table ne connait pas : on garde
		// le nom de l'enregistrement plutot que d'inventer un libelle.
		$nom_riwaya = $ligne['moshaf'];
	}
	if ( '' !== $nom_riwaya ) {
		wp_set_object_terms( $post_id, array( $nom_riwaya ), 'riwaya', false );
	}
	// Apres les metas : la cle se calcule a partir des alias qu'on vient
	// d'ecrire, pas de ceux qui existaient avant.
	huitcoran_maj_cle_recherche( $post_id );
	return $etat;
}

function huitcoran_page_import() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Droits insuffisants.' );
	}
	$langue  = isset( $_REQUEST['hc_langue_noms'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['hc_langue_noms'] ) ) : 'fr';
	$complet = isset( $_REQUEST['hc_complet'] ) ? '1' : ( isset( $_REQUEST['hc_envoye'] ) ? '' : '1' );
	$rapport = null;

	if ( isset( $_POST['hc_importer'] ) && check_admin_referer( 'huitcoran_import' ) ) {
		$cles = isset( $_POST['hc_cles'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['hc_cles'] ) ) : array();
		$catalogue = huitcoran_catalogue( $langue );
		if ( is_wp_error( $catalogue ) ) {
			$rapport = array( 'erreur' => $catalogue->get_error_message() );
		} else {
			$lignes = huitcoran_aplatir( $catalogue );
			$index  = array();
			foreach ( $lignes as $l ) {
				$index[ $l['cle'] ] = $l;
			}
			$cree = 0;
			$maj  = 0;
			$rate = array();
			foreach ( $cles as $cle ) {
				if ( ! isset( $index[ $cle ] ) ) {
					$rate[] = $cle . ' : absent de la liste';
					continue;
				}
				$res = huitcoran_importer_ligne( $index[ $cle ] );
				if ( is_wp_error( $res ) ) {
					$rate[] = $cle . ' : ' . $res->get_error_message();
				} elseif ( 'cree' === $res ) {
					$cree++;
				} else {
					$maj++;
				}
			}
			$rapport = array( 'cree' => $cree, 'maj' => $maj, 'rate' => $rate, 'demande' => count( $cles ) );
		}
	}

	$forcer    = isset( $_POST['hc_recharger'] );
	$catalogue = huitcoran_catalogue( $langue, $forcer );
	?>
	<div class="wrap">
		<h1>Importer des récitateurs</h1>
		<p>
			La liste vient de l’API publique de <strong>mp3quran.net</strong>. L’import copie
			les <em>noms</em> et les <em>adresses de dossiers</em> ; il ne copie aucun fichier audio.
			Réimporter une fiche déjà présente la met à jour au lieu d’en créer une deuxième.
		</p>

		<?php if ( is_wp_error( $catalogue ) ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $catalogue->get_error_message() ); ?></p></div>
			<form method="post"><?php wp_nonce_field( 'huitcoran_import' ); ?>
				<button class="button" name="hc_recharger" value="1">Réessayer</button>
			</form>
			</div>
			<?php
			return;
		endif;

		$lignes = huitcoran_aplatir( $catalogue );
		$total_complets = 0;
		foreach ( $lignes as $l ) {
			if ( 114 === $l['total'] ) {
				$total_complets++;
			}
		}
		?>

		<?php if ( $rapport ) : ?>
			<?php if ( isset( $rapport['erreur'] ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $rapport['erreur'] ); ?></p></div>
			<?php else : ?>
				<div class="notice notice-success">
					<p>
						<?php
						printf(
							'%d demandés : %d créés, %d mis à jour%s.',
							(int) $rapport['demande'],
							(int) $rapport['cree'],
							(int) $rapport['maj'],
							$rapport['rate'] ? ', ' . count( $rapport['rate'] ) . ' en échec' : ''
						);
						?>
					</p>
					<?php if ( $rapport['rate'] ) : ?>
						<ul style="margin-inline-start:20px;list-style:disc;">
							<?php foreach ( $rapport['rate'] as $r ) : ?>
								<li><?php echo esc_html( $r ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		<?php endif; ?>

		<p>
			<strong><?php echo count( $lignes ); ?></strong> enregistrements proposés par la source,
			dont <strong><?php echo (int) $total_complets; ?></strong> Corans complets (114 sourates).
			Déjà sur ce site : <strong><?php echo (int) huitcoran_nombre_recitateurs(); ?></strong>.
		</p>

		<form method="get" style="margin-bottom:14px;">
			<input type="hidden" name="post_type" value="recitateur">
			<input type="hidden" name="page" value="huitcoran-import">
			<input type="hidden" name="hc_envoye" value="1">
			<label>Langue des noms :
				<select name="hc_langue_noms">
					<option value="fr" <?php selected( $langue, 'fr' ); ?>>Français</option>
					<option value="ar" <?php selected( $langue, 'ar' ); ?>>العربية</option>
					<option value="eng" <?php selected( $langue, 'eng' ); ?>>English</option>
				</select>
			</label>
			<label style="margin-inline-start:16px;">
				<input type="checkbox" name="hc_complet" value="1" <?php checked( $complet, '1' ); ?>>
				N’afficher que les Corans complets
			</label>
			<button class="button"><?php echo esc_html( 'Afficher' ); ?></button>
		</form>

		<form method="post">
			<?php wp_nonce_field( 'huitcoran_import' ); ?>
			<input type="hidden" name="hc_langue_noms" value="<?php echo esc_attr( $langue ); ?>">
			<p>
				<button class="button button-primary" name="hc_importer" value="1">Importer la sélection</button>
				<button type="button" class="button" id="hc-tout">Tout cocher</button>
				<button type="button" class="button" id="hc-rien">Tout décocher</button>
				<button class="button" name="hc_recharger" value="1">Recharger depuis la source</button>
			</p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th style="width:30px;"></th>
						<th>Récitateur</th>
						<th>Enregistrement</th>
						<th style="width:90px;">Sourates</th>
						<th>Dossier audio</th>
						<th style="width:90px;">Sur ce site</th>
					</tr>
				</thead>
				<tbody>
				<?php
				foreach ( $lignes as $l ) :
					if ( '1' === $complet && 114 !== $l['total'] ) {
						continue;
					}
					$deja = get_posts(
						array(
							'post_type'      => 'recitateur',
							'post_status'    => 'any',
							'posts_per_page' => 1,
							'meta_key'       => '_hc_cle_api',
							'meta_value'     => $l['cle'],
							'fields'         => 'ids',
						)
					);
					?>
					<tr>
						<td><input type="checkbox" class="hc-case" name="hc_cles[]" value="<?php echo esc_attr( $l['cle'] ); ?>"></td>
						<td><strong><?php echo esc_html( $l['nom'] ); ?></strong></td>
						<td><?php echo esc_html( $l['moshaf'] ); ?></td>
						<td><?php echo (int) $l['total']; ?></td>
						<td><code style="font-size:11px;"><?php echo esc_html( $l['serveur'] ); ?></code></td>
						<td>
							<?php if ( $deja ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( (int) $deja[0] ) ); ?>">présent</a>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p><button class="button button-primary" name="hc_importer" value="1">Importer la sélection</button></p>
		</form>
		<script>
		( function () {
			function toutes( v ) {
				var cases = document.querySelectorAll( '.hc-case' );
				for ( var i = 0; i < cases.length; i++ ) { cases[ i ].checked = v; }
			}
			document.getElementById( 'hc-tout' ).addEventListener( 'click', function () { toutes( true ); } );
			document.getElementById( 'hc-rien' ).addEventListener( 'click', function () { toutes( false ); } );
		} )();
		</script>
	</div>
	<?php
}
