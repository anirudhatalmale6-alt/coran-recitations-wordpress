<?php
/**
 * La page d'un recitateur : le lecteur et les sourates.
 *
 * Sans JavaScript, chaque sourate reste un lien direct vers son fichier :
 * le navigateur l'ouvre avec son propre lecteur. Le lecteur du site n'ajoute
 * que le confort - enchainement, reprise, repetition.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();

while ( have_posts() ) :
	the_post();
	$id       = get_the_ID();
	$serveur  = get_post_meta( $id, '_hc_serveur', true );
	$moshaf   = get_post_meta( $id, '_hc_moshaf', true );
	$source   = get_post_meta( $id, '_hc_source', true );
	$src_url  = get_post_meta( $id, '_hc_source_url', true );
	$numeros  = huitcoran_liste_sourates( $id );
	$sourates = huitcoran_sourates();
	$termes   = get_the_terms( $id, 'riwaya' );
	$montrer_dl = '1' === (string) get_option( 'huitcoran_telechargement', '1' );
	?>

	<div class="hc-bande">

		<p class="hc-fil"><a href="<?php echo esc_url( get_post_type_archive_link( 'recitateur' ) ); ?>">← <?php hc_e( 'retour' ); ?></a></p>

		<header class="hc-tete hc-tete-recitateur">
			<?php list( $principal, $secondaire, $genre ) = huitcoran_noms_affiches( $id ); ?>
			<h1<?php echo 'ar' === $genre ? ' lang="ar" dir="rtl"' : ''; ?>><?php echo esc_html( $principal ); ?></h1>
			<?php if ( $secondaire && 'ar' === $genre ) : ?>
				<p class="hc-nom-latin"><?php echo esc_html( $secondaire ); ?></p>
			<?php elseif ( $secondaire ) : ?>
				<p class="hc-nom-ar" lang="ar" dir="rtl"><?php echo esc_html( $secondaire ); ?></p>
			<?php endif; ?>
			<p class="hc-sous-titre">
				<?php if ( $moshaf ) : ?>
					<span><?php echo esc_html( $moshaf ); ?></span>
				<?php endif; ?>
				<?php if ( $termes && ! is_wp_error( $termes ) ) : ?>
					<a class="hc-pastille" href="<?php echo esc_url( get_term_link( $termes[0] ) ); ?>"><?php echo esc_html( $termes[0]->name ); ?></a>
				<?php endif; ?>
				<?php if ( 114 === count( $numeros ) ) : ?>
					<span class="hc-pastille complet"><?php hc_e( 'coran_complet' ); ?></span>
				<?php else : ?>
					<span class="hc-pastille"><?php printf( esc_html( hc_t( 'nb_sourates' ) ), (int) count( $numeros ) ); ?></span>
				<?php endif; ?>
			</p>
			<?php if ( get_the_content() ) : ?>
				<div class="hc-intro"><?php the_content(); ?></div>
			<?php endif; ?>
		</header>

		<?php if ( '' === trim( (string) $serveur ) ) : ?>
			<p class="hc-vide">
				<?php
				echo esc_html(
					current_user_can( 'edit_post', $id )
						? 'Cette fiche n’a pas encore d’adresse de dossier audio. Ajoutez-la dans « L’enregistrement ».'
						: hc_t( 'aucun' )
				);
				?>
			</p>
		<?php else : ?>

		<div class="hc-lecteur" id="hc-lecteur"
			data-recitateur="<?php echo esc_attr( get_the_title() ); ?>"
			data-page="<?php echo esc_url( get_permalink() ); ?>">
			<div class="hc-lecteur-haut">
				<p class="hc-en-cours">
					<span class="hc-en-cours-num" id="hc-num">—</span>
					<span class="hc-en-cours-ar" id="hc-ar" lang="ar" dir="rtl"></span>
					<span class="hc-en-cours-tr" id="hc-tr"></span>
				</p>
			</div>
			<div class="hc-commandes">
				<button type="button" class="hc-cmd" id="hc-prec" title="<?php echo esc_attr( hc_t( 'precedent' ) ); ?>" aria-label="<?php echo esc_attr( hc_t( 'precedent' ) ); ?>">⏮</button>
				<button type="button" class="hc-cmd hc-cmd-lire" id="hc-lire" aria-label="<?php echo esc_attr( hc_t( 'lire' ) ); ?>">▶</button>
				<button type="button" class="hc-cmd" id="hc-suiv" title="<?php echo esc_attr( hc_t( 'suivant' ) ); ?>" aria-label="<?php echo esc_attr( hc_t( 'suivant' ) ); ?>">⏭</button>
				<span class="hc-temps" id="hc-temps">0:00</span>
				<input class="hc-barre" id="hc-barre" type="range" min="0" max="1000" value="0"
					aria-label="<?php echo esc_attr( hc_t( 'sourate' ) ); ?>">
				<span class="hc-temps" id="hc-duree">0:00</span>
				<button type="button" class="hc-cmd hc-cmd-rep" id="hc-repeter" aria-pressed="false"><?php hc_e( 'repeter' ); ?></button>
			</div>
			<p class="hc-erreur" id="hc-erreur" hidden><?php hc_e( 'erreur_audio' ); ?></p>
			<audio id="hc-audio" preload="none"></audio>
		</div>

		<ol class="hc-sourates" id="hc-liste">
			<?php
			foreach ( $numeros as $n ) :
				if ( ! isset( $sourates[ $n ] ) ) {
					continue;
				}
				$s   = $sourates[ $n ];
				$url = huitcoran_url_sourate( $serveur, $n );
				?>
				<li class="hc-sourate" data-num="<?php echo (int) $n; ?>"
					data-url="<?php echo esc_url( $url ); ?>"
					data-ar="<?php echo esc_attr( $s['ar'] ); ?>"
					data-tr="<?php echo esc_attr( $s['tr'] ); ?>">
					<a class="hc-sourate-lien" href="<?php echo esc_url( $url ); ?>">
						<span class="hc-num"><?php echo (int) $n; ?></span>
						<span class="hc-noms">
							<span class="hc-ar" lang="ar" dir="rtl"><?php echo esc_html( $s['ar'] ); ?></span>
							<span class="hc-tr"><?php echo esc_html( $s['tr'] ); ?></span>
							<?php // Le sens du titre est une traduction francaise : elle n'a rien
								// a faire sur une page arabe ni sur une page anglaise. ?>
							<?php if ( 'fr' === huitcoran_langue()['code'] ) : ?>
								<span class="hc-fr"><?php echo esc_html( $s['fr'] ); ?></span>
							<?php endif; ?>
						</span>
						<span class="hc-meta">
							<span class="hc-lieu"><?php echo esc_html( $s['mek'] ? hc_t( 'mecquoise' ) : hc_t( 'medinoise' ) ); ?></span>
							<span class="hc-pages">
								<?php
								if ( $s['p1'] === $s['p2'] ) {
									printf( esc_html( hc_t( 'page' ) ), (int) $s['p1'] );
								} else {
									printf( esc_html( hc_t( 'pages' ) ), (int) $s['p1'], (int) $s['p2'] );
								}
								?>
							</span>
						</span>
					</a>
					<?php if ( $montrer_dl ) : ?>
						<a class="hc-dl" href="<?php echo esc_url( $url ); ?>" download
							title="<?php echo esc_attr( hc_t( 'telecharger' ) ); ?>"
							aria-label="<?php echo esc_attr( hc_t( 'telecharger' ) . ' ' . $s['tr'] ); ?>">↓</a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>

		<?php if ( $source ) : ?>
			<p class="hc-source">
				<strong><?php hc_e( 'source' ); ?> :</strong>
				<?php if ( $src_url ) : ?>
					<a href="<?php echo esc_url( $src_url ); ?>" rel="noopener nofollow" target="_blank"><?php echo esc_html( $source ); ?></a>
				<?php else : ?>
					<?php echo esc_html( $source ); ?>
				<?php endif; ?>
				— <?php hc_e( 'source_note' ); ?>
			</p>
		<?php endif; ?>

		<?php endif; ?>
	</div>

<?php
endwhile;

get_footer();
