<?php

declare(strict_types = 1);

namespace OCA\RsyncBackup\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version020301Date20231111182700 extends SimpleMigrationStep {
    
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
        $backupLog->getColumn('start_time')->setOptions([
            'length' => 20
        ]);
        $backupLog->getColumn('end_time')->setOptions([
            'length' => 20
        ]);
        
        $backupLogMessage = $schema->getTable('rsync_backup_log_message');
        $backupLogMessage->getColumn('time')->setOptions([
            'length' => 20
        ]);
        
        return $schema;
    }
    
    /**
     * @param IOutput $output
     * @param Closure $schemaClosure The `Closure` returns a `ISchemaWrapper`
     * @param array $options
     */
    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
        $query1 = $this->db->getQueryBuilder();
        $query1->update('rsync_backup_log')->set('updated', $query1->createFunction('updated * 1000000'));
        $query1->execute();
        
        $query2 = $this->db->getQueryBuilder();
        $query2->update('rsync_backup_log')->set('start_time', $query1->createFunction('start_time * 1000000'));
        $query2->execute();
        
        $query3 = $this->db->getQueryBuilder();
        $query3->update('rsync_backup_log')->set('end_time', $query1->createFunction('end_time * 1000000'))->where(
            $query3->expr()->isNotNull('end_time')
        );
        $query3->execute();
        
        $query4 = $this->db->getQueryBuilder();
        $query4->update('rsync_backup_log_message')->set('time', $query1->createFunction('time * 1000000'));
        $query4->execute();
    }
    
}
