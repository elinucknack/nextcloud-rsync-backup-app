<?php

declare(strict_types = 1);

namespace OCA\RsyncBackup\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version020300Date20231105211900 extends SimpleMigrationStep {
    
    /**
     * @param IOutput $output
     * @param Closure $schemaClosure The `Closure` returns a `ISchemaWrapper`
     * @param array $options
     * @return null|ISchemaWrapper
     */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        
        $backupLog = $schema->getTable('rsync_backup_log');
        $backupLog->getColumn('updated')->setOptions([
            'notnull' => true
        ]);
        
        return $schema;
    }
    
}
