<?php

/**
 * Mail Tools - DSGVO Export.
 *
 * @var rex_addon $this
 */

$addon = rex_addon::get('mail_tools');
$content = '';

if (rex_post('export_gdpr', 'bool')) {
    $email = rex_post('email', 'string');
    
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $logs = \FriendsOfRedaxo\MailTools\GdprTools::exportLogsForEmail($email);
        
        if (empty($logs)) {
            echo rex_view::info('Keine Einträge für diese E-Mail-Adresse gefunden.');
        } else {
            // CSV Download
            $filename = 'mail_log_export_' . date('Ymd_His') . '.csv';
            
            // Buffer leeren, um sauberen Download zu garantieren
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Status', 'Timestamp', 'From', 'To', 'Subject', 'Message']);
            
            foreach ($logs as $log) {
                fputcsv($out, [
                    $log['status'],
                    date('Y-m-d H:i:s', $log['timestamp']),
                    $log['from'],
                    $log['to'],
                    $log['subject'],
                    $log['message']
                ]);
            }
            
            fclose($out);
            exit;
        }
    } else {
        echo rex_view::warning('Bitte geben Sie eine gültige E-Mail-Adresse ein.');
    }
}

$content .= '
<form action="' . rex_url::currentBackendPage() . '" method="post">
    <fieldset>
        <legend>Log-Export (Auskunftsrecht)</legend>
        
        <div class="form-group">
            <label for="email">E-Mail-Adresse</label>
            <input type="email" class="form-control" id="email" name="email" required>
            <p class="help-block">Exportiert alle gefundenen Log-Einträge für diese Adresse als CSV.</p>
        </div>
        
        <div class="form-group">
            <button type="submit" name="export_gdpr" value="1" class="btn btn-primary">
                <i class="rex-icon fa-download"></i> Exportieren
            </button>
        </div>
    </fieldset>
</form>';

$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', 'DSGVO Export', false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
