<?php
/**
 * Debug temporaneo Yoast.
 */
$pv_log = static function ( $tag, $value = '' ) {
	file_put_contents(
		'/tmp/pv-debug.log',
		date( 'H:i:s' ) . ' ' . $tag . ' ' . $value . "\n",
		FILE_APPEND
	);
};

add_filter(
	'wpseo_title',
	function ( $title, $pres = null ) use ( $pv_log ) {
		$pv_log( 'wpseo_title', $title );
		return $title;
	},
	20,
	2
);

add_filter(
	'wpseo_metadesc',
	function ( $desc ) use ( $pv_log ) {
		$pv_log( 'wpseo_metadesc', $desc );
		return $desc;
	},
	20
);

add_filter(
	'wpseo_robots_array',
	function ( $robots ) use ( $pv_log ) {
		$pv_log( 'wpseo_robots_array', wp_json_encode( $robots ) );
		return $robots;
	},
	20
);

add_filter(
	'pre_get_document_title',
	function ( $title ) use ( $pv_log ) {
		$pv_log( 'pre_get_document_title', $title );
		return $title;
	},
	25
);
