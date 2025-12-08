<?php

use FriendsOfRedaxo\MailTools\StatisticsGenerator;

/** @var rex_addon $this */

// Prüfen ob PHPMailer verfügbar
if (!rex_addon::get('phpmailer')->isAvailable()) {
    echo rex_view::error($this->i18n('phpmailer_not_available'));
    return;
}

$stats = new StatisticsGenerator();

// Daten sammeln
$heatmap = $stats->getHeatmapData();
$topRecipients = $stats->getTopRecipientDomains(10);
$topSenders = $stats->getTopSenderDomains(10);
$topSubjects = $stats->getTopSubjects(10);
$successStats = $stats->getSuccessStats();
$timeStats = $stats->getTimeStats();
$dailyStats = $stats->getDailyStats(30);
$ownDomain = $stats->getOwnDomain();

// Wochentage für Heatmap
$weekdays = [
    $this->i18n('stats_sunday'),
    $this->i18n('stats_monday'),
    $this->i18n('stats_tuesday'),
    $this->i18n('stats_wednesday'),
    $this->i18n('stats_thursday'),
    $this->i18n('stats_friday'),
    $this->i18n('stats_saturday'),
];

// Maximaler Wert für Heatmap-Farbskala
$maxHeatmap = 1;
foreach ($heatmap as $day) {
    $maxHeatmap = max($maxHeatmap, max($day));
}

?>

<div class="mail-tools-stats">

<div class="mail-tools-stats-row">
    <div class="mail-tools-stats-box">
        <div class="mail-tools-stats-number"><?= number_format($timeStats['today']) ?></div>
        <div class="mail-tools-stats-label"><?= $this->i18n('stats_today') ?></div>
    </div>
    <div class="mail-tools-stats-box">
        <div class="mail-tools-stats-number"><?= number_format($timeStats['week']) ?></div>
        <div class="mail-tools-stats-label"><?= $this->i18n('stats_week') ?></div>
    </div>
    <div class="mail-tools-stats-box">
        <div class="mail-tools-stats-number"><?= number_format($timeStats['month']) ?></div>
        <div class="mail-tools-stats-label"><?= $this->i18n('stats_month') ?></div>
    </div>
    <div class="mail-tools-stats-box">
        <div class="mail-tools-stats-number"><?= number_format($successStats['total']) ?></div>
        <div class="mail-tools-stats-label"><?= $this->i18n('stats_total') ?></div>
    </div>
</div>

<div class="mail-tools-stats-grid">
    <!-- Erfolgsquote -->
    <div class="mail-tools-stats-card">
        <h3><?= $this->i18n('stats_success_rate') ?></h3>
        <div class="mail-tools-stats-number"><?= $successStats['success_rate'] ?>%</div>
        <div class="mail-tools-success-bar">
            <div class="mail-tools-success-bar-fill" style="width: <?= $successStats['success_rate'] ?>%"></div>
        </div>
        <div class="mail-tools-success-stats">
            <span>✓ <?= number_format($successStats['success']) ?> <?= $this->i18n('stats_successful') ?></span>
            <span>✗ <?= number_format($successStats['failed']) ?> <?= $this->i18n('stats_failed') ?></span>
        </div>
    </div>

    <!-- Versand letzte 30 Tage -->
    <div class="mail-tools-stats-card">
        <h3><?= $this->i18n('stats_last_30_days') ?></h3>
        <?php
        $maxDaily = max(1, max($dailyStats));
        ?>
        <div class="mail-tools-daily-chart">
            <?php foreach ($dailyStats as $date => $count): ?>
                <?php
                $height = ($count / $maxDaily) * 100;
                $formattedDate = date('d.m.', strtotime($date));
                ?>
                <div class="mail-tools-daily-bar" 
                     style="height: <?= $height ?>%"
                     data-tooltip="<?= $formattedDate ?>: <?= $count ?> E-Mails"></div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Heatmap -->
<div class="mail-tools-stats-card" style="margin-bottom: 20px;">
    <h3><?= $this->i18n('stats_heatmap_title') ?></h3>
    <?php if (!empty($ownDomain)): ?>
        <p class="mail-tools-help-block">
            <?= $this->i18n('stats_own_domain_excluded', $ownDomain) ?>
        </p>
    <?php endif; ?>
    <div class="mail-tools-heatmap-container">
        <table class="mail-tools-heatmap-table">
            <thead>
                <tr>
                    <th></th>
                    <?php for ($h = 0; $h < 24; ++$h): ?>
                        <th><?= $h ?></th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($weekdays as $dayIndex => $dayName): ?>
                    <tr>
                        <th><?= rex_escape($dayName) ?></th>
                        <?php for ($h = 0; $h < 24; ++$h): ?>
                            <?php
                            $count = $heatmap[$dayIndex][$h];
                            $intensity = $maxHeatmap > 0 ? ($count / $maxHeatmap) : 0;
                            if ($count === 0) {
                                $level = 0;
                            } elseif ($intensity <= 0.25) {
                                $level = 1;
                            } elseif ($intensity <= 0.5) {
                                $level = 2;
                            } elseif ($intensity <= 0.75) {
                                $level = 3;
                            } else {
                                $level = 4;
                            }
                            ?>
                            <td class="mail-tools-heatmap-cell mail-tools-heatmap-cell-<?= $level ?>" title="<?= $dayName ?> <?= $h ?>:00 - <?= $count ?> E-Mails">
                                <?= $count ?: '' ?>
                            </td>
                        <?php endfor; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="mail-tools-stats-grid">
    <!-- Top Empfänger-Domains -->
    <div class="mail-tools-stats-card">
        <h3><?= $this->i18n('stats_top_recipient_domains') ?></h3>
        <?php if (empty($topRecipients)): ?>
            <p class="mail-tools-text-muted"><?= $this->i18n('stats_no_data') ?></p>
        <?php else: ?>
            <ul class="mail-tools-top-list">
                <?php foreach ($topRecipients as $domain => $count): ?>
                    <li>
                        <span class="mail-tools-top-list-domain"><?= rex_escape($domain) ?></span>
                        <span class="mail-tools-top-list-count"><?= number_format($count) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- Top Absender-Domains -->
    <div class="mail-tools-stats-card">
        <h3><?= $this->i18n('stats_top_sender_domains') ?></h3>
        <p class="mail-tools-help-block">
            <?= $this->i18n('stats_sender_domains_hint') ?>
        </p>
        <?php if (empty($topSenders)): ?>
            <p class="mail-tools-text-muted"><?= $this->i18n('stats_no_data') ?></p>
        <?php else: ?>
            <ul class="mail-tools-top-list">
                <?php foreach ($topSenders as $domain => $count): ?>
                    <li>
                        <span class="mail-tools-top-list-domain"><?= rex_escape($domain) ?></span>
                        <span class="mail-tools-top-list-count"><?= number_format($count) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- Top Betreffs -->
    <div class="mail-tools-stats-card">
        <h3><?= $this->i18n('stats_top_subjects') ?></h3>
        <?php if (empty($topSubjects)): ?>
            <p class="mail-tools-text-muted"><?= $this->i18n('stats_no_data') ?></p>
        <?php else: ?>
            <ul class="mail-tools-top-list">
                <?php foreach ($topSubjects as $subject => $count): ?>
                    <li>
                        <span class="mail-tools-top-list-subject" title="<?= rex_escape($subject) ?>"><?= rex_escape($subject) ?></span>
                        <span class="mail-tools-top-list-count"><?= number_format($count) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

</div>
