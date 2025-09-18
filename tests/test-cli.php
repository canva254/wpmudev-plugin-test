<?php
/**
 * WP-CLI tests for Posts Maintenance command.
 */

class Test_CLI extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        // Register CLI command if not already registered
        if (defined('WP_CLI') && WP_CLI) {
            do_action('cli_init');
        }
    }

    public function test_wp_cli_command_is_registered() {
        if (!defined('WP_CLI') || !WP_CLI) {
            $this->markTestSkipped('WP_CLI is not defined, skipping CLI tests.');
        }
        $commands = WP_CLI::get_runner()->get_commands();
        $this->assertArrayHasKey('wpmudev posts-maintenance', $commands);
    }

    public function test_wp_cli_scan_command_runs_and_outputs_success() {
        if (!defined('WP_CLI') || !WP_CLI) {
            $this->markTestSkipped('WP_CLI is not defined, skipping CLI tests.');
        }
        // Simulate posts for scan
        $post_id = $this->factory->post->create(['post_status' => 'publish']);
        // Capture CLI output
        ob_start();
        WP_CLI::run_command(['wpmudev', 'posts-maintenance', 'scan'], []);
        $output = ob_get_clean();
        $this->assertStringContainsString('Scan complete', $output);
    }

    public function test_wp_cli_scan_handles_no_posts() {
        if (!defined('WP_CLI') || !WP_CLI) {
            $this->markTestSkipped('WP_CLI is not defined, skipping CLI tests.');
        }
        // Ensure no posts exist
        $all_posts = get_posts(['post_type' => 'any', 'numberposts' => -1]);
        foreach ($all_posts as $post) {
            wp_delete_post($post->ID, true);
        }
        // Capture CLI output
        ob_start();
        WP_CLI::run_command(['wpmudev', 'posts-maintenance', 'scan'], []);
        $output = ob_get_clean();
        $this->assertStringContainsString('No posts found', $output);
    }
}
