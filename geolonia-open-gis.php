<?php
/**
 * Plugin Name:     Geolonia Open GIS
 * Plugin URI:      https://geolonia.com/
 * Description:     Open GIS for WordPress
 * Author:          Geolonia Inc.
 * Author URI:      https://geolonia.com/
 * Text Domain:     geolonia-open-gis
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Geolonia_Open_GIS
 */

// Your code starts here.

// TODO：公開画面でテーマ図の切り替えができるPOCを作成する
// TODO：公開画面でメニューの表示をするPOCの作成
// TODO：管理画面でテーマ図の設定ができるPOCを作成
// TODO：管理画面で以下UIを実装する
//   https://www.figma.com/design/RcLd9GdV4BxCRgBhHCPMMC/%E7%AE%A1%E7%90%86%E7%94%BB%E9%9D%A2?node-id=771-3304&t=U30wPN5NxFYkZmOU-0

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Invalid request.' );
}

define( 'GEOLONIA_GIS_POST_TYPE', 'map' );

if ( ! defined( 'GEOLONIA_API_KEY' ) ) {
	define( 'GEOLONIA_API_KEY', 'YOUR-API-KEY' );
}

if ( ! defined( 'GEOLONIA_GIS_DEFAULT_STYLE' ) ) {
	define( 'GEOLONIA_GIS_DEFAULT_STYLE', apply_filters( 'geolonia-open-gis-default-style', plugins_url( '/styles/standard.json', __FILE__ ) ) );
}

if ( ! defined( 'GEOLONIA_GIS_DEFAULT_ZOOM' ) ) {
	define( 'GEOLONIA_GIS_DEFAULT_ZOOM', 16 );
}

if ( ! defined( 'GEOLONIA_GIS_DEFAULT_LAT' ) ) {
	define( 'GEOLONIA_GIS_DEFAULT_LAT', 35.67737939162146 );
}

if ( ! defined( 'GEOLONIA_GIS_DEFAULT_LNG' ) ) {
	define( 'GEOLONIA_GIS_DEFAULT_LNG', 139.7478998426507 );
}

require_once( dirname( __FILE__) . '/inc/functions.php' );

