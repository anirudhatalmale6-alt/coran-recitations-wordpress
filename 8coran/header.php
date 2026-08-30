<?php
/**
 * L'en-tete commun.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$l = huitcoran_langue();
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="hc-evitement" href="#contenu">Aller au contenu</a>

<header class="hc-entete">
	<div class="hc-bande">
		<a class="hc-marque" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<span class="hc-marque-nom"><?php bloginfo( 'name' ); ?></span>
			<?php if ( get_bloginfo( 'description' ) ) : ?>
				<span class="hc-marque-desc"><?php bloginfo( 'description' ); ?></span>
			<?php endif; ?>
		</a>

		<nav class="hc-nav" aria-label="<?php echo esc_attr( hc_t( 'recitateurs' ) ); ?>">
			<a href="<?php echo esc_url( get_post_type_archive_link( 'recitateur' ) ); ?>"><?php hc_e( 'recitateurs' ); ?></a>
			<?php
			if ( has_nav_menu( 'principal' ) ) {
				wp_nav_menu(
					array(
						'theme_location' => 'principal',
						'container'      => false,
						'items_wrap'     => '%3$s',
						'depth'          => 1,
					)
				);
			}
			?>
			<button type="button" class="hc-taille" id="hc-taille" aria-pressed="false">
				<span aria-hidden="true">A+</span>
				<span class="hc-lu"><?php hc_e( 'plein_ecran' ); ?></span>
			</button>
		</nav>
	</div>
</header>

<main id="contenu" class="hc-page">
