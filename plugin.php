<?php
/**
 * Plugin Name
 *
 * @package           Thumbwiz
 * @author            Chris McCoy
 * @copyright         2023 Chris McCoy
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name: Thumbwiz
 * Plugin URI: https://github.com/chrismccoy/thumbwiz
 * Description: Makes Video Thumbnails Right in the Browser.
 * Version: 0.9
 * Author: Chris McCoy
 * Author URI: https://github.com/chrismccoy
 * Text Domain: thumbwiz
 * Domain Path: /languages
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( "Can't load this file directly" );
} elseif ( ! defined( 'THUMBWIZ_VERSION' ) ) {
	define( 'THUMBWIZ_VERSION', '0.9' );
}

require_once trailingslashit(plugin_dir_path(__FILE__)) . 'inc/updater.php';
require_once trailingslashit(plugin_dir_path(__FILE__)) . 'inc/class.settings-api.php';
require_once trailingslashit(plugin_dir_path(__FILE__)) . 'inc/thumbwiz-settings.php';

new Thumbwiz_Settings();

function thumbwiz_get_options() {
	$options = array(
		'generate_thumbs'    => thumbwiz_get_thumbs_option(),
		'featured'           => true,
		'thumb_parent'       => 'video',
		'poster'             => '',
		'browser_thumbnails' => true,
	);
	return $options;
}

function thumbwiz_get_attachment_meta_defaults() {
	$options        = thumbwiz_get_options();
	$meta_key_array = array(
		'actualwidth'     => '',
		'actualheight'    => '',
		'numberofthumbs'  => $options['generate_thumbs'],
		'randomize'       => '',
		'featured'        => $options['featured'],
		'thumbtime'       => '',
		'duration'        => '',
		'aspect'          => '',
		'featuredchanged' => 'false',
		'url'             => '',
		'poster'          => '',
		'maxwidth'        => '',
		'maxheight'       => '',
		'width'           => '',
		'height'          => '',
		'autothumb-error' => '',
	);
	return $meta_key_array;
}

function thumbwiz_get_attachment_meta( $post_id ) {
	$thumbwiz_postmeta = get_post_meta( $post_id, '_thumbwiz', true );
	$meta_key_array    = thumbwiz_get_attachment_meta_defaults();

	if ( empty( $thumbwiz_postmeta ) ) {
		$thumbwiz_postmeta = array();
	}

	$thumbwiz_postmeta = array_merge( $meta_key_array, $thumbwiz_postmeta ); // make sure all keys are set
	return apply_filters( 'thumbwiz_attachment_meta', $thumbwiz_postmeta );
}

function thumbwiz_save_attachment_meta( $post_id, $thumbwiz_postmeta ) {

	if ( is_array( $thumbwiz_postmeta ) ) {

		$options               = thumbwiz_get_options();
		$thumbwiz_old_postmeta = thumbwiz_get_attachment_meta( $post_id );
		$thumbwiz_postmeta     = array_merge( $thumbwiz_old_postmeta, $thumbwiz_postmeta ); // make sure all keys are saved

		foreach ( $thumbwiz_postmeta as $key => $meta ) { // don't save if it's the same as the default values or empty

			if ( ( array_key_exists( $key, $options )
					&& $meta == $options[ $key ]
				)
				|| ( ! is_array( $thumbwiz_postmeta[ $key ] )
					&& strlen( $thumbwiz_postmeta[ $key ] ) === 0
					&& (
						( array_key_exists( $key, $options )
							&& strlen( $options[ $key ] ) === 0
						)
						|| ! array_key_exists( $key, $options )
					)
				)
			) {
				unset( $thumbwiz_postmeta[ $key ] );
			}
		}
		update_post_meta( $post_id, '_thumbwiz', $thumbwiz_postmeta );
	}
}

function thumbwiz_filter_validate_url( $uri ) {
	// multibyte compatible check if string is a URL.

	$res = filter_var( $uri, FILTER_VALIDATE_URL );

	if ( $res ) {
		return true;
	}
	// Check if it has unicode chars.
	$l = mb_strlen( $uri );

	if ( $l !== strlen( $uri ) ) {

		// Replace wide chars by “X”.
		$s = str_repeat( ' ', $l );
		for ( $i = 0; $i < $l; ++$i ) {
			$ch       = mb_substr( $uri, $i, 1 );
			$s [ $i ] = strlen( $ch ) > 1 ? 'X' : $ch;
		}
		// Re-check now.
		$res = filter_var( $s, FILTER_VALIDATE_URL );
		if ( $res ) {
			$uri = $res;
			return true;
		}
	}
	return false;
}

function thumbwiz_sanitize_text_field( $text_field ) {
	// recursively sanitizes user input.
	$old_field = $text_field;

	if ( is_array( $text_field ) ) {

		foreach ( $text_field as $key => &$value ) {
			if ( is_array( $value ) ) {
				$value = thumbwiz_sanitize_text_field( $value );
			} elseif ( thumbwiz_filter_validate_url( $value ) ) { // if it's a URL.
				$value = sanitize_url( $value );
			} else {
				$value = sanitize_text_field( $value );
			}
		}
	} elseif ( thumbwiz_filter_validate_url( $text_field ) ) { // not an array, is a URL
			$text_field = sanitize_url( $text_field );
	} else {
		$text_field = sanitize_text_field( $text_field );
	}
	return $text_field;
}

function thumbwiz_sanitize_url( $movieurl ) {
	$sanitized_url = array();

	$decoded_movieurl = rawurldecode( $movieurl );
	$parsed_url       = wp_parse_url( $decoded_movieurl, PHP_URL_PATH );
	$path_info        = pathinfo( $parsed_url );

	if ( empty( $path_info['extension'] ) ) {
		$sanitized_url['noextension'] = $movieurl;
		$sanitized_url['basename']    = substr( $movieurl, -20 );
	} else {
		$no_extension_url   = preg_replace( '/\\.[^.\\s]{3,4}$/', '', $decoded_movieurl );
		$sanitized_basename = sanitize_file_name( $path_info['basename'] );

		$sanitized_url['noextension'] = $no_extension_url;
		$sanitized_url['basename']    = str_replace( '.' . $path_info['extension'], '', $sanitized_basename );
	}

	$sanitized_url['singleurl_id'] = 'singleurl_' . preg_replace( '/[^a-zA-Z0-9]/', '_', $sanitized_url['basename'] );
	$sanitized_url['movieurl']     = esc_url_raw( str_replace( ' ', '%20', $decoded_movieurl ) );
	return $sanitized_url;
}

function thumbwiz_is_video( $post ) {

	if ( is_object( $post ) && property_exists( $post, 'post_mime_type' ) ) {
		// Check if 'post_mime_type' starts with 'video'
		if ( strpos( $post->post_mime_type, 'video' ) === 0 ) {
			// Check if either no parent post exists or parent post is not a video
			if ( empty( $post->post_parent ) || strpos( get_post_mime_type( $post->post_parent ), 'video' ) === false ) {
				return true;
			}
		}
	}
	return false;
}

function enqueue_thumbwiz_script() {
	// loads plugin-related scripts in the admin area

	if ( ! wp_script_is( 'thumbwiz', 'enqueued' ) ) {

		wp_enqueue_script( 'thumbwiz', plugins_url( '/assets/js/thumbwiz.js', __FILE__ ), array( 'jquery' ), THUMBWIZ_VERSION, true );
		wp_enqueue_style( 'thumbwiz_styles', plugins_url( '/assets/css/thumbwiz.css', __FILE__ ), '', THUMBWIZ_VERSION );

		wp_localize_script(
			'thumbwiz',
			'thumbwiz_L10n',
			array(
				'hidevideo'       => esc_html__( 'Hide video...', 'thumbwiz' ),
				'choosefromvideo' => esc_html__( 'Choose from video...', 'thumbwiz' ),
				'cantloadvideo'   => esc_html__( "Can't load video", 'thumbwiz' ),
				'choosethumbnail' => esc_html__( 'Choose Thumbnail:', 'thumbwiz' ),
				'saving'          => esc_html__( 'Saving...', 'thumbwiz' ),
				'write_error'     => esc_html__( 'Error: Unable to save thumbnail in Media Library folder. Check uploads folder permissions.' ),
				'generate_thumbs'  => thumbwiz_get_thumbs_option(),
			)
		);
	}//end if
}

add_action( 'wp_enqueue_media', 'enqueue_thumbwiz_script' ); // always enqueue scripts if media elements are loaded

function thumbwiz_fields_to_edit( $form_fields, $post ) {
	$options  = thumbwiz_get_options();
	$is_video = thumbwiz_is_video( $post );

	if ( $is_video ) {

		wp_enqueue_media(); // allows using the media modal in the Media Library
		wp_enqueue_script( 'thumbwiz' );
		wp_enqueue_style( 'thumbwiz_styles' );

		$user_ID           = get_current_user_id();
		$movieurl          = wp_get_attachment_url( $post->ID );
		$thumbwiz_postmeta = thumbwiz_get_attachment_meta( $post->ID );

		if ( $user_ID === $post->post_author
			|| current_user_can( 'edit_others_posts' )
		) {
			$readonly          = '';
			$security_disabled = '';
		} else {
			$readonly          = ' readonly';
			$security_disabled = ' disabled';
		}

		$form_fields['thumbwiz-url']['input'] = 'hidden';
		$form_fields['thumbwiz-url']['value'] = esc_url( $movieurl );

		$video_aspect = null;
		$video_meta   = wp_get_attachment_metadata( $post->ID );
		if ( is_array( $video_meta )
			&& array_key_exists( 'width', $video_meta )
			&& array_key_exists( 'height', $video_meta )
		) {
			$video_aspect = $video_meta['height'] / $video_meta['width'];
		}
		if ( ! empty( $thumbwiz_postmeta['width'] )
			&& ! empty( $thumbwiz_postmeta['height'] )
		) {
			if ( empty( $video_aspect ) ) {
				$video_aspect = $thumbwiz_postmeta['height'] / $thumbwiz_postmeta['width'];
			}
		}

		$form_fields['thumbwiz-aspect']['input'] = 'hidden';
		$form_fields['thumbwiz-aspect']['value'] = esc_attr( round( $video_aspect, 5 ) );

		$nonce = wp_create_nonce( 'thumbwiz-nonce' );
		$form_fields['thumbwiz-security']['input'] = 'hidden';
		$form_fields['thumbwiz-security']['value'] = $nonce;

		// ** Thumbnail section **//

		$thumbnail_url = get_the_post_thumbnail_url( $post->ID );
		$thumbnail_id  = get_post_thumbnail_id( $post->ID );
		if ( is_ssl() ) {
			$thumbnail_url = str_replace( 'http:', 'https:', $thumbnail_url );
		}

		$thumbnail_html = '';
		if ( ! empty( $thumbwiz_postmeta['autothumb-error'] ) && empty( $thumbnail_url ) ) {
			$thumbnail_html = '<div class="thumbwiz_thumbnail_box thumbwiz_chosen_thumbnail_box">' . wp_kses_post( $thumbwiz_postmeta['autothumb-error'] ) . '</div>';
		} elseif ( ! empty( $thumbnail_url ) ) {
			$thumbnail_html = '<div class="thumbwiz_thumbnail_box thumbwiz_chosen_thumbnail_box"><img id="thumbnail-' . esc_attr( $post->ID ) . '" width="200" data-thumb_id="' . esc_attr( $thumbnail_id ) . '" data-featuredchanged="' . esc_attr( $thumbwiz_postmeta['featuredchanged'] ) . '" src="' . esc_attr( $thumbnail_url ) . '?' . rand() . '"></div>';
		} elseif ( empty( $thumbnail_url ) ) {
			$thumbwiz_postmeta['thumbtime'] = '';
			$thumbwiz_postmeta['numberofthumbs'] = $options['generate_thumbs'];
		}

		$choose_from_video_content = '';
		$generate_content          = '';
		$thumbnail_timecode        = '';
		$update_script             = '';

		if ( empty( $security_disabled ) && current_user_can( 'upload_files' ) ) {

			if ( ! empty( $thumbwiz_postmeta['thumbtime'] ) ) {
				$thumbwiz_postmeta['numberofthumbs'] = '1';
			}

			$args             = array(
				'mime_type' => 'image/jpeg',
				'methods'   => array(
					'save',
				),
			);
			$img_editor_works = wp_image_editor_supports( $args );

			$moviefiletype = pathinfo( $movieurl, PATHINFO_EXTENSION );
			if ( $moviefiletype == 'mov' || $moviefiletype == 'm4v' ) {
				$moviefiletype = 'mp4';
			}

			if ( $img_editor_works ) {
				$choose_from_video_content = '<div class="thumbwiz_thumbnail_box thumbwiz-tabs-content" id="thumb-video-' . esc_attr( $post->ID ) . '-container">
					<div class="thumbwiz-reveal-thumb-video" onclick="thumbwiz_reveal_thumb_video(' . esc_attr( $post->ID ) . ')" id="show-thumb-video-' . esc_attr( $post->ID ) . '"><span class="thumbwiz-right-arrow"></span><span class="thumbwiz-show-video">' . esc_html__( 'Choose from video...', 'thumbwiz' ) . '</span></div>
					<div style="display:none;" id="thumb-video-' . esc_attr( $post->ID ) . '-player">
						<video playsinline crossorigin="anonymous" muted preload="none" class="thumbwiz-thumb-video" width="200" data-allowed="' . esc_attr( $options['browser_thumbnails'] ) . '" onloadedmetadata="thumbwiz_thumb_video_loaded(\'' . esc_attr( $post->ID ) . '\');" id="thumb-video-' . esc_attr( $post->ID ) . '">
						<source src="' . esc_attr( $movieurl ) . '" type="' . esc_attr( 'video/' . $moviefiletype ) . '">
						</video>
						<div class="thumbwiz-video-controls" tabindex="0">
							<div class="thumbwiz-play-pause"></div>
							<div class="thumbwiz-seek-bar">
								<div class="thumbwiz-play-progress"></div>
								<div class="thumbwiz-seek-handle"></div></div>
						</div>
						<span id="manual-thumbnail" class="button" onclick="thumbwiz_thumb_video_manual(' . esc_attr( $post->ID ) . ');">Use this frame</span>
					</div>
				</div>';
			} else {
				$choose_from_video_content = '<div class="thumbwiz_thumbnail_box">Thumbnail selection requires GD or Imagick PHP libraries.</div>';
			}
			$generate_content = '<div id="generate-thumb-' . esc_attr( $post->ID ) . '-container" class="thumbwiz-tabs-content"><input id="attachments-' . esc_attr( $post->ID ) . '-thumbwiz-numberofthumbs" name="attachments[' . esc_attr( $post->ID ) . '][thumbwiz-numberofthumbs]" type="text" value="' . esc_attr( $thumbwiz_postmeta['numberofthumbs'] ) . '" maxlength="2" style="width:35px;text-align:center;" onchange="document.getElementById(\'attachments-' . esc_attr( $post->ID ) . '-thumbwiz-thumbtime\').value =\'\';" ' . esc_attr( $readonly ) . '/>
			<input type="button" id="attachments-' . esc_attr( $post->ID ) . '-thumbgenerate" class="button" value="' . esc_attr_x( 'Generate', 'Button text. Implied "Generate thumbnails"', 'thumbwiz' ) . '" name="thumbgenerate" onclick="thumbwiz_generate_thumb(' . esc_attr( $post->ID ) . ', \'generate\');" />
			<input type="button" id="attachments-' . esc_attr( $post->ID ) . '-thumbrandomize" class="button" value="' . esc_attr_x( 'Randomize', 'Button text. Implied "Randomize thumbnail generation"', 'thumbwiz' ) . '" name="thumbrandomize" onclick="thumbwiz_generate_thumb(' . esc_attr( $post->ID ) . ', \'random\');" />
			<input type="button" id="attachments-' . esc_attr( $post->ID ) . '-forcefirst" class="button" value="' . esc_attr__( 'First frame', 'thumbwiz' ) . '" name="forcefirst" onclick="thumbwiz_first_frame(' . esc_attr( $post->ID ) . ');" />
			</div>';

			$thumbnail_timecode = esc_html__( 'Thumbnail timecode:', 'thumbwiz' ) . ' <input name="attachments[' . esc_attr( $post->ID ) . '][thumbwiz-thumbtime]" id="attachments-' . esc_attr( $post->ID ) . '-thumbwiz-thumbtime" type="text" value="' . esc_attr( $thumbwiz_postmeta['thumbtime'] ) . '" class="thumbwiz-thumbtime"' . esc_attr( $readonly ) . '><br>';

		}

		$form_fields['thumbwiz-autothumb-error']['input'] = 'hidden';
		$form_fields['thumbwiz-autothumb-error']['value'] = esc_attr( $thumbwiz_postmeta['autothumb-error'] );

		$form_fields['generator']['label'] = esc_html_x( 'Thumbnails', 'Header for thumbnail section', 'thumbwiz' );
		$form_fields['generator']['input'] = 'html';
		$form_fields['generator']['html']  = $choose_from_video_content . '
		' . $generate_content . '
		' . $thumbnail_timecode . '
		<div id="attachments-' . esc_attr( $post->ID ) . '-thumbnailplaceholder" style="position:relative;">' . $thumbnail_html . '</div>';
		if ( empty( $security_disabled ) ) {
			$form_fields['generator']['html'] .= '<span id="pick-thumbnail" class="button" style="margin:10px 0;" data-choose="' . esc_attr__( 'Choose a Thumbnail', 'thumbwiz' ) . '" data-update="' . esc_attr__( 'Set as video thumbnail', 'thumbwiz' ) . '" data-change="attachments-' . esc_attr( $post->ID ) . '-thumbwiz-poster" onclick="thumbwiz_pick_image(this);">' . esc_html__( 'Choose from Library', 'thumbwiz' ) . '</span><br />
			<input type="checkbox" id="attachments-' . esc_attr( $post->ID ) . '-featured" name="attachments[' . esc_attr( $post->ID ) . '][thumbwiz-featured]" ' . checked( $thumbwiz_postmeta['featured'], true, false ) . '/> <label for="attachments-' . esc_attr( $post->ID ) . '-featured">' . esc_html__( 'Set thumbnail as featured image', 'thumbwiz' ) . '</label>';
		}

		$form_fields['generator']['html'] .= $update_script;

		if ( empty( $security_disabled ) ) {
			$form_fields['thumbwiz-poster']['label'] = esc_html__( 'Thumbnail URL', 'thumbwiz' );
			$form_fields['thumbwiz-poster']['value'] = esc_url( $thumbnail_url );
			/* translators: %s is an <a> tag */
		}
	} //only add fields if attachment is the right kind of video
	return $form_fields;
}

