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
