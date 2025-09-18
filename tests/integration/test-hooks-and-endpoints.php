<?php
/**
 * Integration tests for WordPress hooks, admin pages, and REST endpoints.
 */

class Test_Hooks_And_Endpoints extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        // Register plugin hooks and admin pages
        do_action('init');
        do_action('rest_api_init');
        if (function_exists('set_current_screen')) {
            set_current_screen('dashboard');
        }
    }

    public function test_admin_page_is_registered() {
        global $menu;
        $found = false;
        foreach ($menu as $item) {
            if (is_array($item) && isset($item[2]) && strpos($item[2], 'wpmudev_plugintest_drive') !== false) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Google Drive admin page should be registered in the admin menu.');
    }

    public function test_posts_maintenance_admin_page_is_registered() {
        global $menu;
        $found = false;
        foreach ($menu as $item) {
            if (is_array($item) && isset($item[2]) && strpos($item[2], 'wpmudev_posts_maintenance') !== false) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Posts Maintenance admin page should be registered in the admin menu.');
    }

    public function test_rest_endpoints_are_registered() {
        $routes = rest_get_server()->get_routes();
        $this->assertArrayHasKey('/wpmudev/v1/drive/save-credentials', $routes);
        $this->assertArrayHasKey('/wpmudev/v1/drive/auth', $routes);
        $this->assertArrayHasKey('/wpmudev/v1/drive/callback', $routes);
        $this->assertArrayHasKey('/wpmudev/v1/drive/files', $routes);
        $this->assertArrayHasKey('/wpmudev/v1/drive/upload', $routes);
        $this->assertArrayHasKey('/wpmudev/v1/drive/download', $routes);
        $this->assertArrayHasKey('/wpmudev/v1/drive/create-folder', $routes);
    }

    public function test_rest_endpoint_returns_expected_error_on_unauth() {
        $request = new WP_REST_Request('GET', '/wpmudev/v1/drive/files');
        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();
        $this->assertArrayHasKey('code', $data);
        $this->assertEquals('no_access_token', $data['code']);
    }

    // Add more integration tests as needed for hooks/filters
}
