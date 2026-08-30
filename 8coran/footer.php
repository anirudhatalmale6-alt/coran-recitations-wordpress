<?php
/**
 * Le pied de page.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
</main>

<footer class="hc-pied">
	<div class="hc-bande">
		<p class="hc-pied-note"><?php hc_e( 'source_note' ); ?></p>
		<p class="hc-pied-nom">
			<?php echo esc_html( get_bloginfo( 'name' ) ); ?>
		</p>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
