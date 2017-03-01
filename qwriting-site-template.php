<?php
/**
 * Plugin Name:     Qwriting Site Template
 * Plugin URI:      http://qwriting.qc.cuny.edu
 * Author:          Boone Gorges
 * Author URI:      https://boone.gorg.es
 * Text Domain:     qwriting-site-template
 * Domain Path:     /languages
 * Version:         0.1.0
 */

define( 'QWST_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Register post types.
add_action( 'init', 'qwst_init' );

// Meta boxes.
add_action( 'add_meta_boxes', 'qwst_meta_boxes' );
add_action( 'save_post', 'qwst_template_site_id_metabox_save_cb' );

// Front-end UI.
add_action( 'signup_blogform', 'qwst_template_selector' );

// Cloning.
add_action( 'wpmu_new_blog', 'qwst_wpmu_new_blog', 10, 6 );

/**
 * Register post type.
 */
function qwst_init() {
	register_post_type( 'qwst_template', array(
		'labels' => array(
			'name' => __( 'Site Templates', 'qwriting-site-templates' ),
			'singular_name' => __( 'Site Template', 'qwriting-site-templates' ),
			'not_found' => __( 'No site templates found.', 'qwriting-site-templates' ),
			'edit_item' => __( 'Edit Site Template', 'qwriting-site-templates' ),
		),
		'public' => false,
		'show_ui' => true,
		'show_in_menu' => true,
		'supports' => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
	) );
}

/**
 * Register meta boxes.
 */
function qwst_meta_boxes() {
	add_meta_box(
		'qwst-template-site-id',
		__( 'Template Site', 'qwriting-site-templates' ),
		'qwst_template_site_id_metabox',
		'qwst_template',
		'side'
	);
}

/**
 * Site template meta box render callback.
 *
 * @param WP_Post $post
 */
function qwst_template_site_id_metabox( WP_Post $post ) {
	$template_site_id = qwst_get_template_site_id( $post->ID );

	?>
	<label for="qwst-template-site-id"><?php esc_html_e( 'Site ID', 'qwriting-site-templates' ); ?></label>&nbsp;
	<input type="text" size="7" id="qwst-template-site-id" name="qwst-template-site-id" value="<?php echo esc_attr( $template_site_id ); ?>" />
	<p class="description">The numerical ID of the template site from which new sites will be cloned.</p>
	<?php

	wp_nonce_field( 'qwst_template_site_id_' . $post->ID, 'qwst-template-site-id-nonce', false );
}

/**
 * Save callback for template site ID.
 *
 * @param int $post_id ID of the post being saved.
 */
function qwst_template_site_id_metabox_save_cb( $post_id ) {
	if ( ! isset( $_POST['qwst-template-site-id-nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['qwst-template-site-id-nonce'] ), 'qwst_template_site_id_' . $post_id ) ) {
		return;
	}

	if ( ! isset( $_POST['qwst-template-site-id'] ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$submitted = intval( $_POST['qwst-template-site-id'] );

	qwst_set_template_site_id( $post_id, $submitted );
}

/**
 * Get the ID of the site associated with a template.
 *
 * @param int $template_id ID of the template object.
 * @return int|null A site ID if found, otherwise null.
 */
function qwst_get_template_site_id( $template_id ) {
	$site_id = get_post_meta( $template_id, 'qwst_template_site_id', true );

	if ( $site_id ) {
		$site_id = intval( $site_id );
	} else {
		$site_id = null;
	}

	return $site_id;
}

/**
 * Save the ID of the site associated with a template.
 *
 * @param int $template_id ID of the template object.
 * @param int $site_id     ID of the site.
 * @return bool
 */
function qwst_set_template_site_id( $template_id, $site_id ) {
	return (bool) update_post_meta( $template_id, 'qwst_template_site_id', $site_id );
}

/**
 * Get templates.
 *
 * @return array Array of WP_Post objects.
 */
function qwst_get_templates() {
	$templates = get_posts( array(
		'post_type' => 'qwst_template',
		'posts_per_page' => '-1',
		'orderby' => 'menu_order',
		'order' => 'ASC',
	) );

	return $templates;
}

function qwst_template_selector( $errors ) {
	$templates = qwst_get_templates();

	if ( empty( $templates ) ) {
		return;
	}

	// @todo
	$checked = true;

	wp_enqueue_style( 'qwst-front', QWST_PLUGIN_URL . '/assets/css/front.css' );

	?>
	<fieldset class="site-template">
		<legend class="label"><?php esc_html_e( 'Site Template', 'qwriting-site-templates' ); ?></legend>

		<?php foreach ( $templates as $template ) : ?>
			<div>
				<input type="radio" <?php checked( $checked ); ?> name="qwst-new-site-template" id="qwst-new-site-template-<?php echo esc_attr( $template->ID ); ?>" value="<?php echo esc_attr( $template->ID ); ?>" />

				<div class="site-template-content">
					<label for="qwst-new-site-template-<?php echo esc_attr( $template->ID ); ?>"><strong><?php echo esc_html( $template->post_title ); ?></strong></label>
					<?php echo wpautop( $template->post_content ); ?>
				</div>
			</div>

			<?php $checked = false; ?>
		<?php endforeach; ?>
	</fieldset>
	<?php

	return $errors;
}

/** Cloning ******************************************************************/

function qwst_wpmu_new_blog( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {
	$template = array_key_exists( 'qwst-new-site-template', $_POST ) ? $_POST['qwst-new-site-template'] : $meta['qwst-template'];
	if ( 0 == $template || empty( $template ) ) {
		return;
	}

	$site_id = qwst_get_template_site_id( $template );
	if ( ! $site_id ) {
		return;
	}

	qwst_copy_table( 'commentmeta', $blog_id, $site_id );
	qwst_copy_table( 'comments', $blog_id, $site_id );
	qwst_copy_table( 'links', $blog_id, $site_id );
	qwst_copy_table( 'postmeta', $blog_id, $site_id );
	qwst_copy_table( 'posts', $blog_id, $site_id );
	qwst_copy_table( 'term_relationships', $blog_id, $site_id );
	qwst_copy_table( 'term_taxonomy', $blog_id, $site_id );
	qwst_copy_table( 'terms', $blog_id, $site_id );
	qwst_copy_table( 'termmeta', $blog_id, $site_id );
	qwst_copy_options( $blog_id, $site_id );
	qwst_delete_post_revisions( $blog_id );
}

function qwst_copy_table( $table_name, $blog_id, $template ) {
	global $wpdb;
	global $table_prefix;

	$new_table = $table_prefix . $blog_id . '_' . $table_name;
	$wpdb->query( 'Delete from ' . $new_table );
	$wpdb->query( 'Insert into ' . $new_table . ' select * from ' . $table_prefix . $template . '_' . $table_name );
}

function qwst_delete_post_revisions( $blog_id ) {
	global $wpdb;

	$wpdb->query( "Delete from {$wpdb->posts} where post_type = 'revision'" );
}

function qwst_copy_options( $blog_id, $template ) {
	global $wpdb;
	global $table_prefix;

	switch_to_blog( $template );

	// get all old options
	$all_options = wp_load_alloptions();
	$options = array();
	foreach ( array_keys( $all_options ) as $key ) {
		$options[ $key ] = get_option( $key );  // have to do this to deal with arrays
	}
	// theme mods -- don't show up in all_options.  Won't add mods for inactive theme.
	$theme = get_option( 'current_theme' );
	$mods = get_option( 'mods_' . $theme );

	$preserve_option = array(
		'siteurl',
		'blogname',
		'admin_email',
		'new_admin_email',
		'home',
		'upload_path',
		'db_version',
		$table_prefix . $template . '_user_roles',
		'fileupload_url',
	);

	// now write them all back
	switch_to_blog( $blog_id );
	foreach ( $options as $key => $value ) {
		if ( ! in_array( $key, $preserve_option ) ) {
			update_option( $key, $value );
		}
	}

	// add the theme mods
	update_option( 'mods_' . $theme, $mods );
}