add_filter( 'attachment_fields_to_edit', 'thumbwiz_fields_to_edit', 10, 2 );

function thumbwiz_video_attachment_fields_to_save( $post, $attachment ) {
	// $attachment part of the form $_POST ($_POST[attachments][postID])
	// $post attachments wp post array - will be saved after returned
	// $post['post_type'] == 'attachment'
	static $flag = 0;

	if ( ! empty( $post['ID'] )
		&& isset( $attachment['thumbwiz-url'] )
		&& $flag < 1
	) {
		$attachment = thumbwiz_sanitize_text_field( $attachment );

		$thumb_id = '';
		if ( isset( $attachment['thumbwiz-poster'] ) ) {

			$thumb_url = $attachment['thumbwiz-poster'];

			if ( ! empty( $thumb_url ) ) {
				$thumb_info = thumbwiz_save_thumb( $post['ID'], $post['post_title'], $thumb_url );
				$thumb_id   = $thumb_info['thumb_id'];
				$thumb_url  = $thumb_info['thumb_url'];
			}

			if ( empty( $thumb_url ) ) {
				delete_post_thumbnail( $post['ID'] );
			} elseif ( empty( $thumb_id ) ) { // we're not saving a new thumbnail
				$poster_id = get_post_thumbnail_id( $post['ID'] );
				if ( empty( $poster_id ) ) { // the featured image was accidentally deleted
					$thumb_url_id = attachment_url_to_postid( $thumb_url );
					if ( $thumb_url_id ) {
						set_post_thumbnail( $post['ID'], $thumb_url_id );
					}
				}
			}
		}

		if ( isset( $attachment['thumbwiz-featured'] ) ) {

			$attachment['thumbwiz-featuredchanged'] = 'false';
			if ( ! empty( $thumb_id ) ) {

				if ( isset( $_POST['action'] )
					&& $_POST['action'] === 'save-attachment-compat'
					&& isset( $_POST['post_id'] )
					&& isset( $_REQUEST['id'] )
				) { // if this is in the media modal
					$id = absint( $_REQUEST['id'] );
					check_ajax_referer( 'update-post_' . $id, 'nonce' );
					$post_parent = thumbwiz_sanitize_text_field( wp_unslash( $_POST['post_id'] ) );
				} elseif ( is_array( $post )
					&& array_key_exists( 'post_ID', $post )
				) {
					$post_parent = wp_get_post_parent_id( $post['post_ID'] );
				}

				if ( isset( $post_parent )
					&& ! empty( $post_parent )
				) {
					set_post_thumbnail( $post_parent, $thumb_id );
					$attachment['thumbwiz-featuredchanged'] = 'true';
				}
			}
		}

		if ( ! empty( $thumb_id ) ) { // always set the video's featured image regardless of the plugin setting
			set_post_thumbnail( $post['ID'], $thumb_id );
		}

		$checkboxes = array(
			'featured',
		); // make sure unchecked checkbox values are saved
		foreach ( $checkboxes as $checkbox ) {
			if ( ! isset( $attachment[ 'thumbwiz-' . $checkbox ] ) ) {
				$attachment[ 'thumbwiz-' . $checkbox ] = 'false';
			}
		}

		$thumbwiz_postmeta = array();

		foreach ( $attachment as $meta_key => $value ) {
			if ( strpos( $meta_key, 'thumbwiz-' ) !== false
				&& $meta_key !== 'thumbwiz-security'
			) {
				$key                       = str_replace( 'thumbwiz-', '', $meta_key );
				$thumbwiz_postmeta[ $key ] = $value;
			}
		}

		thumbwiz_save_attachment_meta( $post['ID'], $thumbwiz_postmeta );

	}
	++$flag;
	return $post;
}

