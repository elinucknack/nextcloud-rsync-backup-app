# Rsync Backup

This is the documentation of Rsync Backup, a Nextcloud application for Nextcloud backuping using rsync!

This application enables to backup Nextcloud (DB, application folder, data folder) using rsync to local or remote machine. The backups are incremental (the newswet backup is full, the rest contains only the changed files). The backup can run as an Nextcloud job (every day once), or you can launch it manually using the command `rsync-backup:backup`.

The following steps describe the installation of Rsync Backup in Nextcloud.

## Prerequisite

- Nextcloud (version 27-28)
- Postgres
- Rsync

**Note:** Tested with Debian/Raspberry Pi OS

## Install the app

1. Copy the `rsync_backup` into Nextcloud's `apps` folder.
2. Add the backup configuration to `config/config.php` (can be later changed in the backup settings through the Nextcloud web interface):
   - `rsync_backup_disabled`: Set to `false` if you want to enable the backuping.
   - `rsync_backup_database_dump_directory`: The local location of DB dump directory.
   - `rsync_backup_database_backup_directory`: The location of DB dump backup directory, format `[[user@]hostname:]/path`.
   - `rsync_backup_database_backup_ssh_auth.password`: In case the DB dump backup directory is located on the remote machine, this is the password of the remote user.
   - `rsync_backup_application_backup_directory`: The location of the application directory backup, format `[[user@]hostname:]/path`.
   - `rsync_backup_application_backup_ssh_auth.password`: In case the application backup directory is located on the remote machine, this is the password of the remote user.
   - `rsync_backup_data_backup_directory`: The location of the data directory backup, format `[[user@]hostname:]/path`.
   - `rsync_backup_data_backup_ssh_auth.password`: In case the data backup directory is located on the remote machine, this is the password of the remote user.
   - `rsync_backup_delete_old_backups`: The interval (in seconds) after which the backup is deleted.
   - `rsync_backup_delete_old_backup_logs`: The interval (in seconds) after which the backup log is deleted.
   - `rsync_backup_notification_recipients`: The list of backup log email recipients.
3. Go to the application administration and click to install Rsync Backup.

## How to user

1. Scheduled job: Runs every day when the `rsync_backup_disabled` is set to `false`.
2. OCC command `rsync-backup:backup`: The following params are available:
   - `--dump-database`: Dump the DB
   - `--backup-database`: Backup the DB
   - `--backup-application`: Backup the application folder
   - `--backup-data`: Backup the data folder
   - `--delete-old-backups=x`: Delete backups older than `x` seconds

## Web interface

A web interface to change the Rsync Backup settings is available (available through the app icon in the top menu). You can also see here the list of running and completed backups including their runtime log.

## Authors

- [**Eli Nucknack**](mailto:eli.nucknack@gmail.com)
