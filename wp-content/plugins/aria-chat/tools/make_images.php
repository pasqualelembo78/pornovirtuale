<?php
/**
 * Genera OG image 1200x630 per le pagine personaggio editorializzate.
 * Uso: php make_images.php [--force]
 */

$force    = in_array( '--force', $argv, true );
$only_cat = ( $argv[1] ?? '' ) !== '--force' ? ( $argv[1] ?? '' ) : '';
$only_slug = $only_cat ? ( $argv[2] ?? '' ) : '';
$base_data = '/opt/aria/backend/characters/data';
$base_edit = '/opt/aria/backend/characters/editorial';
$avatars   = '/opt/aria/backend/static/avatars';
$out_root  = '/var/www/pornovirtuale/wp-content/uploads/og-personaggi';
$font_bold = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
$font_reg  = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';

if ( ! is_file( $font_bold ) ) {
	fwrite( STDERR, "font mancante\n" );
	exit( 1 );
}

// Indice avatar: id => percorso immagine.
$avatar_map = array();
$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $avatars, FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $f ) {
	if ( $f->isFile() && preg_match( '/\.(png|jpe?g|webp)$/i', $f->getFilename() ) ) {
		$avatar_map[ pathinfo( $f->getFilename(), PATHINFO_FILENAME ) ] = $f->getPathname();
	}
}

$palette = array( '#c9366b', '#8b5cf6', '#0ea5e9', '#f59e0b', '#10b981', '#ef4444' );

// Nomi categoria leggibili.
$cat_names = array();
foreach ( (array) json_decode( (string) @file_get_contents( $base_data . '/categories.json' ), true ) as $cat_row ) {
	if ( ! empty( $cat_row['id'] ) ) {
		$cat_names[ $cat_row['id'] ] = $cat_row['name'] ?? $cat_row['id'];
	}
}

$ok = 0;
$ko = 0;

$elenco = ( $only_cat && $only_slug )
	? array( $base_edit . '/' . $only_cat . '/' . $only_slug . '.json' )
	: glob( $base_edit . '/*/*.json' );

foreach ( $elenco as $edit ) {
	if ( ! is_file( $edit ) ) {
		continue;
	}
	$cat  = basename( dirname( $edit ) );
	$slug = basename( $edit, '.json' );
	$out_dir = $out_root . '/' . $cat;
	$out = $out_dir . '/' . $slug . '.jpg';

	if ( ! $force && is_file( $out ) ) {
		continue;
	}
	if ( ! is_dir( $out_dir ) ) {
		mkdir( $out_dir, 0755, true );
	}

	$rows = json_decode( (string) @file_get_contents( $base_data . '/' . $cat . '.json' ), true ) ?: array();
	$c = null;
	foreach ( $rows as $row ) {
		if ( ( $row['slug'] ?? '' ) === $slug ) {
			$c = $row;
			break;
		}
	}
	if ( ! $c ) {
		$ko++;
		continue;
	}

	$nome = trim( (string) ( $c['full_name'] ?? $c['name'] ?? $slug ) );
	$ruolo = trim( (string) ( $c['role'] ?? '' ) );
	$cat_name = $cat_names[ $cat ] ?? ucfirst( str_replace( '-', ' ', $cat ) );
	$colore = $palette[ crc32( $slug ) % count( $palette ) ];
	$initials = '';
	foreach ( preg_split( '/\s+/', $nome ) as $p ) {
		if ( '' !== $p && mb_strlen( $initials ) < 2 ) {
			$initials .= mb_strtoupper( mb_substr( $p, 0, 1 ) );
		}
	}

	$title_file = tempnam( '/tmp', 'ogt' );
	file_put_contents( $title_file, 'Chat con ' . $nome );
	$subtitle_file = tempnam( '/tmp', 'ogs' );
	file_put_contents( $subtitle_file, $ruolo ? ucfirst( $ruolo ) . ' · ' . $cat_name : $cat_name );

	$avatar = $avatar_map[ (string) ( $c['id'] ?? '' ) ] ?? '';

	$args = array(
		'/usr/bin/convert',
		'-size', '1200x630',
		'gradient:#101019-#241a2e',
		'-fill', $colore,
		'-draw', 'rectangle 0,0 14,630',
	);

	// Riquadro destro: avatar reale oppure monogramma.
	if ( $avatar ) {
		$args = array_merge(
			$args,
			array(
				'(', $avatar, '-resize', '460x460^', '-gravity', 'center', '-extent', '460x460', ')',
				'-gravity', 'northwest', '-geometry', '+690+85', '-composite',
			)
		);
	} else {
		$args = array_merge(
			$args,
			array(
				'-fill', 'rgba(255,255,255,0.07)',
				'-draw', 'circle 920,315 920,545',
				'-fill', $colore,
				'-font', $font_bold,
				'-pointsize', '150',
				'-gravity', 'northwest',
				'-annotate', '+790+225', $initials,
			)
		);
	}

	$args = array_merge(
		$args,
		array(
			'-fill', 'white',
			'-font', $font_bold,
			'-pointsize', '58',
			'-gravity', 'northwest',
			'-background', 'none',
			'-size', '620x230',
			'caption:Chat con ' . $nome,
			'-geometry', '+70+90', '-composite',
			'-fill', 'rgba(255,255,255,0.72)',
			'-font', $font_reg,
			'-pointsize', '30',
			'-background', 'none',
			'-size', '620x110',
			'caption:' . ( $ruolo ? ucfirst( $ruolo ) . ' · ' . $cat_name : $cat_name ),
			'-geometry', '+70+345', '-composite',
			'-fill', $colore,
			'-draw', 'roundrectangle 70,480 360,540 12,12',
			'-fill', 'white',
			'-font', $font_bold,
			'-pointsize', '24',
			'-annotate', '+96+525', 'Chatta gratis',
			'-fill', 'rgba(255,255,255,0.85)',
			'-font', $font_bold,
			'-pointsize', '26',
			'-annotate', '+70+600', 'Pornovirtuale.com',
			'-fill', $colore,
			'-draw', 'roundrectangle 1100,565 1165,605 8,8',
			'-fill', 'white',
			'-pointsize', '20',
			'-annotate', '+1111+595', '18+',
			'-quality', '88',
			'-strip',
			$out,
		)
	);

	$cmd = implode( ' ', array_map( 'escapeshellarg', $args ) );
	exec( $cmd . ' 2>&1', $o, $code );
	@unlink( $title_file );
	@unlink( $subtitle_file );

	if ( 0 === $code && is_file( $out ) ) {
		$ok++;
	} else {
		$ko++;
		echo "KO $cat/$slug\n";
	}
}

echo "ok=$ok ko=$ko\n";
