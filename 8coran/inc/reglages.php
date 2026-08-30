<?php
/**
 * Le panneau Reglages > Coran.
 *
 * Tout ce qui change l'aspect du site se decide ici, sans toucher au code :
 * la langue de l'interface, le titre et le texte d'accueil, le nombre de
 * recitateurs par page, l'affichage du lien de telechargement.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function huitcoran_reglages_liste() {
	return array(
		'huitcoran_langue'         => array( 'defaut' => 'fr', 'type' => 'choix' ),
		'huitcoran_titre_accueil'  => array( 'defaut' => '', 'type' => 'texte' ),
		'huitcoran_intro'          => array( 'defaut' => '', 'type' => 'zone' ),
		'huitcoran_par_page'       => array( 'defaut' => 30, 'type' => 'nombre' ),
		'huitcoran_telechargement' => array( 'defaut' => '1', 'type' => 'case' ),
	);
}

function huitcoran_enregistrer_reglages() {
	foreach ( huitcoran_reglages_liste() as $nom => $def ) {
		register_setting(
			'huitcoran',
			$nom,
			array(
				'type'              => 'string',
				'default'           => $def['defaut'],
				'sanitize_callback' => 'huitcoran_nettoyer_reglage',
			)
		);
	}
}
add_action( 'admin_init', 'huitcoran_enregistrer_reglages' );

function huitcoran_nettoyer_reglage( $valeur ) {
	if ( is_numeric( $valeur ) ) {
		return (string) $valeur;
	}
	return wp_kses_post( $valeur );
}

function huitcoran_menu_reglages() {
	add_submenu_page(
		'edit.php?post_type=recitateur',
		'Réglages du site Coran',
		'Réglages',
		'manage_options',
		'huitcoran-reglages',
		'huitcoran_page_reglages'
	);
}
add_action( 'admin_menu', 'huitcoran_menu_reglages' );

function huitcoran_page_reglages() {
	$langue = get_option( 'huitcoran_langue', 'fr' );
	?>
	<div class="wrap">
		<h1>Réglages du site Coran</h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'huitcoran' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="hc_langue">Langue de l’interface</label></th>
					<td>
						<select name="huitcoran_langue" id="hc_langue">
							<option value="fr" <?php selected( $langue, 'fr' ); ?>>Français</option>
							<option value="ar" <?php selected( $langue, 'ar' ); ?>>العربية (arabe, lecture de droite à gauche)</option>
							<option value="en" <?php selected( $langue, 'en' ); ?>>English</option>
						</select>
						<p class="description">Change les libellés du site public et le sens de lecture de la page. Les noms de sourates restent affichés en arabe dans tous les cas.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hc_titre">Titre affiché en haut de l’accueil</label></th>
					<td>
						<input type="text" class="regular-text" id="hc_titre" name="huitcoran_titre_accueil"
							value="<?php echo esc_attr( get_option( 'huitcoran_titre_accueil', '' ) ); ?>">
						<p class="description">Laissé vide, le titre du site est utilisé.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hc_intro">Texte d’accueil</label></th>
					<td>
						<textarea id="hc_intro" name="huitcoran_intro" rows="4" class="large-text"><?php
							echo esc_textarea( get_option( 'huitcoran_intro', '' ) );
						?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hc_par_page">Récitateurs par page</label></th>
					<td><input type="number" min="1" max="200" id="hc_par_page" name="huitcoran_par_page"
						value="<?php echo esc_attr( get_option( 'huitcoran_par_page', 30 ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row">Lien de téléchargement</th>
					<td>
						<label>
							<input type="checkbox" name="huitcoran_telechargement" value="1"
								<?php checked( get_option( 'huitcoran_telechargement', '1' ), '1' ); ?>>
							Afficher un lien de téléchargement à côté de chaque sourate
						</label>
						<p class="description">Le lien pointe vers le fichier chez la source. Ce site n’héberge aucun fichier audio.</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
