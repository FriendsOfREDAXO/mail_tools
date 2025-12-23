<?php

class rex_api_mail_tools_imap_test extends rex_api_function
{
    public function execute()
    {
        $host = rex_request('host', 'string');
        $user = rex_request('user', 'string');
        $password = rex_request('password', 'string');
        $port = rex_request('port', 'int', 993);
        $debug = rex_request('debug', 'bool', false);
        $folder = rex_request('folder', 'string', 'INBOX');

        if (!$host || !$user || !$password) {
            rex_response::sendJson(['success' => false, 'message' => 'Missing parameters']);
            exit;
        }

        if ($debug) {
            $result = \FriendsOfRedaxo\MailTools\BounceHandler::getRecentEmails($host, $user, $password, $port, $folder);
        } else {
            $result = \FriendsOfRedaxo\MailTools\BounceHandler::testConnection($host, $user, $password, $port);
        }
        
        rex_response::cleanOutputBuffers();
        rex_response::sendJson($result);
        exit;
    }
}
