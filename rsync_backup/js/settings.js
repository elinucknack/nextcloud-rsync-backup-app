OC.RsyncBackup.onContentPresent(element => {
    
    let form = OC.RsyncBackup.createForm({
        id: 'settingsData',
        appName: 'rsync_backup',
        dataSource: '/apps/rsync_backup/action/get-settings',
        mapSource: data => {
            data.databaseBackupSshAuth.type = data.databaseBackupSshAuth.hasOwnProperty('password') ? 'password' : (data.databaseBackupSshAuth.hasOwnProperty('keyFile') ? 'keyFile' : 'none');
            data.applicationBackupSshAuth.type = data.applicationBackupSshAuth.hasOwnProperty('password') ? 'password' : (data.applicationBackupSshAuth.hasOwnProperty('keyFile') ? 'keyFile' : 'none');
            data.dataBackupSshAuth.type = data.dataBackupSshAuth.hasOwnProperty('password') ? 'password' : (data.dataBackupSshAuth.hasOwnProperty('keyFile') ? 'keyFile' : 'none');
            return data;
        },
        render: () => {
            $(element).empty();
            
            form.createHeader('settingsHeader', 2, { text: 'Settings' }).appendTo(element);
            
            let settingsDataSection = form.createSection('settings-data').appendTo(element);
            
            form.createCheckbox('disabled', 'Backup disabled').appendTo(settingsDataSection);
            form.createTextInput('databaseDumpDirectory', 'Database dump directory').appendTo(settingsDataSection);
            form.createTextInput('databaseBackupDirectory', 'Database backup directory').appendTo(settingsDataSection);
            form.createSelect('databaseBackupSshAuth.type', 'Database backup SSH authentication type', {
                options: () => ({ none: 'None', password: 'Password', keyFile: 'Key file' })
            }).appendTo(settingsDataSection);
            form.createTextInput('databaseBackupSshAuth.keyFile', 'Database backup SSH key file', {
                hidden: data => data.databaseBackupSshAuth.type !== 'keyFile'
            }).appendTo(settingsDataSection);
            form.createPasswordInput('databaseBackupSshAuth.password', 'Database backup SSH password', {
                hidden: data => data.databaseBackupSshAuth.type !== 'password'
            }).appendTo(settingsDataSection);
            form.createTextInput('applicationBackupDirectory', 'Application backup directory').appendTo(settingsDataSection);
            form.createSelect('applicationBackupSshAuth.type', 'Application backup SSH authentication type', {
                options: () => ({ none: 'None', password: 'Password', keyFile: 'Key file' })
            }).appendTo(settingsDataSection);
            form.createTextInput('applicationBackupSshAuth.keyFile', 'Application backup SSH key file', {
                hidden: data => data.applicationBackupSshAuth.type !== 'keyFile'
            }).appendTo(settingsDataSection);
            form.createPasswordInput('applicationBackupSshAuth.password', 'Application backup SSH password', {
                hidden: data => data.applicationBackupSshAuth.type !== 'password'
            }).appendTo(settingsDataSection);
            form.createTextInput('dataBackupDirectory', 'Data backup directory').appendTo(settingsDataSection);
            form.createSelect('dataBackupSshAuth.type', 'Data backup SSH authentication type', {
                options: () => ({ none: 'None', password: 'Password', keyFile: 'Key file' })
            }).appendTo(settingsDataSection);
            form.createTextInput('dataBackupSshAuth.keyFile', 'Data backup SSH key file', {
                hidden: data => data.dataBackupSshAuth.type !== 'keyFile'
            }).appendTo(settingsDataSection);
            form.createPasswordInput('dataBackupSshAuth.password', 'Data backup SSH password', {
                hidden: data => data.dataBackupSshAuth.type !== 'password'
            }).appendTo(settingsDataSection);
            form.createTextInputArray('notificationRecipients', 'Notification recipients').appendTo(settingsDataSection);
            form.createSubmitButton('save', 'Save', {
                mapTarget: data => {
                    let settings = {};
                    settings.disabled = data.disabled;
                    settings.databaseDumpDirectory = data.databaseDumpDirectory.trim();
                    settings.databaseBackupDirectory = data.databaseBackupDirectory.trim();
                    if (data.databaseBackupSshAuth.type === 'keyFile') {
                        settings.databaseBackupSshAuth = { keyFile: data.databaseBackupSshAuth.keyFile.trim() };
                    } else if (data.databaseBackupSshAuth.type === 'password') {
                        settings.databaseBackupSshAuth = { password: data.databaseBackupSshAuth.password.trim() };
                    } else {
                        settings.databaseBackupSshAuth = {};
                    }
                    settings.applicationBackupDirectory = data.applicationBackupDirectory.trim();
                    if (data.applicationBackupSshAuth.type === 'keyFile') {
                        settings.applicationBackupSshAuth = { keyFile: data.applicationBackupSshAuth.keyFile.trim() };
                    } else if (data.applicationBackupSshAuth.type === 'password') {
                        settings.applicationBackupSshAuth = { password: data.applicationBackupSshAuth.password.trim() };
                    } else {
                        settings.applicationBackupSshAuth = {};
                    }
                    settings.dataBackupDirectory = data.dataBackupDirectory.trim();
                    if (data.dataBackupSshAuth.type === 'keyFile') {
                        settings.dataBackupSshAuth = { keyFile: data.dataBackupSshAuth.keyFile.trim() };
                    } else if (data.dataBackupSshAuth.type === 'password') {
                        settings.dataBackupSshAuth = { password: data.dataBackupSshAuth.password.trim() };
                    } else {
                        settings.dataBackupSshAuth = {};
                    }
                    settings.notificationRecipients = data.notificationRecipients.map(recipient => recipient.trim());
                    return { settings };
                },
                dataTarget: '/apps/rsync_backup/action/set-settings'
            }).appendTo(settingsDataSection);
        }
    });
    
    form.init();
    
});
