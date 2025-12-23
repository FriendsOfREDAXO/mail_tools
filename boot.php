<?php

/**
 * Mail Tools - Boot Script.
 *
 * @var rex_addon $this
 */

// PHPMailer Pre-Send Validator registrieren
if (rex_addon::get('phpmailer')->isAvailable()) {
    \FriendsOfRedaxo\MailTools\PreSendValidator::register();
}

// Cronjobs registrieren, wenn Cronjob-Addon verfügbar
if (rex_addon::get('cronjob')->isAvailable()) {
    rex_cronjob_manager::registerType(\FriendsOfRedaxo\MailTools\Cronjob::class);
    rex_cronjob_manager::registerType(\FriendsOfRedaxo\MailTools\CronjobRetry::class);
    rex_cronjob_manager::registerType(\FriendsOfRedaxo\MailTools\CronjobBounces::class);
    rex_cronjob_manager::registerType(\FriendsOfRedaxo\MailTools\CronjobGdpr::class);
}

// YForm Validator registrieren, wenn YForm verfügbar
if (rex_addon::get('yform')->isAvailable()) {
    rex_yform::addTemplatePath($this->getPath('ytemplates'));
}

// Im Backend: CSS nur auf den jeweiligen Seiten laden
if (rex::isBackend() && rex::getUser()) {
    $currentPage = rex_be_controller::getCurrentPage();
    
    if ($currentPage === 'phpmailer/mail_tools/stats') {
        rex_view::addCssFile($this->getAssetsUrl('mail_tools.css'));
    }
    
    if ($currentPage === 'phpmailer/mail_tools/diagnostics') {
        rex_view::addCssFile($this->getAssetsUrl('mail_tools_diagnostics.css'));
    }
}
