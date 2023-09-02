# MySql Backup Lite Class

MySql Backup Lite is a class written in **PHP** designed to **backup** a **MySql
database** into a file.

Once backup is done you can **download** it or **save** it on the disk.

Forked from [ahierrohdez / MySqlBackupLite](https://github.com/ahierrohdez/MySqlBackupLite) with the following changes/upgrades:

* Contemplate null values
* File compression
* Additional header data
* Select which tables to back up (all by default)
* Select which tables to skip in the backup
* Select which tables will be backed up only structure (no data)

## Examples

### Example 1

Stores a SQL backup file to a writable folder

```php
include('MySqlBackup.php');

$arrayDbConf['host'] = 'dbHost';
$arrayDbConf['user'] = 'dbUser';
$arrayDbConf['pass'] = 'dbPassword';
$arrayDbConf['name'] = 'dbName';

try {
    $bck = new MySqlBackupLite($arrayDbConf);
    $bck->setFileDir('./backups/');
    $bck->setFileName('backupFileNae.sql');
    $bck->backUp();
    $bck->saveToFile();
}
catch(Exception $e) {
    echo $e;
}```
