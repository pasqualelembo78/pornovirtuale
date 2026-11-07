<?php
/**
 * Costruisce /llms-full.txt: testo integrale delle pagine chiave per i crawler AI.
 * Uso: php build_llms_full.php
 */

require '/var/www/pornovirtuale/wp-load.php';

$pagine = array(
	'come-funziona',
	'chi-siamo',
	'personaggi-inventati',
	'video-vr-720p-1080p-4k-differenze',
	'scegliere-visore-vr-contenuti-adulti',
);

$ids = array();
foreach ( $pagine as $slug ) {
	$p = get_page_by_path( $slug );
	if ( $p ) {
		$ids[ $p->ID ] = $p;
	}
}
foreach ( array( 'video-vr-720p-1080p-4k-differenze', 'scegliere-visore-vr-contenuti-adulti' ) as $slug_guida ) {
	$posta_guida = get_page_by_path( $slug_guida, OBJECT, 'post' );
	if ( $posta_guida ) {
		$ids[ $posta_guida->ID ] = $posta_guida;
	}
}

$out  = "# Pornovirtuale.com — contenuti completi per agenti AI\n\n";
$out .= "> Testo integrale delle pagine chiave, in italiano. Contenuti per adulti (18+). Citazione consentita con link alla pagina e data di aggiornamento; non è consentita la ripubblicazione integrale.\n\n";
$out .= "- Indice sintetico: https://pornovirtuale.com/ai-index.json\n";
$out .= "- Elenco schede personaggio: https://pornovirtuale.com/ai-characters.json\n";
$out .= "- Sitemap: https://pornovirtuale.com/sitemap_index.xml\n";
$out .= '- Aggiornato: ' . gmdate( 'Y-m-d' ) . "\n\n";

foreach ( $ids as $post ) {
	$testo = strip_shortcodes( $post->post_content );
	$testo = preg_replace( '#<(script|style)[^>]*>.*?</\1>#si', ' ', $testo );
	$testo = preg_replace( '#</(p|h[1-6]|li|tr|div|section|article)>#i', "\n", $testo );
	$testo = preg_replace( '#<br\s*/?>#i', "\n", $testo );
	$testo = html_entity_decode( wp_strip_all_tags( $testo ), ENT_QUOTES, 'UTF-8' );
	$testo = preg_replace( "/[ \t]+/u", ' ', $testo );
	$testo = preg_replace( "/\n{3,}/u", "\n\n", $testo );
	$testo = trim( $testo );

	$out .= "## " . $post->post_title . "\n\n";
	$out .= 'URL: ' . get_permalink( $post ) . "\n";
	$out .= 'Aggiornato: ' . get_the_modified_date( 'Y-m-d', $post ) . "\n\n";
	$out .= $testo . "\n\n---\n\n";
}

// Un esempio di scheda personaggio per mostrare il formato dei contenuti.
$char_data = json_decode( (string) file_get_contents( '/opt/aria/backend/characters/data/amanti-segreti.json' ), true ) ?: array();
foreach ( array_slice( $char_data, 0, 3 ) as $riga ) {
	$slug = ! empty( $riga['slug'] ) ? $riga['slug'] : sanitize_title( $riga['full_name'] ?? '' );
	$file = '/opt/aria/backend/characters/editorial/amanti-segreti/' . $slug . '.json';
	if ( ! is_file( $file ) ) {
		continue;
	}
	$ed   = json_decode( (string) file_get_contents( $file ), true ) ?: array();
	$url  = home_url( '/stanza/amanti-segreti/' . $slug . '/' );
	$out .= "## Esempio scheda personaggio: " . ( $riga['full_name'] ?? '' ) . "\n\n";
	$out .= 'URL: ' . $url . "\n";
	$out .= 'Aggiornato: ' . ( $ed['generated_at'] ?? '' ) . "\n\n";
	foreach ( array( 'lead', 'chi', 'passioni', 'come', 'invito' ) as $k ) {
		if ( ! empty( $ed[ $k ] ) ) {
			$out .= $ed[ $k ] . "\n\n";
		}
	}
	$out .= "---\n\n";
}

$tmp = '/var/www/pornovirtuale/.llms-full.txt.tmp';
file_put_contents( $tmp, $out );
rename( $tmp, '/var/www/pornovirtuale/llms-full.txt' );
echo 'llms-full.txt aggiornato: ' . strlen( $out ) . " byte\n";
