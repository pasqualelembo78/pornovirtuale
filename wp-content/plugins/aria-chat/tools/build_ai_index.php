<?php
/**
 * Costruisce /ai-index.json e /ai-characters.json: indici machine-readable per crawler AI.
 * Uso: php build_ai_index.php
 */

require '/var/www/pornovirtuale/wp-load.php';

$data_dir = '/opt/aria/backend/characters/data';
$edit_dir = '/opt/aria/backend/characters/editorial';

$totale      = 0;
$categorie   = array();
$personaggi  = array();
$per_cat     = array();

foreach ( glob( $data_dir . '/*.json' ) as $file ) {
	if ( 'categories.json' === basename( $file ) ) {
		continue;
	}

	$cat   = basename( $file, '.json' );
	$righe = json_decode( (string) file_get_contents( $file ), true ) ?: array();

	$totale += count( $righe );
	$categorie[] = array(
		'name' => ucfirst( str_replace( '-', ' ', $cat ) ),
		'url'  => home_url( '/stanza/' . $cat . '/' ),
	);

	$pubblicati = 0;
	foreach ( $righe as $riga ) {
		$nome = isset( $riga['full_name'] ) ? $riga['full_name'] : ( $riga['name'] ?? '' );
		if ( '' === $nome ) {
			continue;
		}

		$slug = ! empty( $riga['slug'] ) ? $riga['slug'] : sanitize_title( $nome );
		$json = $edit_dir . '/' . $cat . '/' . $slug . '.json';
		if ( ! is_file( $json ) ) {
			continue;
		}

		$editoriale = json_decode( (string) file_get_contents( $json ), true ) ?: array();
		$aggiornato = $editoriale['generated_at'] ?? '';

		$descrizione = $editoriale['lead'] ?? ( $editoriale['chi'] ?? '' );
		$descrizione = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $descrizione ) ) );
		if ( mb_strlen( $descrizione ) > 180 ) {
			$descrizione = rtrim( mb_substr( $descrizione, 0, 177 ) ) . '…';
		}

		$personaggi[] = array(
			'name'        => $nome,
			'url'         => home_url( '/stanza/' . $cat . '/' . $slug . '/' ),
			'category'    => str_replace( '-', ' ', $cat ),
			'role'        => $riga['role'] ?? '',
			'description' => $descrizione,
			'updated'     => $aggiornato,
		);
		++$pubblicati;
	}

	$per_cat[ $cat ] = array(
		'total'     => count( $righe ),
		'editorial' => $pubblicati,
	);
}

$editoriali = count( $personaggi );

usort(
	$personaggi,
	static function ( $a, $b ) {
		return strcmp( $a['category'] . $a['name'], $b['category'] . $b['name'] );
	}
);

$pagine = array(
	array( 'title' => 'Home', 'url' => home_url( '/' ), 'description' => 'Stanze live VR, video 180/360 e guida al porno virtuale.' ),
	array( 'title' => 'Guida 720p, 1080p e 4K in VR', 'url' => home_url( '/video-vr-720p-1080p-4k-differenze/' ), 'description' => 'Cosa cambia tra le risoluzioni in realtà virtuale: bitrate, visore, connessione, limiti.' ),
	array( 'title' => 'Come scegliere un visore VR', 'url' => home_url( '/scegliere-visore-vr-contenuti-adulti/' ), 'description' => 'Risoluzione per occhio, campo visivo, refresh, codec e comfort: come scegliere il visore.' ),
	array( 'title' => 'Catalogo video VR', 'url' => home_url( '/video-vr/' ), 'description' => 'Video VR a 180 e 360 gradi, con risoluzioni e formati indicati.' ),
	array( 'title' => 'Stanze live', 'url' => home_url( '/stanze-live/' ), 'description' => 'Elenco delle stanze di chat a tema con personaggi inventati.' ),
	array( 'title' => 'Come funziona', 'url' => home_url( '/come-funziona/' ), 'description' => 'Accesso, prova gratuita, login e uso da browser o visore.' ),
	array( 'title' => 'Chi siamo', 'url' => home_url( '/chi-siamo/' ), 'description' => 'Redazione, metodo di test e criteri editoriali.' ),
	array( 'title' => 'Personaggi inventati', 'url' => home_url( '/personaggi-inventati/' ), 'description' => 'Come sono costruiti i personaggi e perché non sono persone reali.' ),
	array( 'title' => 'Blog', 'url' => home_url( '/blog/' ), 'description' => 'Guide, tutorial, confronti e sicurezza.' ),
);

$indice = array(
	'site'        => 'Pornovirtuale.com',
	'language'    => 'it-IT',
	'updated'     => gmdate( 'c' ),
	'description' => 'Guida italiana a porno virtuale e cam VR: stanze live, video 180/360, schede di personaggi inventati e guide pratiche. Contenuti 18+.',
	'license'     => array(
		'citation' => 'Citazione consentita con link alla pagina e data di aggiornamento; non è consentita la ripubblicazione integrale.',
		'url'      => home_url( '/chi-siamo/' ),
	),
	'facts'       => array(
		'Lingua italiana, contenuti per adulti 18+.',
		'Nessuna installazione per navigare; accesso da browser o visore.',
		'La prova chat prevede 4 messaggi gratis, poi serve l’accesso.',
		'I personaggi delle chat sono inventati e non corrispondono a persone reali.',
		'Le pagine personaggio pubblicate hanno testo editoriale, FAQ e data di aggiornamento.',
		'I link affiliati sono segnalati con rel="nofollow sponsored".',
	),
	'stats'       => array(
		'characters_total'     => $totale,
		'characters_editorial' => $editoriali,
		'rooms'                => (int) wp_count_posts( 'stanza' )->publish,
		'videos'               => (int) wp_count_posts( 'video' )->publish,
		'guides'               => (int) wp_count_posts( 'post' )->publish,
	),
	'key_pages'   => $pagine,
	'categories'  => $categorie,
	'coverage'    => $per_cat,
	'feeds'       => array(
		'sitemap_index'      => home_url( '/sitemap_index.xml' ),
		'character_sitemap'  => home_url( '/?aria_char_sitemap=top' ),
		'characters'         => home_url( '/ai-characters.json' ),
		'llms'               => home_url( '/llms.txt' ),
		'llms_full'          => home_url( '/llms-full.txt' ),
		'rss'                => home_url( '/feed/' ),
	),
);

$json = wp_json_encode( $indice, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
file_put_contents( '/var/www/pornovirtuale/ai-index.json', $json . "\n" );

$char_index = array(
	'site'        => 'Pornovirtuale.com',
	'language'    => 'it-IT',
	'updated'     => gmdate( 'c' ),
	'description' => 'Elenco delle schede personaggio pubblicate, con testo editoriale e FAQ. I personaggi sono inventati.',
	'count'       => $editoriali,
	'characters'  => $personaggi,
);
$json_char = wp_json_encode( $char_index, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
file_put_contents( '/var/www/pornovirtuale/ai-characters.json', $json_char . "\n" );

echo "ai-index.json e ai-characters.json aggiornati: {$editoriali}/{$totale} schede editoriali\n";
