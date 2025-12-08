<?php

/**
 * Mail Tools - Install Script.
 *
 * @var rex_addon $this
 */

// Tabelle für bereits gemeldete Fehler
rex_sql_table::get(rex::getTable('mail_tools_reported'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('log_hash', 'varchar(64)', false))
    ->ensureColumn(new rex_sql_column('recipient', 'varchar(255)', true))
    ->ensureColumn(new rex_sql_column('subject', 'varchar(255)', true))
    ->ensureColumn(new rex_sql_column('error_message', 'text', true))
    ->ensureColumn(new rex_sql_column('log_timestamp', 'datetime', true))
    ->ensureColumn(new rex_sql_column('reported_at', 'datetime', true))
    ->ensureIndex(new rex_sql_index('log_hash', ['log_hash'], rex_sql_index::UNIQUE))
    ->ensure();

// Tabelle für Retry-Status
rex_sql_table::get(rex::getTable('mail_tools_retry'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('log_hash', 'varchar(64)', false))
    ->ensureColumn(new rex_sql_column('retry_count', 'int(11)', false, '0'))
    ->ensureColumn(new rex_sql_column('last_retry_at', 'datetime', true))
    ->ensureColumn(new rex_sql_column('next_retry_at', 'datetime', true))
    ->ensureColumn(new rex_sql_column('last_success', 'tinyint(1)', false, '0'))
    ->ensureIndex(new rex_sql_index('log_hash', ['log_hash'], rex_sql_index::UNIQUE))
    ->ensure();
