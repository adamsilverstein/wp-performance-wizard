<?php

use PHPUnit\Framework\TestCase;

class WP_Performance_Wizard_Test extends TestCase {

    public function testGetApiKey() {
        global \$wp_filesystem;

        define('ABSPATH', '/');
        require_once ABSPATH . 'wp-admin/includes/file.php';
        \$wp_filesystem = \$this->createMock('WP_Filesystem_Base');
        \$GLOBALS['wp_filesystem'] = \$wp_filesystem;

        \$mock_key_data = '{"apikey": "test_api_key"}';

        \$wp_filesystem->method('get_contents')
            ->willReturn(\$mock_key_data);

        \$pw = new WP_Performance_Wizard();
        \$api_key = \$pw->get_api_key('Gemini');

        \$this->assertEquals('test_api_key', \$api_key);

        \$api_key_empty = \$pw->get_api_key('');
        \$this->assertEquals('', \$api_key_empty);
    }
}