function geolonia_gis_load_textdomain() {
	load_plugin_textdomain( 'geolonia-open-gis', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

add_action( 'plugins_loaded', 'geolonia_gis_load_textdomain');

// Registers the custom post type `maps`.
add_action( 'init', function() {
	register_post_type_maps();
	define_map_caps(); // もしかしたらいらないかも？
} );

// Disable Gutenberg on the back end.
add_filter( 'use_block_editor_for_post', function() {
	if ( GEOLONIA_GIS_POST_TYPE === get_post_type() ){
		return false;
	}
	return true;
} );

// Disable TinyMCE and Quicktags on the editor.
add_filter( 'wp_editor_settings', function( $settings, $editor_id ) {
	if ( 'content' === $editor_id && GEOLONIA_GIS_POST_TYPE === get_post_type() ){
		$settings['media_buttons'] = false;
		$settings['tinymce'] = false;
		$settings['quicktags'] = false;
	}
	return $settings;
}, 10, 2 );

// Replaces the editor with the Geolonia GIS editor.
add_filter( 'the_editor', function( $editor ) {
	if ( GEOLONIA_GIS_POST_TYPE !== get_post_type() || ! user_can_richedit() ) {
		return $editor;
	}

	$zoom = GEOLONIA_GIS_DEFAULT_ZOOM;

	if ( floatval( get_post_meta(get_the_ID(), '_geolonia-gis-zoom', true) ) > 0 ) {
		$zoom = get_post_meta(get_the_ID(), '_geolonia-gis-zoom', true);
	}

	$lat = GEOLONIA_GIS_DEFAULT_LAT;
	$lng = GEOLONIA_GIS_DEFAULT_LNG;
	$style = GEOLONIA_GIS_DEFAULT_STYLE;

	if ( is_numeric( get_post_meta(get_the_ID(), '_geolonia-gis-lat', true ) ) ) {
		$lat = get_post_meta(get_the_ID(), '_geolonia-gis-lat', true);
	}

	if ( is_numeric( get_post_meta(get_the_ID(), '_geolonia-gis-lng', true) ) ) {
		$lng = get_post_meta(get_the_ID(), '_geolonia-gis-lng', true);
	}

	if ( get_post_meta( get_the_ID(), '_geolonia-gis-style', true ) ) {
		$style = get_post_meta( get_the_ID(), '_geolonia-gis-style', true );
	}

	return '<div id="geolonia-gis-editor-container">
	<div class="editor-menu"><button type="button" data-editor="map" class="active">MAP</button><button type="button" data-editor="geojson" class="inactive">GeoJSON</button></div>
	<div class="editor-container">
	<div id="geolonia-map-editor"
		data-style="' . esc_attr( $style ) . '"
		data-zoom="' . esc_attr( $zoom ) . '"
		data-lat="' . esc_attr( $lat ) . '"
		data-lng="' . esc_attr( $lng ) . '"
		data-marker="off"
		data-lazy-loading="off"
		data-geolocate-control="on"
		data-gesture-handling="off"
		></div>
		<div id="geojson-meta-container">
			<div class="geojson-meta"><input type="text" id="geojson-meta-title" placeholder="タイトル"></div>
			<div><div id="color-picker" acp-color="#3bb2d0" acp-show-hsl="no" acp-show-hex="no" acp-show-rgb="no"></div></div>
			<div class="close"></div>
		</div>
		<textarea id="geolonia-geojson-editor" spellcheck="false" style="display: none;"></textarea>
		<textarea id="content" name="content" style="display: none;">%s</textarea>
		<div id="geolonia-uploader"><span class="dashicons dashicons-upload">'.__('Upload GeoJSON', 'geolonia-open-gis').'</span></div>
		</div><!-- end .editor-container -->
	</div><!-- end #geolonia-gis-editor-container -->';
} );

// Registers the scripts.
add_action( 'admin_enqueue_scripts', function() {
	if ( GEOLONIA_GIS_POST_TYPE !== get_post_type() ) {
		return;
	}

	wp_enqueue_script(
		'geolonia-embed-api',
		esc_url( 'https://cdn.geolonia.com/v1/embed?geolonia-api-key=' . GEOLONIA_API_KEY ),
		array(),
		false,
		true
	);

	wp_enqueue_script(
		'mapbox-gl-draw',
		'https://cdnjs.cloudflare.com/ajax/libs/mapbox-gl-draw/1.4.3/mapbox-gl-draw.min.js',
		array(),
		false,
		true
	);

	wp_enqueue_style(
		'mapbox-gl-draw-css',
		'https://cdnjs.cloudflare.com/ajax/libs/mapbox-gl-draw/1.4.3/mapbox-gl-draw.min.css',
		array(),
		false
	);

	wp_enqueue_script(
		'geolonia-color-picker',
		'https://cdn.jsdelivr.net/npm/a-color-picker@1.2.1/dist/acolorpicker.min.js',
		array(),
		false,
		true
	);

	wp_enqueue_script(
		'geolonia-gis-draw-style',
		plugins_url( '/js/draw-style.js', __FILE__ ),
		array(),
		filemtime( plugin_dir_path( __FILE__ ) . '/js/draw-style.js' ),
		true
	);

	wp_enqueue_script(
		'geolonia-gis',
		plugins_url( '/js/admin.js', __FILE__ ),
		array( 'geolonia-embed-api', 'mapbox-gl-draw', 'geolonia-color-picker', 'geolonia-gis-draw-style' ),
		filemtime( plugin_dir_path( __FILE__ ) . '/js/admin.js' ),
		true
	);

	wp_enqueue_style(
		'geolonia-gis-css',
		plugins_url( '/css/admin.css', __FILE__ ),
		array( 'mapbox-gl-draw-css' ),
		filemtime( plugin_dir_path( __FILE__ ) . '/css/admin.css' )
	);
}, 20 );

// Registers the Geolonia Embed API on the front end.
add_action( 'wp_enqueue_scripts', function() {
	wp_enqueue_script(
		'geolonia-embed-api',
		'https://cdn.geolonia.com/v1/embed?geolonia-api-key=' . esc_html( GEOLONIA_API_KEY ),
		array(),
		false,
		true
	);
}, 10 );

// Registers the meta boxes on the admin screen.
add_action( 'add_meta_boxes', function() {
	add_meta_box(
		'geolonia-gis-meta-center',
		__( 'Coordinates', 'geolonia-open-gis' ),
		function() {
			$lat = GEOLONIA_GIS_DEFAULT_LAT;
			$lng = GEOLONIA_GIS_DEFAULT_LNG;

			if ( is_numeric( get_post_meta(get_the_ID(), '_geolonia-gis-lat', true ) ) ) {
				$lat = get_post_meta(get_the_ID(), '_geolonia-gis-lat', true);
			}

			if ( is_numeric( get_post_meta(get_the_ID(), '_geolonia-gis-lng', true) ) ) {
				$lng = get_post_meta(get_the_ID(), '_geolonia-gis-lng', true);
			}

			wp_nonce_field( 'geolonia-gis-nonce-latlng', 'geolonia-gis-nonce-latlng' );
			?>
				<p><input type="text" id="geolonia-gis-lat" name="geolonia-gis-lat" class="geolonia-meta" value="<?php echo esc_attr($lat) ?>"></p>
				<p><input type="text" id="geolonia-gis-lng" name="geolonia-gis-lng" class="geolonia-meta" value="<?php echo esc_attr($lng) ?>"></p>
				<p><button type="button" id="geolonia-get-latlng-button" class="geolonia-meta button"><?php echo __( 'Use the current coordinates', 'geolonia-open-gis' ); ?></button></p>
			<?php
		},
		GEOLONIA_GIS_POST_TYPE,
		'side'
	);

	add_meta_box(
		'geolonia-gis-meta-zoom',
		__( 'Zoom', 'geolonia-open-gis' ),
		function() {
			$zoom = GEOLONIA_GIS_DEFAULT_ZOOM;

			if ( floatval( get_post_meta(get_the_ID(), '_geolonia-gis-zoom', true) ) > 0 ) {
				$zoom = get_post_meta(get_the_ID(), '_geolonia-gis-zoom', true);
			}

			wp_nonce_field( 'geolonia-gis-nonce-zoom', 'geolonia-gis-nonce-zoom' );
			?>
				<p><input type="text" id="geolonia-gis-zoom" name="geolonia-gis-zoom" class="geolonia-meta" value="<?php echo esc_attr($zoom) ?>"></p>
				<p><button type="button" id="geolonia-get-zoom-button" class="geolonia-meta button"><?php echo __( 'Use the current zoom level', 'geolonia-open-gis' ); ?></button></p>
			<?php
		},
		GEOLONIA_GIS_POST_TYPE,
		'side'
	);

	add_meta_box(
		'geolonia-gis-meta-style',
		__( 'Style', 'geolonia-open-gis' ),
		function() {
			$current_style = GEOLONIA_GIS_DEFAULT_STYLE;

			if ( get_post_meta( get_the_ID(), '_geolonia-gis-style', true ) ) {
				$current_style = get_post_meta( get_the_ID(), '_geolonia-gis-style', true );
			}

			wp_nonce_field( 'geolonia-gis-nonce-style', 'geolonia-gis-nonce-style' );
			?>
			<div class="select-geolonia-styles">
				<input id="geolonia-gis-style" name="geolonia-gis-style" list="styles" class="geolonia-meta" value="<?php echo esc_url($current_style) ?>" autocomplete="off" />
				<ul id="geolonia-style-data-list">
				<?php
					$default_styles = apply_filters( 'geolonia-open-gis-styles', array(
						array(
							'name' => __( 'Standard', 'geolonia-open-gis' ),
							'image' => plugins_url( '/styles/standard.png', __FILE__ ),
							'url' => plugins_url( '/styles/standard.json', __FILE__ ),
						),
						array(
							'name' => __( 'GSI', 'geolonia-open-gis' ),
							'image' => plugins_url( '/styles/gsi-vector.png', __FILE__ ),
							'url' => plugins_url( '/styles/gsi-vector.json', __FILE__ ),
						),
						array(
							'name' => __( 'Blank Map', 'geolonia-open-gis' ),
							'image' => plugins_url( '/styles/blank-map.png', __FILE__ ),
							'url' => plugins_url( '/styles/blank-map.json', __FILE__ ),
						),
					) );



					foreach ( $default_styles as $style ) {
						if ($current_style === $style['url']) {
							echo '<li class="active" data-url="' . esc_url( $style['url'] ) . '"><img src="' . esc_url( $style['image'] ) . '"><div class="style-name">' . esc_html( $style['name'] ) . '</div></li>';
						} else {
							echo '<li data-url="' . esc_url( $style['url'] ) . '"><img src="' . esc_url( $style['image'] ) . '"><div class="style-name">' . esc_html( $style['name'] ) . '</div></li>';
						}
					}
				?>
				</ul>
			</div>
			<?php
		},
		GEOLONIA_GIS_POST_TYPE,
		'side'
	);
} );

// Saves the latlng and the zoom as post meta.
add_action( 'save_post', function( $post_id ) {

    // verify post is not a revision
    if ( ! wp_is_post_revision( $post_id ) ) {

		if ( isset( $_POST['geolonia-gis-nonce-latlng'] ) ) {
			$nonce = $_POST['geolonia-gis-nonce-latlng'];

			if ( wp_verify_nonce( $nonce, 'geolonia-gis-nonce-latlng' ) && isset( $_POST['geolonia-gis-lat'] ) && isset( $_POST['geolonia-gis-lng'] ) ) {
				if ( is_numeric( $_POST['geolonia-gis-lat'] ) && is_numeric( $_POST['geolonia-gis-lng'] ) ) {
					update_post_meta( $post_id, '_geolonia-gis-lat', $_POST['geolonia-gis-lat'] );
					update_post_meta( $post_id, '_geolonia-gis-lng', $_POST['geolonia-gis-lng'] );
				}
			}
		}

		if ( isset( $_POST['geolonia-gis-nonce-zoom'] ) ) {
			$nonce = $_POST['geolonia-gis-nonce-zoom'];

			if ( wp_verify_nonce( $nonce, 'geolonia-gis-nonce-zoom' ) && isset( $_POST['geolonia-gis-zoom'] ) ) {
				update_post_meta( $post_id, '_geolonia-gis-zoom', floatval($_POST['geolonia-gis-zoom']) );
			}
		}

		if ( isset( $_POST['geolonia-gis-nonce-style'] ) ) {
			$nonce = $_POST['geolonia-gis-nonce-style'];

			if ( wp_verify_nonce( $nonce, 'geolonia-gis-nonce-style' ) && isset( $_POST['geolonia-gis-style'] ) ) {
				update_post_meta( $post_id, '_geolonia-gis-style', trim( $_POST['geolonia-gis-style'] ) );
			}
		}
    }

	return $post_id;
}, 10 );

// Filters the content and replaces it with the Geolonia GIS map.
add_filter( 'the_content',	function( $content ) {
	if ( GEOLONIA_GIS_POST_TYPE === get_post_type() && ! post_password_required( get_the_ID() ) ){
		$zoom = GEOLONIA_GIS_DEFAULT_ZOOM;

		if ( floatval( get_post_meta(get_the_ID(), '_geolonia-gis-zoom', true) ) > 0 ) {
			$zoom = get_post_meta(get_the_ID(), '_geolonia-gis-zoom', true);
		}

		$lat = GEOLONIA_GIS_DEFAULT_LAT;
		$lng = GEOLONIA_GIS_DEFAULT_LNG;
		$style = GEOLONIA_GIS_DEFAULT_STYLE;

		if ( is_numeric( get_post_meta(get_the_ID(), '_geolonia-gis-lat', true ) ) ) {
			$lat = get_post_meta(get_the_ID(), '_geolonia-gis-lat', true);
		}

		if ( is_numeric( get_post_meta(get_the_ID(), '_geolonia-gis-lng', true) ) ) {
			$lng = get_post_meta(get_the_ID(), '_geolonia-gis-lng', true);
		}

		if ( get_post_meta( get_the_ID(), '_geolonia-gis-style', true ) ) {
			$style = get_post_meta( get_the_ID(), '_geolonia-gis-style', true );
		}

		$content = sprintf( '<script id="geojson-%s" type="application/json">%s</script>',
			esc_attr( get_the_ID() ),
			json_encode( json_decode( get_the_content() ) ),
		);
	}

	return $content;
} );

// Filters the REST API response and replaces the content with the GeoJSON.
add_filter( 'rest_prepare_maps', function( $response, $post, $request ) {

	if ( ! post_password_required( $post ) && GEOLONIA_GIS_POST_TYPE === get_post_type() ){
		$response->data['content']['rendered'] = json_decode( $post->post_content );
	}

	return $response;

}, 10, 3 );

add_filter( 'gettext', function( $translation, $text, $domain ) {
	// 投稿画面の「文字数」を 「Count」（地物の数）に変更
	if ( GEOLONIA_GIS_POST_TYPE === get_post_type() ) {
		if ( 'Word count: %s' === $text ) {
			$translation = __( 'Number of features: <span id="num-of-features"></span>', 'geolonia-open-gis' );
		}
	}

	return $translation;
}, 10, 3 );
