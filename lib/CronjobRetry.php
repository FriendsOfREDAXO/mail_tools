<?php

namespace FriendsOfRedaxo\MailTools;

use rex_cronjob;

/**
 * Cronjob für automatische E-Mail Retries.
 *
 * Versendet fehlgeschlagene E-Mails mit temporären Fehlern erneut.
 */
class CronjobRetry extends rex_cronjob
{
    public function execute(): bool
    {
        try {
            $stats = RetryHandler::processRetries();

            if ($stats['total'] === 0) {
                $this->setMessage('Keine E-Mails für Retry gefunden');
                return true;
            }

            $this->setMessage(sprintf(
                '%d E-Mail(s) verarbeitet: %d erfolgreich, %d fehlgeschlagen',
                $stats['total'],
                $stats['success'],
                $stats['failed'],
            ));

            return true;
        } catch (\Exception $e) {
            $this->setMessage('Fehler: ' . $e->getMessage());
            \rex_logger::logException($e);
            return false;
        }
    }

    public function getTypeName(): string
    {
        return \rex_addon::get('mail_tools')->i18n('cronjob_retry_name');
    }

    /**
     * @return list<array{label?: string, name: string, type: string, notice?: string}>
     */
    public function getParamFields(): array
    {
        $addon = \rex_addon::get('mail_tools');

        return [
            [
                'name' => 'info',
                'type' => 'text',
                'label' => $addon->i18n('cronjob_retry_info_label'),
                'notice' => $addon->i18n('cronjob_retry_info_notice'),
            ],
        ];
    }
}
