<?php
/**
 * Genera la scheda editoriale di un personaggio e la salva in JSON.
 * Uso: php gen_one.php <categoria> <slug>
 */

$cat  = $argv[1] ?? '';
$slug = $argv[2] ?? '';
if ( ! $cat || ! $slug ) {
	fwrite( STDERR, "uso: gen_one.php categoria slug\n" );
	exit( 1 );
}

$data_file = "/opt/aria/backend/characters/data/{$cat}.json";
$rows      = json_decode( (string) @file_get_contents( $data_file ), true );
if ( ! is_array( $rows ) ) {
	fwrite( STDERR, "dati categoria mancanti: $cat\n" );
	exit( 1 );
}

$c = null;
foreach ( $rows as $row ) {
	if ( ( $row['slug'] ?? '' ) === $slug ) {
		$c = $row;
		break;
	}
}
if ( ! $c ) {
	fwrite( STDERR, "personaggio non trovato: $cat/$slug\n" );
	exit( 1 );
}

$out_dir = "/opt/aria/backend/characters/editorial/{$cat}";
if ( ! is_dir( $out_dir ) && ! mkdir( $out_dir, 0755, true ) ) {
	fwrite( STDERR, "impossibile creare $out_dir\n" );
	exit( 1 );
}
$out_file = "{$out_dir}/{$slug}.json";
if ( file_exists( $out_file ) && filesize( $out_file ) > 300 ) {
	echo "skip $cat/$slug\n";
	exit( 0 );
}

$stanza = str_replace( '-', ' ', $cat );
$profilo = array(
	'nome_completo' => $c['full_name'] ?? $c['name'],
	'eta'           => $c['age'] ?? null,
	'ruolo'         => $c['role'] ?? '',
	'genere'        => $c['gender_display'] ?? '',
	'orientamento'  => $c['sexual_orientation_display'] ?? '',
	'tag'           => $c['tags'] ?? array(),
	'descrizione'   => $c['description'] ?? '',
	'personalita'   => $c['personality'] ?? '',
	'contesto'      => $c['category_context'] ?? '',
	'tratti'        => $c['core_traits'] ?? array(),
	'conoscenze'    => $c['knowledge_domains'] ?? array(),
);

$prompt = "Sei un copywriter SEO italiano specializzato in contenuti per adulti. "
	. "Scrivi la scheda editoriale completa di un personaggio di chat erotica ambientata nella stanza \"{$stanza}\".\n\n"
	. "REGOLE:\n"
	. "- Italiano naturale, terza persona, tono diretto, sensoriale, mai volgare a caso.\n"
	. "- 450-550 parole totali.\n"
	. "- Usa SOLO i dati del profilo; non inventare fatti biografici verificabili. Puoi dedurre hobby e desideri da tag, personalità e contesto.\n"
	. "- Deve invogliare a iniziare la chat e posizionarsi su ricerche tipo \"chat con {$c['name']} {$c['role']}\".\n"
	. "- Niente frasi generiche tipo \"sei pronto a sfidare i limiti\".\n"
	. "- Varia l'inizio delle frasi, non ripetere il nome in ogni paragrafo.\n\n"
	. "Rispondi SOLO con JSON valido con questa struttura:\n"
	. "{\"lead\":\"2 frasi di gancio\",\"chi\":\"80-110 parole: chi è, età, ruolo, carattere\","
	. "\"passioni\":\"80-110 parole: hobby, passioni, cosa ama fare\","
	. "\"come\":\"80-110 parole: come si comporta in chat\","
	. "\"invito\":\"60-90 parole: cosa vorrebbe fare con chi legge\","
	. "\"iniziare_intro\":\"30-50 parole: come rompere il ghiaccio\","
	. "\"a_chi\":\"40-60 parole: a chi piace e per chi è adatta\","
	. "\"esempi\":[\"messaggio 1\",\"messaggio 2\",\"messaggio 3\"],"
	. "\"faq\":[{\"q\":\"domanda\",\"a\":\"risposta\"},{\"q\":\"domanda\",\"a\":\"risposta\"},{\"q\":\"domanda\",\"a\":\"risposta\"}]}\n\n"
	. "PROFILO:\n" . json_encode( $profilo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );

