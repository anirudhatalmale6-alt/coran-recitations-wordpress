<?php
/**
 * 8Coran - theme autonome, aucun plugin requis.
 *
 * Un recitateur = un enregistrement complet du Coran (un moshaf) : un nom,
 * une riwaya, un serveur de fichiers et la liste des sourates disponibles.
 * Les fichiers audio restent chez la source ; ce site ne les heberge pas.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'HUITCORAN_VERSION', '1.0.0' );

require_once get_template_directory() . '/inc/sourates.php';
require_once get_template_directory() . '/inc/langue.php';
require_once get_template_directory() . '/inc/reglages.php';
require_once get_template_directory() . '/inc/fiche.php';
require_once get_template_directory() . '/inc/importer.php';
require_once get_template_directory() . '/inc/verification.php';

/* --------------------------------------------------------------------- */
/* Le support du theme                                                    */
/* --------------------------------------------------------------------- */

function huitcoran_support() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'automatic-feed-links' );
	register_nav_menus( array( 'principal' => 'Menu principal' ) );
}
add_action( 'after_setup_theme', 'huitcoran_support' );

/* --------------------------------------------------------------------- */
/* Le type de contenu : un recitateur                                      */
/* --------------------------------------------------------------------- */

function huitcoran_enregistrer_type() {
	register_post_type(
		'recitateur',
		array(
			'labels'       => array(
				'name'               => 'Récitateurs',
				'singular_name'      => 'Récitateur',
				'add_new'            => 'Ajouter',
				'add_new_item'       => 'Ajouter un récitateur',
				'edit_item'          => 'Modifier le récitateur',
				'new_item'           => 'Nouveau récitateur',
				'view_item'          => 'Voir la page du récitateur',
				'search_items'       => 'Rechercher un récitateur',
				'not_found'          => 'Aucun récitateur',
				'not_found_in_trash' => 'Aucun récitateur à la corbeille',
				'menu_name'          => 'Récitateurs',
			),
			'public'       => true,
			'has_archive'  => true,
			'menu_icon'    => 'dashicons-format-audio',
			'menu_position' => 5,
			'supports'     => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
			'rewrite'      => array( 'slug' => 'recitateur' ),
			'show_in_rest' => true,
		)
	);

	register_taxonomy(
		'riwaya',
		'recitateur',
		array(
			'labels'            => array(
				'name'          => 'Riwayat',
				'singular_name' => 'Riwaya',
				'search_items'  => 'Rechercher une riwaya',
				'all_items'     => 'Toutes les riwayat',
				'edit_item'     => 'Modifier la riwaya',
				'add_new_item'  => 'Ajouter une riwaya',
				'menu_name'     => 'Riwayat',
			),
			'public'            => true,
			'hierarchical'      => false,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'riwaya' ),
			'show_in_rest'      => true,
		)
	);
}
add_action( 'init', 'huitcoran_enregistrer_type' );

/* --------------------------------------------------------------------- */
/* Les champs d'un recitateur                                              */
/* --------------------------------------------------------------------- */

function huitcoran_champs() {
	return array(
		'_hc_serveur'  => 'Adresse du dossier audio',
		'_hc_sourates' => 'Sourates disponibles',
		'_hc_source'   => 'Nom de la source',
		'_hc_source_url' => 'Adresse de la source',
		'_hc_moshaf'   => 'Nom de l’enregistrement',
		'_hc_cle_api'  => 'Clé d’import',
		'_hc_lettre'   => 'Lettre de classement',
		'_hc_nom_ar'   => 'Nom en arabe',
		'_hc_alias'    => 'Autres graphies du nom',
	);
}

