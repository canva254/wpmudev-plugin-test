<?php
/**
 * WP-CLI command for Posts Maintenance
 */

namespace WPMUDEV\PluginTest\CLI;

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    /**
     * Scan posts/pages and update meta via WP-CLI.
     *
     * ## OPTIONS
     *
     * [--post_types=<types>]
     * : Comma-separated list of post types to scan. Default: post,page
     *
     * ## EXAMPLES
     *
     *     wp wpmudev posts-scan
     *     wp wpmudev posts-scan --post_types=post,page,custom_type
     */
    class Posts_Maintenance_CLI {
        /**
         * Scan posts and update meta.
         *
         * @when after_wp_load
         */
        public function posts_scan( $args, $assoc_args ) {
            $types = isset($assoc_args['post_types']) ? explode(',', $assoc_args['post_types']) : ['post','page'];
            $query = new \WP_Query([
                'post_type' => $types,
                'post_status' => 'publish',
                'fields' => 'ids',
                'posts_per_page' => -1,
            ]);
            $total = count($query->posts);
            $count = 0;
            foreach ($query->posts as $post_id) {
                update_post_meta($post_id, 'wpmudev_test_last_scan', current_time('mysql'));
                $count++;
                if ($count % 100 === 0) {
                    \WP_CLI::log("Scanned $count/$total posts...");
                }
            }
            \WP_CLI::success("Scan complete. $count posts/pages updated.");
        }
    }
    \WP_CLI::add_command('wpmudev posts-scan', [ 'WPMUDEV\\PluginTest\\CLI\\Posts_Maintenance_CLI', 'posts_scan' ]);
}
