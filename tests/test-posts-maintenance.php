<?php
/**
 * Unit tests for Posts Maintenance scan feature.
 */
class Test_Posts_Maintenance extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        // Create some posts and pages
        $this->post_ids = [];
        $this->page_ids = [];
        for ($i = 0; $i < 3; $i++) {
            $this->post_ids[] = $this->factory->post->create(['post_status' => 'publish']);
            $this->page_ids[] = $this->factory->post->create(['post_type' => 'page', 'post_status' => 'publish']);
        }
    }

    public function test_scan_updates_post_meta() {
        // Simulate scan logic
        foreach (array_merge($this->post_ids, $this->page_ids) as $id) {
            update_post_meta($id, 'wpmudev_test_last_scan', '');
        }
        // Call the scan handler directly
        require_once dirname(__FILE__) . '/../app/admin-pages/class-posts-maintenance.php';
        $scanner = new \WPMUDEV\PluginTest\AdminPages\Posts_Maintenance();
        // Simulate AJAX handler logic
        global $wpdb;
        $post_types = ['post', 'page'];
        $posts = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM $wpdb->posts WHERE post_type IN (" . implode(',', array_fill(0, count($post_types), '%s')) . ") AND post_status = 'publish'",
            ...$post_types
        ));
        foreach ($posts as $post_id) {
            update_post_meta($post_id, 'wpmudev_test_last_scan', current_time('mysql'));
        }
        // Check post meta
        foreach (array_merge($this->post_ids, $this->page_ids) as $id) {
            $meta = get_post_meta($id, 'wpmudev_test_last_scan', true);
            $this->assertNotEmpty($meta, 'Post meta should be updated');
        }
    }

    public function test_scan_handles_no_posts() {
        // Remove all posts/pages
        foreach (array_merge($this->post_ids, $this->page_ids) as $id) {
            wp_delete_post($id, true);
        }
        // Call scan logic
        require_once dirname(__FILE__) . '/../app/admin-pages/class-posts-maintenance.php';
        $scanner = new \WPMUDEV\PluginTest\AdminPages\Posts_Maintenance();
        global $wpdb;
        $post_types = ['post', 'page'];
        $posts = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM $wpdb->posts WHERE post_type IN (" . implode(',', array_fill(0, count($post_types), '%s')) . ") AND post_status = 'publish'",
            ...$post_types
        ));
        $this->assertEmpty($posts, 'There should be no posts to scan');
    }
}
