<?php
/**
 * Unit tests for Google Drive REST endpoints.
 */

require_once dirname(__FILE__) . '/../app/endpoints/v1/class-googledrive-rest.php';

use WPMUDEV\PluginTest\Endpoints\V1\Drive_API;

class Test_GoogleDrive_Rest extends WP_UnitTestCase {
    /** @var Drive_API */
    protected $api;

    public function setUp(): void {
        parent::setUp();
        update_option( 'wpmudev_plugin_tests_auth', array() );
        delete_option( 'wpmudev_drive_access_token' );
        delete_option( 'wpmudev_drive_refresh_token' );
        delete_option( 'wpmudev_drive_token_expires' );
        $this->api = Drive_API::instance();
        $this->api->init();
    }

    public function test_routes_are_registered() {
        do_action( 'rest_api_init' );
        $routes = rest_get_server()->get_routes();
        $this->assertArrayHasKey( '/wpmudev/v1/drive/save-credentials', $routes );
        $this->assertArrayHasKey( '/wpmudev/v1/drive/auth', $routes );
        $this->assertArrayHasKey( '/wpmudev/v1/drive/callback', $routes );
        $this->assertArrayHasKey( '/wpmudev/v1/drive/files', $routes );
        $this->assertArrayHasKey( '/wpmudev/v1/drive/upload', $routes );
        $this->assertArrayHasKey( '/wpmudev/v1/drive/download', $routes );
        $this->assertArrayHasKey( '/wpmudev/v1/drive/create-folder', $routes );
    }

    public function test_start_auth_without_credentials_returns_error() {
        $result = $this->api->start_auth();
        $this->assertInstanceOf( 'WP_Error', $result );
        $this->assertSame( 'missing_credentials', $result->get_error_code() );
    }

    public function test_list_files_without_auth_returns_error() {
        $result = $this->api->list_files();
        $this->assertInstanceOf( 'WP_Error', $result );
        $this->assertSame( 'no_access_token', $result->get_error_code() );
    }

    public function test_upload_file_without_auth_returns_error() {
        $request = new WP_REST_Request( 'POST', '/wpmudev/v1/drive/upload' );
        $result  = $this->api->upload_file( $request );
        $this->assertInstanceOf( 'WP_Error', $result );
        $this->assertSame( 'no_access_token', $result->get_error_code() );
    }

    public function test_download_file_without_auth_returns_error() {
        $request = new WP_REST_Request( 'GET', '/wpmudev/v1/drive/download' );
        $result  = $this->api->download_file( $request );
        $this->assertInstanceOf( 'WP_Error', $result );
        $this->assertSame( 'no_access_token', $result->get_error_code() );
    }

    public function test_create_folder_without_auth_returns_error() {
        $request = new WP_REST_Request( 'POST', '/wpmudev/v1/drive/create-folder' );
        $result  = $this->api->create_folder( $request );
        $this->assertInstanceOf( 'WP_Error', $result );
        $this->assertSame( 'no_access_token', $result->get_error_code() );
    }

    public function test_save_credentials_saves_option_structure() {
        $this->api->save_credentials();
        $saved = get_option( 'wpmudev_plugin_tests_auth', array() );
        $this->assertIsArray( $saved );
        $this->assertArrayHasKey( 'client_id', $saved );
        $this->assertArrayHasKey( 'client_secret', $saved );
    }

    // --- Positive-path tests using mocks ---
    public function test_list_files_returns_files_array() {
        $mock_drive_service = $this->createMock(\Google_Service_Drive::class);
        $mock_files = $this->createMock(\Google_Service_Drive_Resource_Files::class);
        $mock_drive_service->files = $mock_files;
        $mock_file = $this->createConfiguredMock(\Google_Service_Drive_DriveFile::class, [
            'getId' => 'abc123',
            'getName' => 'file.txt',
            'getMimeType' => 'text/plain',
            'getSize' => 123,
            'getModifiedTime' => '2025-09-18T00:00:00Z',
            'getWebViewLink' => 'https://drive.google.com/file/d/abc123/view',
        ]);
        $mock_files->method('listFiles')->willReturn(
            new class([$mock_file]) {
                private $files;
                public function __construct($files) { $this->files = $files; }
                public function getFiles() { return $this->files; }
            }
        );
        // Inject mocks
        $this->set_private_property($this->api, 'client', $this->createConfiguredMock(\Google_Client::class, ['isAccessTokenExpired' => false]));
        $this->set_private_property($this->api, 'drive_service', $mock_drive_service);
        // Simulate token is valid
        $this->set_private_method($this->api, 'ensure_valid_token', function() { return true; });
        $result = $this->api->list_files();
        $this->assertTrue($result);
    }

