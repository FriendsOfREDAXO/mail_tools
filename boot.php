<?php

/**
 * Mail Tools - Boot Script.
 *
 * @var rex_addon $this
 */

// Cronjobs registrieren, wenn Cronjob-Addon verfügbar
if (rex_addon::get('cronjob')->isAvailable()) {
    rex_cronjob_manager::registerType(\FriendsOfRedaxo\MailTools\Cronjob::class);
    rex_cronjob_manager::registerType(\FriendsOfRedaxo\MailTools\CronjobRetry::class);
}

// YForm Validator registrieren, wenn YForm verfügbar
if (rex_addon::get('yform')->isAvailable()) {
    rex_yform::addTemplatePath($this->getPath('ytemplates'));
}

// Im Backend: Statistik-Widget für Dashboard
if (rex::isBackend() && rex::getUser()) {
    // Extension Point für Dashboard-Integration könnte hier folgen
}