$schema = array(
	'type'       => 'OBJECT',
	'properties' => array(
		'lead'           => array( 'type' => 'STRING' ),
		'chi'            => array( 'type' => 'STRING' ),
		'passioni'       => array( 'type' => 'STRING' ),
		'come'           => array( 'type' => 'STRING' ),
		'invito'         => array( 'type' => 'STRING' ),
		'iniziare_intro' => array( 'type' => 'STRING' ),
		'a_chi'          => array( 'type' => 'STRING' ),
		'esempi'         => array( 'type' => 'ARRAY', 'items' => array( 'type' => 'STRING' ), 'minItems' => 3, 'maxItems' => 3 ),
		'faq'            => array(
			'type'     => 'ARRAY',
			'minItems' => 3,
			'maxItems' => 3,
			'items'    => array(
				'type'       => 'OBJECT',
				'properties' => array(
					'q' => array( 'type' => 'STRING' ),
					'a' => array( 'type' => 'STRING' ),
				),
				'required'   => array( 'q', 'a' ),
			),
		),
	),
	'required'   => array( 'lead', 'chi', 'passioni', 'come', 'invito', 'iniziare_intro', 'a_chi', 'esempi', 'faq' ),
);

$key = '';
foreach ( file( '/opt/aria/backend/.env', FILE_IGNORE_NEW_LINES ) as $line ) {
	if ( str_starts_with( $line, 'GEMINI_API_KEY=' ) ) {
		$key = trim( substr( $line, strlen( 'GEMINI_API_KEY=' ) ), "\"'" );
		break;
	}
}
if ( ! $key ) {
	fwrite( STDERR, "GEMINI_API_KEY assente\n" );
	exit( 1 );
}

$body = json_encode(
	array(
		'contents'         => array( array( 'parts' => array( array( 'text' => $prompt ) ) ) ),
		'generationConfig' => array(
			'temperature'      => 0.9,
			'maxOutputTokens'  => 2600,
			'thinkingConfig'   => array( 'thinkingBudget' => 0 ),
			'responseMimeType' => 'application/json',
			'responseSchema'   => $schema,
		),
	),
	JSON_UNESCAPED_UNICODE
);

$model = getenv( 'GEN_MODEL' ) ?: 'gemini-3.1-flash-lite';
$url   = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $key;

for ( $tentativo = 1; $tentativo <= 5; $tentativo++ ) {
	$ch = curl_init( $url );
	curl_setopt_array(
		$ch,
		array(
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => $body,
			CURLOPT_HTTPHEADER     => array( 'Content-Type: application/json' ),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 120,
		)
	);
	$resp = curl_exec( $ch );
	$code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	curl_close( $ch );

	if ( 200 === $code ) {
		$json = json_decode( (string) $resp, true );
		$text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
		$doc  = json_decode( $text, true );
		$ok   = is_array( $doc );
		if ( $ok ) {
			foreach ( array( 'lead', 'chi', 'passioni', 'come', 'invito', 'iniziare_intro', 'a_chi' ) as $campo ) {
				if ( empty( $doc[ $campo ] ) || ! is_string( $doc[ $campo ] ) ) {
					$ok = false;
				}
			}
			if ( ! is_array( $doc['esempi'] ?? null ) || count( $doc['esempi'] ) < 3 ) {
				$ok = false;
			}
			if ( ! is_array( $doc['faq'] ?? null ) || count( $doc['faq'] ) < 3 ) {
				$ok = false;
			} else {
				foreach ( $doc['faq'] as $coppia ) {
					if ( empty( $coppia['q'] ) || empty( $coppia['a'] ) ) {
						$ok = false;
					}
				}
			}
			$parole = str_word_count( strip_tags( implode( ' ', array( $doc['lead'], $doc['chi'], $doc['passioni'], $doc['come'], $doc['invito'], $doc['iniziare_intro'], $doc['a_chi'] ) ) ) );
			if ( $parole < 350 ) {
				$ok = false;
			}
		}
		if ( $ok ) {
			$doc['generated_at'] = gmdate( 'c' );
			$doc['model']        = $model;
			file_put_contents( $out_file, json_encode( $doc, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
			echo "ok $cat/$slug\n";
			sleep( 3 );
			exit( 0 );
		}
	}
	sleep( 429 === $code ? 15 * $tentativo : 2 * $tentativo );
}

fwrite( STDERR, "fallito $cat/$slug (ultimo code $code)\n" );
exit( 1 );
