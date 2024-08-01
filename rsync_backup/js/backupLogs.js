OC.RsyncBackup.onContentPresent(element => {
    
    let table = OC.RsyncBackup.createTable({
        id: 'backupLogs',
        appName: 'rsync_backup',
        container: $(element),
        title: 'Backup logs',
        dataSource: '/apps/rsync_backup/action/get-backup-logs',
        mapSource: data => {
            let mappedData = {};
            mappedData.count = data.count;
            mappedData.table = [];
            data.table.forEach(item => {
                let row = {};
                row.id = item.id;
                let updated = new Date(item.updated / 1000);
                row.updated = updated.toLocaleString();
                let startTime = new Date(item.startTime / 1000);
                row.startTime = startTime.toLocaleString();
                let endTime = item.endTime === null ? null : new Date(item.endTime / 1000);
                row.endTime = endTime === null ? '' : endTime.toLocaleString();
                let status = { running: 'Running', completed: 'Completed', aborted: 'Aborted' }[item.status];
                row.status = t('rsync_backup', status);
                let statusClass = item.errors ? 'error' : (item.warnings || item.status !== 'completed' ? 'warning' : (item.successes ? 'success' : ''));
                row.statusClass = statusClass;
                mappedData.table.push(row);
            });
            return mappedData;
        },
        columns: [
            { label: 'Updated', attr: 'updated' },
            { label: 'Start time', attr: 'startTime' },
            { label: 'End time', attr: 'endTime' },
            { label: 'Status', attr: 'status', class: 'statusClass' }
        ],
        row: { link: { title: 'Show detail', url: item => `/apps/rsync_backup/backup-log/${item.id}` } }
    });
    
    table.init();
    
});
