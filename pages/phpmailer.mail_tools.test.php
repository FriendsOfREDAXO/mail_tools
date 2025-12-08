<?php

use FriendsOfRedaxo\MailTools\DomainValidator;

$addon = rex_addon::get('mail_tools');

$testEmail = rex_request('test_email', 'string', '');
$requireMx = rex_request('require_mx', 'bool', false);
$validationResult = null;
$details = [];

// Test durchführen
if ($testEmail !== '' && rex_post('test_submit', 'bool', false)) {
    $validationResult = DomainValidator::validate($testEmail);
    $domain = DomainValidator::extractDomain($testEmail);

    $details = [
        'email' => $testEmail,
        'domain' => $domain,
        'syntax_valid' => $validationResult['syntax'],
        'has_mx' => $validationResult['mx'],
        'has_a' => $validationResult['domain'],
        'result' => $requireMx ? ($validationResult['valid'] && $validationResult['mx']) : $validationResult['valid'],
        'error' => $validationResult['valid'] ? null : $validationResult['message'],
    ];
}

// Formular
$formContent = '
<form action="' . rex_url::currentBackendPage() . '" method="post" class="form-horizontal">
    <div class="form-group">
        <label class="col-sm-2 control-label" for="test_email">' . $addon->i18n('test_email_label') . '</label>
        <div class="col-sm-6">
            <input type="email" class="form-control" id="test_email" name="test_email" 
                   value="' . rex_escape($testEmail) . '" placeholder="user@example.com" required>
        </div>
    </div>
    <div class="form-group">
        <div class="col-sm-offset-2 col-sm-6">
            <label class="checkbox-inline">
                <input type="checkbox" name="require_mx" value="1"' . ($requireMx ? ' checked' : '') . '>
                ' . $addon->i18n('test_require_mx') . '
            </label>
        </div>
    </div>
    <div class="form-group">
        <div class="col-sm-offset-2 col-sm-6">
            <button type="submit" name="test_submit" value="1" class="btn btn-primary">
                <i class="rex-icon fa-check"></i> ' . $addon->i18n('test_button') . '
            </button>
        </div>
    </div>
</form>';

$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', $addon->i18n('test_title'));
$fragment->setVar('body', $formContent, false);
echo $fragment->parse('core/page/section.php');

// Ergebnis anzeigen
if ($validationResult !== null) {
    $isValid = $details['result'];
    $resultClass = $isValid ? 'success' : 'danger';
    $resultIcon = $isValid ? 'fa-check-circle' : 'fa-times-circle';
    $resultText = $isValid ? $addon->i18n('test_result_valid') : $addon->i18n('test_result_invalid');

    $resultContent = '
    <div class="alert alert-' . $resultClass . '">
        <i class="fa ' . $resultIcon . ' fa-2x pull-left" style="margin-right: 15px;"></i>
        <strong style="font-size: 1.2em;">' . $resultText . '</strong>
        ' . ($details['error'] ? '<br><small>' . rex_escape($details['error']) . '</small>' : '') . '
    </div>
    
    <table class="table table-bordered">
        <tbody>
            <tr>
                <th width="200">' . $addon->i18n('test_detail_email') . '</th>
                <td><code>' . rex_escape($details['email']) . '</code></td>
            </tr>
            <tr>
                <th>' . $addon->i18n('test_detail_domain') . '</th>
                <td><code>' . rex_escape($details['domain']) . '</code></td>
            </tr>
            <tr>
                <th>' . $addon->i18n('test_detail_syntax') . '</th>
                <td>' . ($details['syntax_valid'] ? '<span class="text-success">✅ ' . $addon->i18n('test_yes') . '</span>' : '<span class="text-danger">❌ ' . $addon->i18n('test_no') . '</span>') . '</td>
            </tr>
            <tr>
                <th>' . $addon->i18n('test_detail_mx') . '</th>
                <td>' . ($details['has_mx'] ? '<span class="text-success">✅ ' . $addon->i18n('test_yes') . '</span>' : '<span class="text-warning">⚠️ ' . $addon->i18n('test_no') . '</span>') . '</td>
            </tr>
            <tr>
                <th>' . $addon->i18n('test_detail_a') . '</th>
                <td>' . ($details['has_a'] ? '<span class="text-success">✅ ' . $addon->i18n('test_yes') . '</span>' : '<span class="text-danger">❌ ' . $addon->i18n('test_no') . '</span>') . '</td>
            </tr>
        </tbody>
    </table>';

    $fragment = new rex_fragment();
    $fragment->setVar('class', $resultClass, false);
    $fragment->setVar('title', $addon->i18n('test_result_title') . ': ' . rex_escape($testEmail));
    $fragment->setVar('body', $resultContent, false);
    echo $fragment->parse('core/page/section.php');
}

// ============================================
// Test-Report senden
// ============================================

$reportEmail = rex_request('report_email', 'string', rex::getUser()->getValue('email') ?? '');
$reportSent = false;
$reportError = '';

