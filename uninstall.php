<?php

/**
 * Mail Tools - Uninstall Script.
 *
 * @var rex_addon $this
 */

// Tabellen löschen
rex_sql_table::get(rex::getTable('mail_tools_reported'))->drop();
rex_sql_table::get(rex::getTable('mail_tools_retry'))->drop();
