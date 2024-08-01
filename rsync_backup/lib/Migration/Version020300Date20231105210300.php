<?php

declare(strict_types = 1);

namespace OCA\RsyncBackup\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version020300Date20231105210300 extends SimpleMigrationStep {
    
    /** @var IDBConnection */
    private $db;
    
    public function __construct(IDBConnection $db) {
        $this->db = $db;
    }
    
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
        $backupLog->addColumn('updated', Types::BIGINT, [
            'notnull' => false,
            'length' => 11
        ]);
        
        return $schema;
    }
    
    /**
     * @param IOutput $output
     * @param Closure $schemaClosure The `Closure` returns a `ISchemaWrapper`
     * @param array $options
     */
    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
        $time = time();
        
        $query = $this->db->getQueryBuilder();
        $query->update('rsync_backup_log')->set('updated', $query->createNamedParameter($time));
        $query->execute();
    }
    
}
