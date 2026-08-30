<?php
/**
 * Les textes de l'interface, en francais, en arabe et en anglais.
 *
 * La langue se choisit dans Reglages > Coran. Elle decide aussi du sens
 * de lecture : l'arabe passe la page entiere en dir="rtl".
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function huitcoran_textes() {
	return array(
		'fr' => array(
			'dir'            => 'ltr',
			'code'           => 'fr',
			'recitateurs'    => 'Récitateurs',
			'recitateur'     => 'Récitateur',
			'un_recitateur'  => '%s récitateur',
			'des_recitateurs' => '%s récitateurs',
			'rechercher'     => 'Rechercher un récitateur',
			'chercher'       => 'Chercher',
			'toutes_riwayat' => 'Toutes les riwayat',
			'toutes_lettres' => 'Toutes les lettres',
			'riwaya'         => 'Riwaya',
			'sourates'       => 'Sourates',
			'sourate'        => 'Sourate',
			'nb_sourates'    => '%s sourates',
			'coran_complet'  => 'Coran complet',
			'partiel'        => 'Récitation partielle',
			'ecouter'        => 'Écouter',
			'lire'           => 'Lire',
			'pause'          => 'Pause',
			'precedent'      => 'Sourate précédente',
			'suivant'        => 'Sourate suivante',
			'telecharger'    => 'Télécharger',
			'dl_en_cours'    => 'Téléchargement en cours',
			'dl_annuler'     => 'Annuler le téléchargement',
			'dl_fait'        => 'Téléchargée',
			'dl_echec'       => 'Le téléchargement n’a pas abouti. Le fichier s’ouvre dans un nouvel onglet : clic droit, « Enregistrer sous ».',
			'mecquoise'      => 'Mecquoise',
			'medinoise'      => 'Médinoise',
			'reprendre'      => 'Reprendre l’écoute',
			'reprendre_quoi' => 'Vous vous étiez arrêté ici',
			'repeter'        => 'Répéter',
			'repeter_une'    => 'Répéter cette sourate',
			'repeter_tout'   => 'Répéter tout',
			'repeter_non'    => 'Ne pas répéter',
			'aucun'          => 'Aucun récitateur pour l’instant.',
			'aucun_admin'    => 'Aucun récitateur pour l’instant. Ajoutez-en depuis le tableau de bord : Récitateurs > Importer.',
			'aucun_resultat' => 'Aucun récitateur ne correspond à cette recherche.',
			'retour'         => 'Retour aux récitateurs',
			'source'         => 'Source de l’enregistrement',
			'source_note'    => 'Les fichiers audio ne sont pas hébergés sur ce site : ils sont lus depuis le serveur de la source indiquée.',
			'page'           => 'Page %s',
			'pages'          => 'Pages %s à %s',
			'chargement'     => 'Chargement…',
			'erreur_audio'   => 'Ce fichier n’a pas pu être lu. Le serveur de la source ne répond pas.',
			'plein_ecran'    => 'Texte plus grand',
			'lettre'         => 'Lettre',
		),
		'ar' => array(
			'dir'            => 'rtl',
			'code'           => 'ar',
			'recitateurs'    => 'القراء',
			'recitateur'     => 'القارئ',
			'un_recitateur'  => 'قارئ %s',
			'des_recitateurs' => '%s قارئ',
			'rechercher'     => 'ابحث عن قارئ',
			'chercher'       => 'بحث',
			'toutes_riwayat' => 'كل الروايات',
			'toutes_lettres' => 'كل الحروف',
			'riwaya'         => 'الرواية',
			'sourates'       => 'السور',
			'sourate'        => 'سورة',
			'nb_sourates'    => '%s سورة',
			'coran_complet'  => 'المصحف كامل',
			'partiel'        => 'تلاوة جزئية',
			'ecouter'        => 'استمع',
			'lire'           => 'تشغيل',
			'pause'          => 'إيقاف مؤقت',
			'precedent'      => 'السورة السابقة',
			'suivant'        => 'السورة التالية',
			'telecharger'    => 'تحميل',
			'dl_en_cours'    => 'جارٍ التحميل',
			'dl_annuler'     => 'إلغاء التحميل',
			'dl_fait'        => 'تم التحميل',
			'dl_echec'       => 'تعذّر التحميل. يُفتح الملف في تبويب جديد: زر الفأرة الأيمن ثم «حفظ باسم».',
			'mecquoise'      => 'مكية',
			'medinoise'      => 'مدنية',
			'reprendre'      => 'متابعة الاستماع',
			'reprendre_quoi' => 'توقفت هنا',
			'repeter'        => 'تكرار',
			'repeter_une'    => 'تكرار السورة',
			'repeter_tout'   => 'تكرار الكل',
			'repeter_non'    => 'بدون تكرار',
			'aucun'          => 'لا يوجد قراء بعد.',
			'aucun_admin'    => 'لا يوجد قراء بعد. أضفهم من لوحة التحكم.',
			'aucun_resultat' => 'لا نتائج لهذا البحث.',
			'retour'         => 'العودة إلى القراء',
			'source'         => 'مصدر التسجيل',
			'source_note'    => 'الملفات الصوتية ليست مستضافة على هذا الموقع، بل تُقرأ من خادم المصدر المذكور.',
			'page'           => 'صفحة %s',
			'pages'          => 'الصفحات %s إلى %s',
			'chargement'     => 'جارٍ التحميل…',
			'erreur_audio'   => 'تعذّر تشغيل هذا الملف. خادم المصدر لا يستجيب.',
			'plein_ecran'    => 'خط أكبر',
			'lettre'         => 'حرف',
		),
		'en' => array(
			'dir'            => 'ltr',
			'code'           => 'en',
			'recitateurs'    => 'Reciters',
			'recitateur'     => 'Reciter',
			'un_recitateur'  => '%s reciter',
			'des_recitateurs' => '%s reciters',
			'rechercher'     => 'Search for a reciter',
			'chercher'       => 'Search',
			'toutes_riwayat' => 'All riwayat',
			'toutes_lettres' => 'All letters',
			'riwaya'         => 'Riwaya',
			'sourates'       => 'Surahs',
			'sourate'        => 'Surah',
			'nb_sourates'    => '%s surahs',
			'coran_complet'  => 'Complete Quran',
			'partiel'        => 'Partial recitation',
			'ecouter'        => 'Listen',
			'lire'           => 'Play',
			'pause'          => 'Pause',
			'precedent'      => 'Previous surah',
			'suivant'        => 'Next surah',
			'telecharger'    => 'Download',
			'dl_en_cours'    => 'Downloading',
			'dl_annuler'     => 'Cancel the download',
			'dl_fait'        => 'Downloaded',
			'dl_echec'       => 'The download did not complete. The file opens in a new tab: right-click, “Save as”.',
			'mecquoise'      => 'Meccan',
			'medinoise'      => 'Medinan',
			'reprendre'      => 'Resume listening',
			'reprendre_quoi' => 'You stopped here',
			'repeter'        => 'Repeat',
			'repeter_une'    => 'Repeat this surah',
			'repeter_tout'   => 'Repeat all',
			'repeter_non'    => 'No repeat',
			'aucun'          => 'No reciter yet.',
			'aucun_admin'    => 'No reciter yet. Add some from the dashboard: Reciters > Import.',
			'aucun_resultat' => 'No reciter matches this search.',
			'retour'         => 'Back to reciters',
			'source'         => 'Recording source',
			'source_note'    => 'The audio files are not hosted on this site: they are played from the source server named here.',
			'page'           => 'Page %s',
			'pages'          => 'Pages %s to %s',
			'chargement'     => 'Loading…',
			'erreur_audio'   => 'This file could not be played. The source server is not responding.',
			'plein_ecran'    => 'Larger text',
			'lettre'         => 'Letter',
		),
	);
}

/** Le tableau de textes de la langue choisie. */
function huitcoran_langue() {
	$textes = huitcoran_textes();
	$choix  = get_option( 'huitcoran_langue', 'fr' );
	if ( ! isset( $textes[ $choix ] ) ) {
		$choix = 'fr';
	}
	return $textes[ $choix ];
}

/** Un texte de l'interface, deja echappe pour l'affichage. */
function hc_t( $cle ) {
	$l = huitcoran_langue();
	return isset( $l[ $cle ] ) ? $l[ $cle ] : $cle;
}

function hc_e( $cle ) {
	echo esc_html( hc_t( $cle ) );
}