add_filter( 'attachment_fields_to_save', 'thumbwiz_video_attachment_fields_to_save', null, 2 );

function thumbwiz_can_write_direct( $path ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';

	if ( get_filesystem_method( array(), $path, true ) === 'direct' ) {
		$creds = request_filesystem_credentials( site_url() . '/wp-admin/', '', false, false, array() );
		if ( ! WP_Filesystem( $creds ) ) {
			return false;
		}
		return true;
	}
	return false;
}

function thumbwiz_decode_base64_png( $raw_png, $tmp_posterpath ) {
	$raw_png     = str_replace( 'data:image/png;base64,', '', $raw_png );
	$raw_png     = str_replace( 'data:image/jpeg;base64,', '', $raw_png );
	$raw_png     = str_replace( ' ', '+', $raw_png );
	$decoded_png = base64_decode( $raw_png );

	if ( thumbwiz_can_write_direct( dirname( $tmp_posterpath ) ) ) {
		global $wp_filesystem;
		$success = $wp_filesystem->put_contents( $tmp_posterpath, $decoded_png );

		$editor = wp_get_image_editor( $tmp_posterpath );
		if ( is_wp_error( $editor ) ) {
			$wp_filesystem->delete( $tmp_posterpath );
		}

		return $editor;
	}
	return false;
}

