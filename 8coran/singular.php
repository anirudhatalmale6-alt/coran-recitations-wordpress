<?php
/**
 * Une page ou un article ordinaire (mentions, contact, texte libre).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();
?>
<div class="hc-bande">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<article class="hc-article">
			<header class="hc-tete">
				<h1><?php the_title(); ?></h1>
			</header>
			<div class="hc-contenu"><?php the_content(); ?></div>
		</article>
	<?php endwhile; ?>
</div>
<?php
get_footer();
