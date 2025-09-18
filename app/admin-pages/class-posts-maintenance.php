<?php
/**
 * Posts Maintenance Admin Page for WPMUDEV Plugin Test
 */

namespace WPMUDEV\PluginTest\AdminPages;

if ( ! defined( 'WPINC' ) ) {
    die;
}

class Posts_Maintenance {
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'wp_ajax_wpmudev_posts_scan', [ $this, 'handle_scan_ajax' ] );
    }

    public function add_admin_menu() {
        add_menu_page(
            __( 'Posts Maintenance', 'wpmudev-plugin-test' ),
            __( 'Posts Maintenance', 'wpmudev-plugin-test' ),
            'manage_options',
            'wpmudev_posts_maintenance',
            [ $this, 'render_admin_page' ],
            'dashicons-update',
            80
        );
    }

    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Posts Maintenance', 'wpmudev-plugin-test' ); ?></h1>
            <p><?php esc_html_e( 'Scan all public posts and pages, update post meta, and view progress.', 'wpmudev-plugin-test' ); ?></p>
            <button id="wpmudev-scan-posts" class="button button-primary">
                <?php esc_html_e( 'Scan Posts', 'wpmudev-plugin-test' ); ?>
            </button>
            <div id="wpmudev-scan-progress"></div>
        </div>
        <script type="text/javascript">
        jQuery(document).ready(function($){
            $('#wpmudev-scan-posts').on('click', function(){
                var $btn = $(this);
                $btn.prop('disabled', true);
                $('#wpmudev-scan-progress').html('<p>Scanning...</p>');
                $.post(ajaxurl, { action: 'wpmudev_posts_scan', _ajax_nonce: '<?php echo wp_create_nonce('wpmudev_posts_scan'); ?>' }, function(response){
                    if(response.success){
                        $('#wpmudev-scan-progress').html('<p>'+response.data+'</p>');
                    } else {
                        $('#wpmudev-scan-progress').html('<p style="color:red;">'+(response.data || 'Error occurred')+'</p>');
                    }
                    $btn.prop('disabled', false);
                });
            });
        });
        </script>
        <?php
    }

    public function handle_scan_ajax() {
        check_ajax_referer( 'wpmudev_posts_scan' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'wpmudev-plugin-test' ) );
        }

        global $wpdb;
        $post_types = apply_filters('wpmudev_posts_maintenance_types', ['post', 'page']);
        $posts = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM $wpdb->posts WHERE post_type IN (" . implode(',', array_fill(0, count($post_types), '%s')) . ") AND post_status = 'publish'",
            ...$post_types
        ));
        $count = 0;
        foreach ($posts as $post_id) {
            update_post_meta($post_id, 'wpmudev_test_last_scan', current_time('mysql'));
            $count++;
        }
        wp_send_json_success(sprintf(esc_html__('Scan completed. %d posts/pages updated.', 'wpmudev-plugin-test'), $count));
    }
}

// Initialize the admin page
new Posts_Maintenance();