function thumbwiz_cleanup_generated_thumbnails_handler() {
	$uploads = wp_upload_dir();

	if ( thumbwiz_can_write_direct( $uploads['path'] . '/thumb_tmp' ) ) {
		global $wp_filesystem;
		$wp_filesystem->rmdir( $uploads['path'] . '/thumb_tmp', true ); // remove the whole tmp file directory
	}
}

add_action( 'thumbwiz_cleanup_generated_thumbnails', 'thumbwiz_cleanup_generated_thumbnails_handler' );

function thumbwiz_schedule_cleanup_generated_files( $arg ) {
	// schedules deleting all tmp thumbnails or logfiles if no files are generated in an hour

	if ( $arg == 'thumbs' ) {
		$timestamp = wp_next_scheduled( 'thumbwiz_cleanup_generated_thumbnails' );
		wp_unschedule_event( $timestamp, 'thumbwiz_cleanup_generated_thumbnails' );
		wp_schedule_single_event( time() + 3600, 'thumbwiz_cleanup_generated_thumbnails' );
	} else {
		$timestamp = wp_next_scheduled( 'thumbwiz_cleanup_generated_logfiles' );
		wp_unschedule_event( $timestamp, 'thumbwiz_cleanup_generated_logfiles' );
		$args = array( 'logfile' => $arg );
		wp_schedule_single_event( time() + 600, 'thumbwiz_cleanup_generated_logfiles', $args );
	}
}

