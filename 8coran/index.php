<?php
/**
 * L'annuaire des recitateurs : accueil, archive, riwaya, recherche.
 *
 * Le meme fichier sert les quatre cas, parce que ce sont les memes cartes
 * et les memes filtres ; seul le titre change.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();

$recherche = get_search_query();
$riwaya_en_cours = is_tax( 'riwaya' ) ? get_queried_object() : null;
$lettre_active = isset( $_GET['lettre'] ) ? sanitize_text_field( wp_unslash( $_GET['lettre'] ) ) : '';
$riwaya_active = isset( $_GET['riwaya'] ) ? sanitize_text_field( wp_unslash( $_GET['riwaya'] ) ) : '';
$total = (int) $GLOBALS['wp_query']->found_posts;

$titre = get_option( 'huitcoran_titre_accueil', '' );
if ( $riwaya_en_cours ) {
	$titre = $riwaya_en_cours->name;
} elseif ( $recherche ) {
	$titre = hc_t( 'chercher' ) . ' : ' . $recherche;
} elseif ( '' === trim( (string) $titre ) ) {
	$titre = get_bloginfo( 'name' );
}
$intro = ( ! $riwaya_en_cours && ! $recherche ) ? get_option( 'huitcoran_intro', '' ) : '';
?>

<div class="hc-bande">

	<section class="hc-tete">
		<h1><?php echo esc_html( $titre ); ?></h1>
		<?php if ( $intro ) : ?>
			<div class="hc-intro"><?php echo wp_kses_post( wpautop( $intro ) ); ?></div>
		<?php endif; ?>
		<p class="hc-compte">
			<?php
			printf(
				esc_html( 1 === $total ? hc_t( 'un_recitateur' ) : hc_t( 'des_recitateurs' ) ),
				'<strong>' . esc_html( number_format_i18n( $total ) ) . '</strong>'
			);
			?>
		</p>
	</section>

	<section class="hc-reprise" id="hc-reprise" hidden>
		<h2><?php hc_e( 'reprendre_quoi' ); ?></h2>
		<a class="hc-bouton" id="hc-reprise-lien" href="#"><?php hc_e( 'reprendre' ); ?></a>
	</section>

	<form class="hc-filtres" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
		<label class="hc-lu" for="hc-s"><?php hc_e( 'rechercher' ); ?></label>
		<input type="search" id="hc-s" name="s" value="<?php echo esc_attr( $recherche ); ?>"
			placeholder="<?php echo esc_attr( hc_t( 'rechercher' ) ); ?>">
		<input type="hidden" name="post_type" value="recitateur">

		<?php
		$riwayat = get_terms( array( 'taxonomy' => 'riwaya', 'hide_empty' => true ) );
		if ( ! is_wp_error( $riwayat ) && $riwayat ) :
			?>
			<label class="hc-lu" for="hc-riwaya"><?php hc_e( 'riwaya' ); ?></label>
			<select id="hc-riwaya" name="riwaya">
				<option value=""><?php hc_e( 'toutes_riwayat' ); ?></option>
				<?php foreach ( $riwayat as $t ) : ?>
					<option value="<?php echo esc_attr( $t->slug ); ?>" <?php selected( $riwaya_active, $t->slug ); ?>>
						<?php echo esc_html( $t->name ); ?> (<?php echo (int) $t->count; ?>)
					</option>
				<?php endforeach; ?>
			</select>
		<?php endif; ?>

		<button type="submit" class="hc-bouton"><?php hc_e( 'chercher' ); ?></button>
	</form>

	<?php
	$lettres = huitcoran_lettres_presentes();
	if ( $lettres ) :
		$base = get_post_type_archive_link( 'recitateur' );
		?>
		<nav class="hc-alphabet" aria-label="<?php echo esc_attr( hc_t( 'lettre' ) ); ?>">
			<a class="<?php echo '' === $lettre_active ? 'actif' : ''; ?>"
				href="<?php echo esc_url( $base ); ?>"><?php hc_e( 'toutes_lettres' ); ?></a>
			<?php foreach ( $lettres as $lettre ) : ?>
				<a class="<?php echo $lettre_active === $lettre ? 'actif' : ''; ?>"
					href="<?php echo esc_url( add_query_arg( 'lettre', $lettre, $base ) ); ?>"><?php echo esc_html( $lettre ); ?></a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>

	<?php if ( have_posts() ) : ?>
		<ul class="hc-grille">
			<?php
			while ( have_posts() ) :
				the_post();
				$id       = get_the_ID();
				$sourates = huitcoran_liste_sourates( $id );
				$moshaf   = get_post_meta( $id, '_hc_moshaf', true );
				$termes   = get_the_terms( $id, 'riwaya' );
				?>
				<li class="hc-carte">
					<?php list( $principal, $secondaire, $genre ) = huitcoran_noms_affiches( $id ); ?>
					<h2 class="hc-carte-nom">
						<a href="<?php the_permalink(); ?>"
							<?php echo 'ar' === $genre ? ' lang="ar" dir="rtl"' : ''; ?>><?php echo esc_html( $principal ); ?></a>
					</h2>
					<?php if ( $secondaire && 'ar' === $genre ) : ?>
						<p class="hc-carte-latin"><?php echo esc_html( $secondaire ); ?></p>
					<?php elseif ( $secondaire ) : ?>
						<p class="hc-carte-ar" lang="ar" dir="rtl"><?php echo esc_html( $secondaire ); ?></p>
					<?php endif; ?>
					<?php if ( $moshaf ) : ?>
						<p class="hc-carte-moshaf"><?php echo esc_html( $moshaf ); ?></p>
					<?php elseif ( $termes && ! is_wp_error( $termes ) ) : ?>
						<p class="hc-carte-moshaf"><?php echo esc_html( $termes[0]->name ); ?></p>
					<?php endif; ?>
					<p class="hc-carte-compte">
						<?php if ( 114 === count( $sourates ) ) : ?>
							<span class="hc-pastille complet"><?php hc_e( 'coran_complet' ); ?></span>
						<?php else : ?>
							<span class="hc-pastille"><?php printf( esc_html( hc_t( 'nb_sourates' ) ), (int) count( $sourates ) ); ?></span>
						<?php endif; ?>
					</p>
					<a class="hc-bouton hc-carte-lien" href="<?php the_permalink(); ?>"><?php hc_e( 'ecouter' ); ?></a>
				</li>
			<?php endwhile; ?>
		</ul>

		<nav class="hc-pagination">
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'prev_text' => '‹',
						'next_text' => '›',
					)
				)
			);
			?>
		</nav>

	<?php else : ?>
		<p class="hc-vide">
			<?php
			if ( $recherche || $lettre_active || $riwaya_active ) {
				hc_e( 'aucun_resultat' );
			} elseif ( current_user_can( 'manage_options' ) ) {
				hc_e( 'aucun_admin' );
			} else {
				hc_e( 'aucun' );
			}
			?>
		</p>
	<?php endif; ?>

</div>

<?php
get_footer();
