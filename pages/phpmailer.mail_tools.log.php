<?php

use FriendsOfRedaxo\MailTools\LogParser;

$addon = rex_addon::get('mail_tools');

// Prüfen ob PHPMailer installiert ist
if (!rex_addon::get('phpmailer')->isAvailable()) {
    echo rex_view::error($addon->i18n('phpmailer_not_available'));
    return;
}

// Zeitfilter
$filter = rex_request('filter', 'string', '24h');
$filterOptions = [
    '1h' => $addon->i18n('filter_1h'),
    '6h' => $addon->i18n('filter_6h'),
    '24h' => $addon->i18n('filter_24h'),
    '7d' => $addon->i18n('filter_7d'),
    '30d' => $addon->i18n('filter_30d'),
    'all' => $addon->i18n('filter_all'),
];

// Zeitraum berechnen
$since = match ($filter) {
    '1h' => time() - 3600,
    '6h' => time() - 21600,
    '24h' => time() - 86400,
    '7d' => time() - 604800,
    '30d' => time() - 2592000,
    default => 0,
};

// Filter-Formular
$filterForm = '<form action="' . rex_url::currentBackendPage() . '" method="get" class="form-inline">';
$filterForm .= '<div class="form-group" style="margin-right: 10px;">';
$filterForm .= '<label for="filter" style="margin-right: 5px;">' . $addon->i18n('filter_label') . '</label>';
$filterForm .= '<select name="filter" id="filter" class="form-control" onchange="this.form.submit()">';
foreach ($filterOptions as $value => $label) {
    $selected = $filter === $value ? ' selected' : '';
    $filterForm .= '<option value="' . $value . '"' . $selected . '>' . $label . '</option>';
}
$filterForm .= '</select>';
$filterForm .= '</div>';
$filterForm .= '</form>';

// Log-Daten laden
try {
    $parser = new LogParser();
    $failedEmails = $parser->getFailedEmails($since);
    $statistics = $parser->getStatistics();
} catch (Exception $e) {
    echo rex_view::error($e->getMessage());
    return;
}

// Statistik-Boxen
$statsContent = '<div class="row">';
$statsContent .= '<div class="col-sm-3"><div class="panel panel-default"><div class="panel-body text-center">';
$statsContent .= '<h2 style="margin:0;color:#d9534f;">' . $statistics['today'] . '</h2>';
$statsContent .= '<small class="text-muted">' . $addon->i18n('stats_today') . '</small>';
$statsContent .= '</div></div></div>';

$statsContent .= '<div class="col-sm-3"><div class="panel panel-default"><div class="panel-body text-center">';
$statsContent .= '<h2 style="margin:0;color:#f0ad4e;">' . $statistics['week'] . '</h2>';
$statsContent .= '<small class="text-muted">' . $addon->i18n('stats_week') . '</small>';
$statsContent .= '</div></div></div>';

$statsContent .= '<div class="col-sm-3"><div class="panel panel-default"><div class="panel-body text-center">';
$statsContent .= '<h2 style="margin:0;color:#5bc0de;">' . $statistics['month'] . '</h2>';
$statsContent .= '<small class="text-muted">' . $addon->i18n('stats_month') . '</small>';
$statsContent .= '</div></div></div>';

$statsContent .= '<div class="col-sm-3"><div class="panel panel-default"><div class="panel-body text-center">';
$statsContent .= '<h2 style="margin:0;color:#777;">' . $statistics['total'] . '</h2>';
$statsContent .= '<small class="text-muted">' . $addon->i18n('stats_total') . '</small>';
$statsContent .= '</div></div></div>';
$statsContent .= '</div>';

echo $statsContent;

// Tabelle mit Fehlern
$content = '';

if (empty($failedEmails)) {
    $content .= '<div class="alert alert-success">';
    $content .= '<i class="rex-icon fa-check"></i> ' . $addon->i18n('no_errors_found');
    $content .= '</div>';
} else {
    $content .= '<table class="table table-striped table-hover" style="table-layout: fixed;">';
    $content .= '<thead><tr>';
    $content .= '<th style="width: 140px;">' . $addon->i18n('col_time') . '</th>';
    $content .= '<th style="width: 200px;">' . $addon->i18n('col_recipient') . '</th>';
    $content .= '<th style="width: 200px;">' . $addon->i18n('col_subject') . '</th>';
    $content .= '<th>' . $addon->i18n('col_error') . '</th>';
    $content .= '</tr></thead>';
    $content .= '<tbody>';

    foreach ($failedEmails as $entry) {
        $time = date('d.m.Y H:i:s', $entry['timestamp']);
        $content .= '<tr>';
        $content .= '<td class="text-nowrap">' . rex_escape($time) . '</td>';
        $content .= '<td style="word-break: break-all;">' . rex_escape($entry['to']) . '</td>';
        $content .= '<td style="word-break: break-word;">' . rex_escape($entry['subject']) . '</td>';
        $content .= '<td style="word-break: break-word;"><code style="white-space: pre-wrap; word-break: break-word;">' . rex_escape($entry['message']) . '</code></td>';
        $content .= '</tr>';
    }

    $content .= '</tbody></table>';
}

$fragment = new rex_fragment();
$fragment->setVar('title', $addon->i18n('log_title') . ' <small>(' . count($failedEmails) . ' ' . $addon->i18n('entries') . ')</small>', false);
$fragment->setVar('options', $filterForm, false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
