<?php
/**
 * Bridge theme bootstrap.
 *
 * @package Bridge
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BRIDGE_VERSION', '1.0.0' );
define( 'BRIDGE_DIST_URI', get_theme_file_uri( 'dist' ) );
define( 'BRIDGE_DIST_PATH', get_theme_file_path( 'dist' ) );

/**
 * Theme setup: declare feature support and register editor styling.
 */
function bridge_setup(): void {
	load_theme_textdomain( 'bridge', get_template_directory() . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'html5', array(
		'search-form',
		'comment-form',
		'comment-list',
		'gallery',
		'caption',
		'style',
		'script',
	) );

	add_editor_style( 'dist/main.css' );
}
add_action( 'after_setup_theme', 'bridge_setup' );

/**
 * Enqueue compiled frontend assets from the Vite /dist folder.
 */
function bridge_enqueue_assets(): void {
	$css_path = BRIDGE_DIST_PATH . '/main.css';
	$js_path  = BRIDGE_DIST_PATH . '/main.js';

	if ( file_exists( $css_path ) ) {
		wp_enqueue_style(
			'bridge-style',
			BRIDGE_DIST_URI . '/main.css',
			array(),
			(string) filemtime( $css_path )
		);
	}

	if ( file_exists( $js_path ) ) {
		wp_enqueue_script(
			'bridge-script',
			BRIDGE_DIST_URI . '/main.js',
			array(),
			(string) filemtime( $js_path ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}
}
add_action( 'wp_enqueue_scripts', 'bridge_enqueue_assets' );

/**
 * Register block-pattern category so custom patterns group cleanly in the inserter.
 */
function bridge_register_pattern_categories(): void {
	if ( function_exists( 'register_block_pattern_category' ) ) {
		register_block_pattern_category(
			'bridge',
			array( 'label' => __( 'Bridge', 'bridge' ) )
		);
	}
}
add_action( 'init', 'bridge_register_pattern_categories' );

/**
 * Register custom blocks and their compiled Vite assets.
 *
 * Script and style handles are registered first so that the handles
 * referenced in each block's block.json (editorScript, viewScript, viewStyle)
 * resolve to real, versioned files in /dist.
 */
function bridge_register_blocks(): void {
	$blocks_root = get_theme_file_path( 'src/blocks' );

	// --- Hero Slider -------------------------------------------------------
	$editor_js = BRIDGE_DIST_PATH . '/hero-slider-editor.js';
	if ( file_exists( $editor_js ) ) {
		wp_register_script(
			'bridge-hero-slider-editor',
			BRIDGE_DIST_URI . '/hero-slider-editor.js',
			array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-i18n', 'wp-components' ),
			(string) filemtime( $editor_js ),
			true
		);
	}

	$view_js = BRIDGE_DIST_PATH . '/slider.js';
	if ( file_exists( $view_js ) ) {
		wp_register_script(
			'bridge-hero-slider-view',
			BRIDGE_DIST_URI . '/slider.js',
			array(),
			(string) filemtime( $view_js ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	$view_css = BRIDGE_DIST_PATH . '/slider.css';
	if ( file_exists( $view_css ) ) {
		wp_register_style(
			'bridge-hero-slider-style',
			BRIDGE_DIST_URI . '/slider.css',
			array(),
			(string) filemtime( $view_css )
		);
	}

	$hero_slider_dir = $blocks_root . '/hero-slider';
	if ( is_dir( $hero_slider_dir ) && file_exists( $hero_slider_dir . '/block.json' ) ) {
		register_block_type( $hero_slider_dir );
	}

	// --- Cards -------------------------------------------------------------
	$cards_editor_js = BRIDGE_DIST_PATH . '/cards-editor.js';
	if ( file_exists( $cards_editor_js ) ) {
		wp_register_script(
			'bridge-cards-editor',
			BRIDGE_DIST_URI . '/cards-editor.js',
			array(
				'wp-blocks',
				'wp-block-editor',
				'wp-element',
				'wp-i18n',
				'wp-components',
				'wp-data',
				'wp-server-side-render',
			),
			(string) filemtime( $cards_editor_js ),
			true
		);
	}

	$cards_dir = $blocks_root . '/cards';
	if ( is_dir( $cards_dir ) && file_exists( $cards_dir . '/block.json' ) ) {
		register_block_type( $cards_dir );
	}
}
add_action( 'init', 'bridge_register_blocks' );

/**
 * Preload the hero-slider CSS when the current request actually renders
 * the block. The browser starts fetching it in parallel with the HTML
 * parser, removing it from the render-blocking critical path.
 */
function bridge_preload_hero_slider_css(): void {
	if ( is_admin() || is_feed() || is_embed() ) {
		return;
	}

	if ( ! has_block( 'bridge/hero-slider' ) ) {
		return;
	}

	$css_path = BRIDGE_DIST_PATH . '/slider.css';
	if ( ! file_exists( $css_path ) ) {
		return;
	}

	printf(
		'<link rel="preload" href="%s" as="style" />' . "\n",
		esc_url( BRIDGE_DIST_URI . '/slider.css?ver=' . filemtime( $css_path ) )
	);
}
add_action( 'wp_head', 'bridge_preload_hero_slider_css', 1 );

/**
 * Enqueue the landing-mode editor watcher on Page edit screens only.
 *
 * Subscribes to template changes and, when "Landing Page" is selected,
 * hides the post-title field and auto-inserts a Hero Slider with the
 * page title seeded into the first slide's H1.
 */
function bridge_enqueue_editor_watcher(): void {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'page' !== $screen->post_type ) {
		return;
	}

	$path = BRIDGE_DIST_PATH . '/template-watcher.js';
	if ( ! file_exists( $path ) ) {
		return;
	}

	wp_enqueue_script(
		'bridge-template-watcher',
		BRIDGE_DIST_URI . '/template-watcher.js',
		array( 'wp-data', 'wp-blocks', 'bridge-hero-slider-editor' ),
		(string) filemtime( $path ),
		true
	);
}
add_action( 'enqueue_block_editor_assets', 'bridge_enqueue_editor_watcher' );
