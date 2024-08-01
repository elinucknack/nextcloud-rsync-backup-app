<?php

declare(strict_types = 1);

namespace OCA\RsyncBackup\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version020000Date20230924210200 extends SimpleMigrationStep {
    
    /**
     * @param IOutput $output
     * @param Closure $schemaClosure The `Closure` returns a `ISchemaWrapper`
     * @param array $options
     * @return null|ISchemaWrapper
     */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        
        $backupLog = $schema->createTable('rsync_backup_log');
        $backupLog->addColumn('id', Types::BIGINT, [
            'autoincrement' => true,
            'notnull' => true,
            'length' => 20,
            'unsigned' => true
        ]);
        $backupLog->addColumn('pid', Types::BIGINT, [
            'notnull' => true,
            'length' => 11,
            'unsigned' => true
        ]);
        $backupLog->addColumn('start_time', Types::BIGINT, [
            'notnull' => true,
            'length' => 11,
            'unsigned' => true
        ]);
        $backupLog->addColumn('end_time', Types::BIGINT, [
            'notnull' => false,
            'length' => 11,
            'unsigned' => true
        ]);
        $backupLog->addColumn('status', Types::STRING, [
            'notnull' => true,
            'length' => 10
        ]);
        $backupLog->setPrimaryKey(['id']);
        
        $backupLogMessage = $schema->createTable('rsync_backup_log_message');
        $backupLogMessage->addColumn('id', Types::BIGINT, [
            'autoincrement' => true,
            'notnull' => true,
            'length' => 20,
            'unsigned' => true
        ]);
        $backupLogMessage->addColumn('log_id', Types::BIGINT, [
            'notnull' => true,
            'length' => 20,
            'unsigned' => true
        ]);
        $backupLogMessage->addColumn('time', Types::BIGINT, [
            'notnull' => true,
            'length' => 11,
            'unsigned' => true
        ]);
        $backupLogMessage->addColumn('type', Types::STRING, [
            'notnull' => true,
            'length' => 10
        ]);
        $backupLogMessage->addColumn('message', Types::TEXT, [
            'notnull' => true
        ]);
        $backupLogMessage->setPrimaryKey(['id']);
        $backupLogMessage->addForeignKeyConstraint($backupLog, ['log_id'], ['id']);
        
        return $schema;
    }
    
}
