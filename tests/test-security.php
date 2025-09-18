<?php
/**
 * Security tests for nonce verification, capability checks, and input validation.
 */

class Test_Security extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        do_action('init');
        do_action('rest_api_init');
    }

    public function test_drive_endpoints_require_nonce_for_post() {
        // Simulate POST request to save-credentials endpoint without nonce
        $request = new WP_REST_Request('POST', '/wpmudev/v1/drive/save-credentials');
        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();
        $this->assertArrayHasKey('code', $data);
        $this->assertTrue(
            $data['code'] === 'rest_cookie_invalid_nonce' || $data['code'] === 'rest_forbidden',
            'POST endpoints should require a valid nonce.'
        );
    }

    public function test_drive_endpoints_require_manage_options_capability() {
        // Remove all caps from current user
        $user_id = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($user_id);
        $request = new WP_REST_Request('POST', '/wpmudev/v1/drive/save-credentials');
        $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();
        $this->assertArrayHasKey('code', $data);
        $this->assertTrue(
            $data['code'] === 'rest_forbidden' || $data['code'] === 'rest_cannot_edit',
            'Endpoints should require proper capability.'
        );
    }

    public function test_input_is_sanitized_on_create_folder() {
        // This test assumes input is sanitized in the endpoint
        $user_id = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);
        $request = new WP_REST_Request('POST', '/wpmudev/v1/drive/create-folder');
        $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
        $request->set_param('name', '<script>alert(1)</script>');
        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();
        // Should not return raw script tag in folder name
        if (isset($data['folder']['name'])) {
            $this->assertStringNotContainsString('<script>', $data['folder']['name']);
        }
    }
}