if (rex_post('send_report', 'bool', false) && $reportEmail !== '') {
    if (!filter_var($reportEmail, FILTER_VALIDATE_EMAIL)) {
        $reportError = $addon->i18n('test_report_invalid_email');
    } else {
        // Test-Daten erstellen
        $testFailedEmails = [
            [
                'status' => 'ERROR',
                'timestamp' => time() - 3600,
                'from' => 'noreply@' . (rex::getServer() ?: 'example.com'),
                'to' => 'invalid-user@nonexistent-domain.test',
                'subject' => 'Test Newsletter',
                'message' => 'SMTP Error: The following recipients failed: invalid-user@nonexistent-domain.test',
                'hash' => md5('test1'),
            ],
            [
                'status' => 'ERROR',
                'timestamp' => time() - 7200,
                'from' => 'noreply@' . (rex::getServer() ?: 'example.com'),
                'to' => 'bounced@expired-mailbox.test',
                'subject' => 'Bestellbestätigung #12345',
                'message' => 'Recipient address rejected: User unknown in virtual mailbox table',
                'hash' => md5('test2'),
            ],
            [
                'status' => 'ERROR',
                'timestamp' => time() - 86400,
                'from' => 'noreply@' . (rex::getServer() ?: 'example.com'),
                'to' => 'old-address@deleted-domain.test',
                'subject' => 'Passwort zurücksetzen',
                'message' => 'SMTP-Fehler: Domain not found',
                'hash' => md5('test3'),
            ],
        ];

        $testStatistics = [
            'today' => 2,
            'week' => 5,
            'month' => 12,
            'total' => 47,
        ];

        $htmlReport = \FriendsOfRedaxo\MailTools\ReportGenerator::generateHtmlReport($testFailedEmails, $testStatistics);
        $textReport = \FriendsOfRedaxo\MailTools\ReportGenerator::generateTextReport($testFailedEmails);

        $reportSent = \FriendsOfRedaxo\MailTools\ReportGenerator::sendReport([$reportEmail], $htmlReport, $textReport);

        if (!$reportSent) {
            $reportError = $addon->i18n('test_report_send_error');
        }
    }
}

// Report-Formular
$reportFormContent = '';

if ($reportSent) {
    $reportFormContent .= '<div class="alert alert-success"><i class="fa fa-check"></i> ' . $addon->i18n('test_report_sent', $reportEmail) . '</div>';
}
if ($reportError !== '') {
    $reportFormContent .= '<div class="alert alert-danger"><i class="fa fa-warning"></i> ' . rex_escape($reportError) . '</div>';
}

$reportFormContent .= '
<p>' . $addon->i18n('test_report_desc') . '</p>
<form action="' . rex_url::currentBackendPage() . '" method="post" class="form-horizontal">
    <div class="form-group">
        <label class="col-sm-2 control-label" for="report_email">' . $addon->i18n('test_report_email_label') . '</label>
        <div class="col-sm-6">
            <input type="email" class="form-control" id="report_email" name="report_email" 
                   value="' . rex_escape($reportEmail) . '" placeholder="admin@example.com" required>
        </div>
    </div>
    <div class="form-group">
        <div class="col-sm-offset-2 col-sm-6">
            <button type="submit" name="send_report" value="1" class="btn btn-primary">
                <i class="rex-icon fa-paper-plane"></i> ' . $addon->i18n('test_report_button') . '
            </button>
        </div>
    </div>
</form>';

$fragment = new rex_fragment();
$fragment->setVar('class', 'info', false);
$fragment->setVar('title', $addon->i18n('test_report_title'));
$fragment->setVar('body', $reportFormContent, false);
echo $fragment->parse('core/page/section.php');

// ============================================
// Retry-Test
// ============================================

use FriendsOfRedaxo\MailTools\LogParser;
use FriendsOfRedaxo\MailTools\RetryHandler;

$retryContent = '';

// Retry-fähige E-Mails anzeigen
$retryableEmails = RetryHandler::getRetryableEmails();
$failedEmails = LogParser::getFailedEmails();

// Manueller Retry
if (rex_post('retry_hash', 'string', '') !== '') {
    $retryHash = rex_post('retry_hash', 'string');
    $result = RetryHandler::retry($retryHash);
    if ($result['success']) {
        $retryContent .= '<div class="alert alert-success"><i class="fa fa-check"></i> ' . rex_escape($result['message']) . '</div>';
    } else {
        $retryContent .= '<div class="alert alert-danger"><i class="fa fa-warning"></i> ' . rex_escape($result['message']) . '</div>';
    }
}

// Archiv-Pfad Info
$archiveFolder = \rex_mailer::logFolder();
$archiveExists = is_dir($archiveFolder);

