<?php

namespace FriendsOfRedaxo\MailTools;

use rex_cronjob;
use rex_addon;

class CronjobGdpr extends rex_cronjob
{
    public function execute(): bool
    {
        $addon = rex_addon::get('mail_tools');
        $days = (int) $addon->getConfig('gdpr_anonymize_days', 30);
        
        if ($days <= 0) {
            $this->setMessage('Anonymization disabled.');
            return true;
        }
        
        $count = GdprTools::anonymizeLogs($days);
        
        $this->setMessage(sprintf('Anonymized %d log entries.', $count));
        return true;
    }

    public function getTypeName(): string
    {
        return 'Mail Tools: GDPR Anonymization';
    }
}
