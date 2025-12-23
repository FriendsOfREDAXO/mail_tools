<?php

namespace FriendsOfRedaxo\MailTools;

use rex_cronjob;

class CronjobBounces extends rex_cronjob
{
    public function execute(): bool
    {
        $result = BounceHandler::processBounces();
        
        if (isset($result['error'])) {
            $this->setMessage($result['error']);
            return false;
        }
        
        $this->setMessage(sprintf('Processed %d bounces.', $result['count']));
        return true;
    }

    public function getTypeName(): string
    {
        return 'Mail Tools: Bounce Handler';
    }
}