$retryContent .= '<p><strong>' . $addon->i18n('test_retry_archive_path') . ':</strong> <code>' . rex_escape($archiveFolder) . '</code>';
$retryContent .= $archiveExists ? ' <span class="text-success">✅</span>' : ' <span class="text-danger">❌ ' . $addon->i18n('test_retry_archive_missing') . '</span>';
$retryContent .= '</p>';

if (!empty($failedEmails)) {
    $retryContent .= '<table class="table table-striped table-hover">';
    $retryContent .= '<thead><tr>';
    $retryContent .= '<th>' . $addon->i18n('col_time') . '</th>';
    $retryContent .= '<th>' . $addon->i18n('col_recipient') . '</th>';
    $retryContent .= '<th>' . $addon->i18n('col_subject') . '</th>';
    $retryContent .= '<th>' . $addon->i18n('test_retry_type') . '</th>';
    $retryContent .= '<th>EML</th>';
    $retryContent .= '<th>' . $addon->i18n('test_retry_action') . '</th>';
    $retryContent .= '</tr></thead>';
    $retryContent .= '<tbody>';

    $count = 0;
    foreach ($failedEmails as $entry) {
        if (++$count > 10) {
            break; // Maximal 10 anzeigen
        }

        $time = date('d.m.Y H:i', $entry['timestamp']);
        $isTemporary = RetryHandler::isTemporaryError($entry['message']);
        $emlFile = LogParser::findArchiveFile($entry['subject'], $entry['timestamp']);
        $retryInfo = RetryHandler::getRetryInfo($entry['hash']);

        $typeLabel = $isTemporary
            ? '<span class="label label-warning">' . $addon->i18n('test_retry_temporary') . '</span>'
            : '<span class="label label-danger">' . $addon->i18n('test_retry_permanent') . '</span>';

        $emlStatus = $emlFile
            ? '<span class="text-success" title="' . rex_escape(basename($emlFile)) . '">✅</span>'
            : '<span class="text-danger">❌</span>';

        $retryButton = '';
        if ($isTemporary && $emlFile && $retryInfo['retry_count'] < RetryHandler::MAX_RETRIES) {
            $retryButton = '<form method="post" style="display:inline;">';
            $retryButton .= '<input type="hidden" name="retry_hash" value="' . rex_escape($entry['hash']) . '">';
            $retryButton .= '<button type="submit" class="btn btn-xs btn-primary"><i class="fa fa-refresh"></i> Retry';
            if ($retryInfo['retry_count'] > 0) {
                $retryButton .= ' (' . $retryInfo['retry_count'] . '/' . RetryHandler::MAX_RETRIES . ')';
            }
            $retryButton .= '</button></form>';
        } elseif ($retryInfo['retry_count'] >= RetryHandler::MAX_RETRIES) {
            $retryButton = '<span class="text-muted">' . $addon->i18n('test_retry_max_reached') . '</span>';
        }

        $retryContent .= '<tr>';
        $retryContent .= '<td class="text-nowrap">' . rex_escape($time) . '</td>';
        $retryContent .= '<td>' . rex_escape($entry['to']) . '</td>';
        $retryContent .= '<td>' . rex_escape(mb_substr($entry['subject'], 0, 30)) . '</td>';
        $retryContent .= '<td>' . $typeLabel . '</td>';
        $retryContent .= '<td>' . $emlStatus . '</td>';
        $retryContent .= '<td>' . $retryButton . '</td>';
        $retryContent .= '</tr>';
    }

    $retryContent .= '</tbody></table>';
} else {
    $retryContent .= '<div class="alert alert-info">' . $addon->i18n('no_errors_found') . '</div>';
}

$fragment = new rex_fragment();
$fragment->setVar('class', 'warning', false);
$fragment->setVar('title', $addon->i18n('test_retry_title') . ' <small>(' . count($retryableEmails) . ' ' . $addon->i18n('test_retry_available') . ')</small>', false);
$fragment->setVar('body', $retryContent, false);
echo $fragment->parse('core/page/section.php');

// ============================================
// Beispiel-Adressen
// ============================================

// Beispiel-Adressen zum Testen
$examplesContent = '
<p>' . $addon->i18n('test_examples_desc') . '</p>
<div class="row">
    <div class="col-sm-6">
        <h4 class="text-success">' . $addon->i18n('test_examples_valid') . '</h4>
        <ul>
            <li><code>info@google.com</code></li>
            <li><code>test@github.com</code></li>
            <li><code>mail@redaxo.org</code></li>
        </ul>
    </div>
    <div class="col-sm-6">
        <h4 class="text-danger">' . $addon->i18n('test_examples_invalid') . '</h4>
        <ul>
            <li><code>test@invalid-domain-xyz123.com</code></li>
            <li><code>user@</code></li>
            <li><code>notanemail</code></li>
        </ul>
    </div>
</div>';

$fragment = new rex_fragment();
$fragment->setVar('title', $addon->i18n('test_examples_title'));
$fragment->setVar('body', $examplesContent, false);
echo $fragment->parse('core/page/section.php');