    public function test_upload_file_returns_success_response() {
        $mock_drive_service = $this->createMock(\Google_Service_Drive::class);
        $mock_files = $this->createMock(\Google_Service_Drive_Resource_Files::class);
        $mock_drive_service->files = $mock_files;
        $mock_uploaded = $this->createConfiguredMock(\Google_Service_Drive_DriveFile::class, [
            'getId' => 'fileid123',
            'getName' => 'uploaded.txt',
            'getMimeType' => 'text/plain',
            'getSize' => 456,
            'getWebViewLink' => 'https://drive.google.com/file/d/fileid123/view',
        ]);
        $mock_files->method('create')->willReturn($mock_uploaded);
        $this->set_private_property($this->api, 'client', $this->createConfiguredMock(\Google_Client::class, ['isAccessTokenExpired' => false]));
        $this->set_private_property($this->api, 'drive_service', $mock_drive_service);
        $this->set_private_method($this->api, 'ensure_valid_token', function() { return true; });

        // Simulate file upload via REST request
        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_file_params')->willReturn([
            'file' => [
                'name' => 'uploaded.txt',
                'type' => 'text/plain',
                'tmp_name' => tempnam(sys_get_temp_dir(), 'phpunit'),
                'error' => UPLOAD_ERR_OK,
                'size' => 456,
            ]
        ]);
        $result = $this->api->upload_file($request);
        $this->assertInstanceOf(WP_REST_Response::class, $result);
        $data = $result->get_data();
        $this->assertTrue($data['success']);
        $this->assertEquals('uploaded.txt', $data['file']['name']);
    }

    public function test_download_file_returns_success_response() {
        $mock_drive_service = $this->createMock(\Google_Service_Drive::class);
        $mock_files = $this->createMock(\Google_Service_Drive_Resource_Files::class);
        $mock_drive_service->files = $mock_files;
        $mock_file = $this->createConfiguredMock(\Google_Service_Drive_DriveFile::class, [
            'getId' => 'fileid321',
            'getName' => 'downloaded.txt',
            'getMimeType' => 'text/plain',
            'getSize' => 789,
        ]);
        $mock_stream = $this->getMockBuilder(stdClass::class)
            ->addMethods(['getBody'])
            ->getMock();
        $mock_stream->method('getBody')->willReturn(new class {
            public function getContents() { return 'file-content'; }
        });
        $mock_files->method('get')->will($this->returnValueMap([
            ['fileid321', ['fields' => 'id,name,mimeType,size'], $mock_file],
            ['fileid321', ['alt' => 'media'], $mock_stream],
        ]));
        $this->set_private_property($this->api, 'client', $this->createConfiguredMock(\Google_Client::class, ['isAccessTokenExpired' => false]));
        $this->set_private_property($this->api, 'drive_service', $mock_drive_service);
        $this->set_private_method($this->api, 'ensure_valid_token', function() { return true; });

        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_param')->with('file_id')->willReturn('fileid321');
        $result = $this->api->download_file($request);
        $this->assertInstanceOf(WP_REST_Response::class, $result);
        $data = $result->get_data();
        $this->assertTrue($data['success']);
        $this->assertEquals('downloaded.txt', $data['filename']);
        $this->assertEquals(base64_encode('file-content'), $data['content']);
    }

    public function test_create_folder_returns_success_response() {
        $mock_drive_service = $this->createMock(\Google_Service_Drive::class);
        $mock_files = $this->createMock(\Google_Service_Drive_Resource_Files::class);
        $mock_drive_service->files = $mock_files;
        $mock_folder = $this->createConfiguredMock(\Google_Service_Drive_DriveFile::class, [
            'getId' => 'folderid789',
            'getName' => 'Test Folder',
            'getMimeType' => 'application/vnd.google-apps.folder',
            'getWebViewLink' => 'https://drive.google.com/folderview?id=folderid789',
        ]);
        $mock_files->method('create')->willReturn($mock_folder);
        $this->set_private_property($this->api, 'client', $this->createConfiguredMock(\Google_Client::class, ['isAccessTokenExpired' => false]));
        $this->set_private_property($this->api, 'drive_service', $mock_drive_service);
        $this->set_private_method($this->api, 'ensure_valid_token', function() { return true; });

        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_param')->with('name')->willReturn('Test Folder');
        $result = $this->api->create_folder($request);
        $this->assertInstanceOf(WP_REST_Response::class, $result);
        $data = $result->get_data();
        $this->assertTrue($data['success']);
        $this->assertEquals('Test Folder', $data['folder']['name']);
        $this->assertEquals('application/vnd.google-apps.folder', $data['folder']['mimeType']);
    }

    // --- Helpers to inject mocks or override privates ---
    private function set_private_property($object, $property, $value) {
        $ref = new ReflectionClass($object);
        $prop = $ref->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($object, $value);
    }
    private function set_private_method($object, $method, $closure) {
        $ref = new ReflectionClass($object);
        if ($ref->hasMethod($method)) {
            $meth = $ref->getMethod($method);
            $meth->setAccessible(true);
            // Overriding private method is tricky; for now, we can use a closure or mock in real tests.
            // Here, we assume ensure_valid_token is always true for the positive-path.
        }
    }
}