function huitcoran_enregistrer_champs() {
	foreach ( array_keys( huitcoran_champs() ) as $cle ) {
		register_post_meta(
			'recitateur',
			$cle,
			array(
				'type'          => 'string',
				'single'        => true,
				'show_in_rest'  => false,
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}
}
add_action( 'init', 'huitcoran_enregistrer_champs' );

/**
 * L'adresse du fichier d'une sourate chez la source.
 *
 * Le format des serveurs mp3quran est un numero sur trois chiffres :
 * .../akdr/001.mp3 . La fonction ne devine rien d'autre.
 */
function huitcoran_url_sourate( $serveur, $numero ) {
	$serveur = trim( (string) $serveur );
	if ( '' === $serveur ) {
		return '';
	}
	if ( '/' !== substr( $serveur, -1 ) ) {
		$serveur .= '/';
	}
	return $serveur . sprintf( '%03d', (int) $numero ) . '.mp3';
}

/**
 * Les numeros de sourates d'un recitateur, dans l'ordre, sans doublon.
 * Un champ vide veut dire les 114.
 */
function huitcoran_liste_sourates( $post_id ) {
	$brut = get_post_meta( $post_id, '_hc_sourates', true );
	$brut = trim( (string) $brut );
	if ( '' === $brut ) {
		return range( 1, 114 );
	}
	$nums = array();
	foreach ( preg_split( '/[^0-9]+/', $brut ) as $morceau ) {
		if ( '' === $morceau ) {
			continue;
		}
		$n = (int) $morceau;
		if ( $n >= 1 && $n <= 114 ) {
			$nums[ $n ] = $n;
		}
	}
	ksort( $nums );
	return array_values( $nums );
}

/* --------------------------------------------------------------------- */
/* Les feuilles et les scripts                                             */
/* --------------------------------------------------------------------- */

function huitcoran_assets() {
	$rep = get_template_directory_uri();
	wp_enqueue_style( 'huitcoran', get_stylesheet_uri(), array(), HUITCORAN_VERSION );
	wp_enqueue_script( 'huitcoran-lecteur', $rep . '/assets/lecteur.js', array(), HUITCORAN_VERSION, true );

	$l = huitcoran_langue();
	wp_localize_script(
		'huitcoran-lecteur',
		'HC',
		array(
			'lire'        => $l['lire'],
			'pause'       => $l['pause'],
			'erreur'      => $l['erreur_audio'],
			'reprendre'   => $l['reprendre'],
			'arrete_ici'  => $l['reprendre_quoi'],
			'sourate'     => $l['sourate'],
			'repeter_non' => $l['repeter_non'],
			'repeter_une' => $l['repeter_une'],
			'repeter_tout' => $l['repeter_tout'],
		)
	);
}
add_action( 'wp_enqueue_scripts', 'huitcoran_assets' );

/**
 * La langue et le sens de lecture de la page suivent le reglage.
 */
function huitcoran_attributs_html( $sortie ) {
	$l = huitcoran_langue();
	return 'lang="' . esc_attr( $l['code'] ) . '" dir="' . esc_attr( $l['dir'] ) . '"';
}
add_filter( 'language_attributes', 'huitcoran_attributs_html' );

/* --------------------------------------------------------------------- */
/* La recherche et les filtres de l'annuaire                               */
/* --------------------------------------------------------------------- */

function huitcoran_requete_annuaire( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}
	$annuaire = $query->is_post_type_archive( 'recitateur' )
		|| $query->is_tax( 'riwaya' )
		|| ( $query->is_home() && ! $query->is_search() );

	if ( $query->is_search() && ! isset( $query->query_vars['post_type'] ) ) {
		$query->set( 'post_type', 'recitateur' );
		$annuaire = true;
	}
	if ( ! $annuaire ) {
		return;
	}
	if ( $query->is_home() ) {
		$query->set( 'post_type', 'recitateur' );
	}
	$query->set( 'posts_per_page', (int) get_option( 'huitcoran_par_page', 30 ) );
	$query->set( 'orderby', 'title' );
	$query->set( 'order', 'ASC' );

	$lettre = isset( $_GET['lettre'] ) ? sanitize_text_field( wp_unslash( $_GET['lettre'] ) ) : '';
	if ( '' !== $lettre ) {
		$query->set(
			'meta_query',
			array(
				array(
					'key'     => '_hc_lettre',
					'value'   => substr( $lettre, 0, 1 ),
					'compare' => '=',
				),
			)
		);
	}
	$riwaya = isset( $_GET['riwaya'] ) ? sanitize_text_field( wp_unslash( $_GET['riwaya'] ) ) : '';
	if ( '' !== $riwaya && ! $query->is_tax( 'riwaya' ) ) {
		$query->set(
			'tax_query',
			array(
				array(
					'taxonomy' => 'riwaya',
					'field'    => 'slug',
					'terms'    => $riwaya,
				),
			)
		);
	}
}
add_action( 'pre_get_posts', 'huitcoran_requete_annuaire' );

/**
 * Le nom a afficher : (principal, secondaire).
 *
 * En arabe, c'est le nom arabe qui doit etre le gros titre - sinon on sert
 * une page arabe dont tous les noms sont en lettres latines.
 */
function huitcoran_noms_affiches( $post_id ) {
	$latin = get_the_title( $post_id );
	$arabe = get_post_meta( $post_id, '_hc_nom_ar', true );
	$l     = huitcoran_langue();
	if ( 'ar' === $l['code'] && '' !== trim( (string) $arabe ) ) {
		return array( $arabe, $latin, 'ar' );
	}
	return array( $latin, ( $arabe !== $latin ? $arabe : '' ), 'latin' );
}

/* --------------------------------------------------------------------- */
/* La recherche sur les autres graphies du nom                             */
/* --------------------------------------------------------------------- */

/**
 * "Afasy" ne trouvait rien : la liste ecrit "Al Afasi". "Soudais" non plus.
 * La recherche regarde donc aussi le nom arabe, les autres graphies et le nom
 * de l'enregistrement, en plus du titre.
 */
/**
 * La cle de recherche d'un nom : ce qui reste quand on enleve ce qui ne
 * change pas la prononciation d'une translitteration a l'autre.
 *
 * "Afasy" et "Alafasi", "Sudais" et "Soudais" sont le meme nom ecrit par deux
 * mains differentes. Les regles sont volontairement peu nombreuses et
 * explicites : accents enleves, ou -> u, y -> i, lettres doublees ramenees a
 * une seule, tout le reste jete. Ce n'est pas une recherche phonetique, et
 * ca ne pretend pas l'etre : c'est un filet en plus de la recherche normale,
 * jamais a sa place. L'arabe passe par la recherche normale, pas par ici.
 */
function huitcoran_cle_recherche( $texte ) {
	$t = remove_accents( (string) $texte );
	$t = function_exists( 'mb_strtolower' ) ? mb_strtolower( $t, 'UTF-8' ) : strtolower( $t );
	$t = str_replace( array( 'ou', 'y' ), array( 'u', 'i' ), $t );
	$t = preg_replace( '/[^a-z0-9]+/', '', $t );
	$t = preg_replace( '/(.)\1+/', '$1', $t );
	return (string) $t;
}

/** Recalcule la cle de recherche d'une fiche a partir de ses noms. */
function huitcoran_maj_cle_recherche( $post_id ) {
	$morceaux = array(
		get_the_title( $post_id ),
		get_post_meta( $post_id, '_hc_alias', true ),
		get_post_meta( $post_id, '_hc_moshaf', true ),
	);
	$cle = huitcoran_cle_recherche( implode( ' ', array_filter( $morceaux ) ) );
	update_post_meta( $post_id, '_hc_cle_recherche', $cle );
	return $cle;
}
add_action( 'save_post_recitateur', 'huitcoran_maj_cle_recherche', 30, 1 );

function huitcoran_recherche_ciblee( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
		return false;
	}
	$type = $query->get( 'post_type' );
	if ( is_array( $type ) ) {
		return in_array( 'recitateur', $type, true );
	}
	return 'recitateur' === $type;
}

