<?php

declare(strict_types = 1);

return [
    'routes' => [
        ['name' => 'page#backupLogs', 'url' => '/backup-logs', 'verb' => 'GET'],
        ['name' => 'page#backupLog', 'url' => '/backup-log/{id}', 'verb' => 'GET'],
        ['name' => 'page#settings', 'url' => '/settings', 'verb' => 'GET'],
        ['name' => 'backup#getBackupLogs', 'url' => '/action/get-backup-logs', 'verb' => 'POST'],
        ['name' => 'backup#getBackupLog', 'url' => '/action/get-backup-log/{id}', 'verb' => 'POST'],
        ['name' => 'backup#getNavigation', 'url' => '/action/get-navigation', 'verb' => 'POST'],
        ['name' => 'backup#getSettings', 'url' => '/action/get-settings', 'verb' => 'POST'],
        ['name' => 'backup#setSettings', 'url' => '/action/set-settings', 'verb' => 'POST'],
    ]
];
