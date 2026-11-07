<?php

defined("ABSPATH") || exit;

class PV_Schema {
	public static function init() {
		add_action("wp_head", array(__CLASS__, "output"), 5);
	}

	public static function output() {
		if (is_admin()) {
			return;
		}
		$schema = array("@context" => "https://schema.org");
		if (is_front_page()) {
			$schema["@type"] = "WebSite";
			$schema["name"] = get_bloginfo("name");
			$schema["url"] = home_url("/");
		} elseif (is_singular("video")) {
			$schema["@type"] = "VideoObject";
			$schema["name"] = get_the_title();
			$schema["description"] = wp_strip_all_tags(get_the_excerpt());
			$schema["url"] = get_permalink();
			$embed_url = get_post_meta( get_the_ID(), "_pv_embed_url", true );
			if ( $embed_url ) {
				$schema["contentUrl"] = $embed_url;
				$schema["embedUrl"]   = $embed_url;
			}
			if ( $thumb = get_the_post_thumbnail_url() ) {
				$schema["thumbnail"] = array(
					"@type" => "ImageObject",
					"url"   => $thumb,
				);
			}
			$schema["datePublished"] = get_post_time( "c", true );
			$schema["inLanguage"]    = get_bloginfo( "language" );
			$schema["publisher"] = array(
				"@type" => "Organization",
				"@id"   => home_url( "/#organization" ),
				"name"  => get_bloginfo( "name" ),
			);
		} elseif (is_singular("stanza")) {
			$schema["@type"] = "ItemPage";
			$schema["name"] = get_the_title();
			$schema["description"] = wp_strip_all_tags(get_the_excerpt());
			$schema["url"] = get_permalink();
		} else {
			return;
		}
		echo "<script type=\"application/ld+json\">" . wp_json_encode($schema) . "</script>\n";
	}
}

PV_Schema::init();