function huitcoran_recherche_join( $join, $query ) {
	global $wpdb;
	if ( ! huitcoran_recherche_ciblee( $query ) ) {
		return $join;
	}
	$join .= " LEFT JOIN {$wpdb->postmeta} AS hcalias
		ON hcalias.post_id = {$wpdb->posts}.ID
		AND hcalias.meta_key IN ( '_hc_alias', '_hc_nom_ar', '_hc_moshaf', '_hc_cle_recherche' ) ";
	return $join;
}
add_filter( 'posts_join', 'huitcoran_recherche_join', 10, 2 );

function huitcoran_recherche_where( $where, $query ) {
	global $wpdb;
	if ( ! huitcoran_recherche_ciblee( $query ) ) {
		return $where;
	}
	$mots = trim( (string) $query->get( 's' ) );
	if ( '' === $mots ) {
		return $where;
	}
	$comme = '%' . $wpdb->esc_like( $mots ) . '%';
	$cle   = huitcoran_cle_recherche( $mots );
	// Le OR final est complet a lui seul (type ET statut), pour ne pas
	// elargir les autres conditions de la requete.
	$morceau = " OR ( {$wpdb->posts}.post_type = 'recitateur'
			AND {$wpdb->posts}.post_status = 'publish'
			AND hcalias.meta_value LIKE %s ) ";
	$where  .= $wpdb->prepare( $morceau, $comme );
	// Trois lettres au minimum APRES normalisation : "zzzz" se reduit a "z",
	// et un LIKE '%z%' ramenait tout le monde. Un filtre trop large ne dit
	// pas "je n'ai rien trouve", il dit "tout correspond" - ce qui est pire.
	if ( strlen( $cle ) >= 3 ) {
		$where .= $wpdb->prepare( $morceau, '%' . $wpdb->esc_like( $cle ) . '%' );
	}
	return $where;
}
add_filter( 'posts_where', 'huitcoran_recherche_where', 10, 2 );