function thumbwiz_save_thumb( $post_id, $post_name, $thumb_url, $index = false ) {

	$user_ID = get_current_user_id();
	$options = thumbwiz_get_options();
	$uploads = wp_upload_dir();

	$posterfile       = pathinfo( $thumb_url, PATHINFO_BASENAME );
	$tmp_posterpath   = $uploads['path'] . '/thumb_tmp/' . $posterfile;
	$final_posterpath = $uploads['path'] . '/' . $posterfile;

	if ( is_file( $final_posterpath ) ) {

		$old_thumb_id = attachment_url_to_postid( $thumb_url );

		if ( ! $old_thumb_id ) { // should be there but check if it was so big it was scaled down
			$old_thumb_id = attachment_url_to_postid( str_replace( '.jpg', '-scaled.jpg', $thumb_url ) );
		}

		if ( $old_thumb_id ) {

			$existing_posterpath = wp_get_original_image_path( $old_thumb_id );

			if ( is_file( $tmp_posterpath )
				&& abs( filemtime( $tmp_posterpath ) - filemtime( $existing_posterpath ) ) > 10 // file modified time more than 10 seconds different
				&& abs( filesize( $tmp_posterpath ) - filesize( $existing_posterpath ) ) > 100 // filesize is more than 100 bytes different means it's probably a different image
			) {

				$posterfile_noextension = pathinfo( $thumb_url, PATHINFO_FILENAME );

				$thumb_index = $index;
				if ( $thumb_index === false ) {
					$thumb_index = substr( $posterfile_noextension, strpos( $posterfile_noextension, '_thumb' ) + 6 );
				}

				if ( $thumb_index === false ) { // nothing after "_thumb"
					$thumb_index        = 1;
					$posterfile_noindex = $posterfile_noextension;
				} else {
					$posterfile_noindex = str_replace( '_thumb' . $thumb_index, '_thumb', $posterfile_noextension );
					$thumb_index        = intval( $thumb_index );
					++$thumb_index;
				}

				while ( is_file( $uploads['path'] . '/' . $posterfile_noindex . $thumb_index . '.jpg' ) ) {
					++$thumb_index; // increment the filename until we get one that doesn't exist
				}

				$final_posterpath = $uploads['path'] . '/' . $posterfile_noindex . $thumb_index . '.jpg';
				$thumb_url        = $uploads['url'] . '/' . $posterfile_noindex . $thumb_index . '.jpg';

			} else { // if a new thumbnail was just entered that's exactly the same as the old one, use the old one

				$arr = array(
					'thumb_id'  => $old_thumb_id,
					'thumb_url' => $thumb_url,
				);
				return $arr;

			}
		}
	}

	$success = false;
	if ( ! is_file( $final_posterpath ) && is_file( $tmp_posterpath ) ) { // if the file doesn't already exist and the tmp one does
		$success = copy( $tmp_posterpath, $final_posterpath );
	}

	// insert the $thumb_url into the media library if it does not already exist

	if ( $success ) { // new file was copied into uploads directory

		wp_delete_file( $tmp_posterpath );

		$desc = $post_name . ' ' . esc_html_x( 'thumbnail', 'text appended to newly created thumbnail titles', 'thumbwiz' );
		if ( $index ) {
			$desc .= ' ' . $index;
		}

		// is image in uploads directory?
		$upload_dir = wp_upload_dir();

		$video = get_post( $post_id );
		if ( $options['thumb_parent'] === 'post' ) {
			if ( ! empty( $video->post_parent ) ) {
				$post_id = $video->post_parent;
			}
		}

		if ( false !== strpos( $thumb_url, $upload_dir['baseurl'] ) ) {
			$wp_filetype = wp_check_filetype( basename( $thumb_url ), null );
			if ( $user_ID == 0 ) {
				$user_ID = $video->post_author;
			}

			$attachment = array(
				'guid'           => $thumb_url,
				'post_mime_type' => $wp_filetype['type'],
				'post_title'     => $desc,
				'post_content'   => '',
				'post_status'    => 'inherit',
				'post_author'    => $user_ID,
			);

			$thumb_id = wp_insert_attachment( $attachment, $final_posterpath, $post_id );
			// you must first include the image.php file
			// for the function wp_generate_attachment_metadata() to work
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$attach_data = wp_generate_attachment_metadata( $thumb_id, $final_posterpath );
			wp_update_attachment_metadata( $thumb_id, $attach_data );
		} else { // not in uploads so we'll have to sideload it
			$tmp = download_url( $thumb_url );

			// Set variables for storage
			// fix file filename for query strings
			preg_match( '/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $thumb_url, $matches );
			$file_array['name']     = basename( $matches[0] );
			$file_array['tmp_name'] = $tmp;

			// If error storing temporarily, delete
			if ( is_wp_error( $tmp ) ) {
				wp_delete_file( $file_array['tmp_name'] );
				$file_array['tmp_name'] = '';
			}

			// do the validation and storage stuff
			$thumb_id = media_handle_sideload( $file_array, $post_id, $desc );

			// If error storing permanently, delete
			if ( is_wp_error( $thumb_id ) ) {
				wp_delete_file( $file_array['tmp_name'] );
				$arr = array(
					'thumb_id'  => $thumb_id,
					'thumb_url' => $thumb_url,
				);
				return $arr;
			}
		} //end sideload

		$thumb_id = intval( $thumb_id );
		//end copied new file into uploads directory
	} else {
		$thumb_id = false;
	}

	if ( $thumb_id ) {
		set_post_thumbnail( $post_id, $thumb_id );
	}

	$arr = array(
		'thumb_id'  => $thumb_id,
		'thumb_url' => $thumb_url,
	);
	return $arr;
}

function thumbwiz_ajax_save_html5_thumb() {

	$thumb_info = array(
		'thumb_url' => false,
		'thumb_id'  => false,
	);
	$uploads    = wp_upload_dir();

	if ( current_user_can( 'upload_files' ) ) {

		check_ajax_referer( 'thumbwiz-nonce', 'security' );

		if ( isset( $_POST['postID'] ) ) {
			$post_id = thumbwiz_sanitize_text_field( wp_unslash( $_POST['postID'] ) );
		}
		if ( isset( $_POST['raw_png'] ) ) {
			$raw_png = thumbwiz_sanitize_text_field( wp_unslash( $_POST['raw_png'] ) );
		}
		if ( isset( $_POST['url'] ) ) {
			$video_url = thumbwiz_sanitize_text_field( wp_unslash( $_POST['url'] ) );
		}
		if ( isset( $_POST['total'] ) ) {
			$total = thumbwiz_sanitize_text_field( wp_unslash( $_POST['total'] ) );
		}
		if ( isset( $_POST['index'] ) ) {
			$index = intval( thumbwiz_sanitize_text_field( wp_unslash( $_POST['index'] ) ) ) + 1;
		}

		$sanitized_url = thumbwiz_sanitize_url( $video_url );
		$posterfile    = $sanitized_url['basename'] . '_thumb' . $index;
		wp_mkdir_p( $uploads['path'] . '/thumb_tmp' );
		$tmp_posterpath          = $uploads['path'] . '/thumb_tmp/' . $posterfile . '.png';
		$thumb_info['thumb_url'] = $uploads['url'] . '/' . $posterfile . '.jpg';

		$editor = thumbwiz_decode_base64_png( $raw_png, $tmp_posterpath );

		if ( $editor === false || is_wp_error( $editor ) ) { // couldn't open the image. Try the alternate php://input

			$raw_post = file_get_contents( 'php://input' );
			parse_str( $raw_post, $alt_post );
			$editor = thumbwiz_decode_base64_png( $alt_post['raw_png'], $tmp_posterpath );

		}

		if ( $editor === false || is_wp_error( $editor ) ) {
			$thumb_info['thumb_url'] = false;
		} else {
			$thumb_dimensions = $editor->get_size();
			if ( $thumb_dimensions ) {
				$thumbwiz_postmeta                 = thumbwiz_get_attachment_meta( $post_id );
				$thumbwiz_postmeta['actualwidth']  = $thumb_dimensions['width'];
				$thumbwiz_postmeta['actualheight'] = $thumb_dimensions['height'];
				thumbwiz_save_attachment_meta( $post_id, $thumbwiz_postmeta );
			}
			$editor->set_quality( 90 );
			$new_image_info = $editor->save( $uploads['path'] . '/thumb_tmp/' . $posterfile . '.jpg', 'image/jpeg' );
			wp_delete_file( $tmp_posterpath ); // delete png

			$post_name  = get_the_title( $post_id );
			$thumb_info = thumbwiz_save_thumb( $post_id, $post_name, $thumb_info['thumb_url'], $index );

		}

		thumbwiz_schedule_cleanup_generated_files( 'thumbs' );

	}
	wp_send_json( $thumb_info );
}

add_action( 'wp_ajax_thumbwiz_save_html5_thumb', 'thumbwiz_ajax_save_html5_thumb' );

function thumbwiz_ajax_redraw_thumbnail_box() {
	check_ajax_referer( 'thumbwiz-nonce', 'security' );

	if ( isset( $_POST['post_id'] ) ) {
		$post_id = thumbwiz_sanitize_text_field( wp_unslash( $_POST['post_id'] ) );
	}
	$thumbwiz_postmeta  = thumbwiz_get_attachment_meta( $post_id );
	$poster_id          = get_post_thumbnail_id( $post_id );
	$thumbnail_size_url = '';
	if ( ! empty( $poster_id ) ) {
		$thumbnail_src = wp_get_attachment_image_src( $poster_id, 'thumbnail' );
		if ( is_array( $thumbnail_src ) && array_key_exists( 0, $thumbnail_src ) ) {
			$thumbnail_size_url = $thumbnail_src[0];
		}
	}

	$response = array(
		'thumb_url'          => esc_url( get_the_post_thumbnail_url( $post_id ) ),
		'thumbnail_size_url' => esc_url( $thumbnail_size_url ),
		'thumb_error'        => wp_kses_post( $thumbwiz_postmeta['autothumb-error'] ),
		'thumb_id'           => esc_html( $poster_id ),
	);
	wp_send_json( $response );
}

add_action( 'wp_ajax_thumbwiz_redraw_thumbnail_box', 'thumbwiz_ajax_redraw_thumbnail_box' );
