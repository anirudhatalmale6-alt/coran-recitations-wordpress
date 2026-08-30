<?php
/**
 * Page introuvable.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();
?>
<div class="hc-bande">
	<section class="hc-tete">
		<h1>404</h1>
		<p class="hc-intro"><?php hc_e( 'aucun_resultat' ); ?></p>
		<p><a class="hc-bouton" href="<?php echo esc_url( get_post_type_archive_link( 'recitateur' ) ); ?>"><?php hc_e( 'retour' ); ?></a></p>
	</section>
</div>
<?php
get_footer();