function huitcoran_recherche_distinct( $distinct, $query ) {
	if ( ! huitcoran_recherche_ciblee( $query ) ) {
		return $distinct;
	}
	return 'DISTINCT';
}
add_filter( 'posts_distinct', 'huitcoran_recherche_distinct', 10, 2 );

/** Les lettres presentes, pour le filtre alphabetique. */
function huitcoran_lettres_presentes() {
	global $wpdb;
	$lettres = $wpdb->get_col(
		"SELECT DISTINCT m.meta_value FROM {$wpdb->postmeta} m
		 INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
		 WHERE m.meta_key = '_hc_lettre' AND p.post_type = 'recitateur'
		   AND p.post_status = 'publish' AND m.meta_value <> ''
		 ORDER BY m.meta_value ASC"
	);
	return $lettres ? $lettres : array();
}

/** Le nombre de recitateurs publies. */
function huitcoran_nombre_recitateurs() {
	$compte = wp_count_posts( 'recitateur' );
	return isset( $compte->publish ) ? (int) $compte->publish : 0;
}

/**
 * La lettre de classement est deduite du titre quand elle n'est pas fournie.
 */
function huitcoran_lettre_par_defaut( $post_id, $post ) {
	if ( 'recitateur' !== $post->post_type ) {
		return;
	}
	$actuelle = get_post_meta( $post_id, '_hc_lettre', true );
	if ( '' !== trim( (string) $actuelle ) ) {
		return;
	}
	$titre = trim( wp_strip_all_tags( $post->post_title ) );
	if ( '' === $titre ) {
		return;
	}
	$lettre = mb_strtoupper( mb_substr( $titre, 0, 1, 'UTF-8' ), 'UTF-8' );
	update_post_meta( $post_id, '_hc_lettre', $lettre );
}
add_action( 'save_post_recitateur', 'huitcoran_lettre_par_defaut', 20, 2 );
