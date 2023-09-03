<?php

/**
*
* MySql Backup Lite Class
*
* @author     Gonzalo Iglesias
* @license    http://www.apache.org/licenses/LICENSE-2.0  Apache License, Version 2.0
* @version    0.0.2
* @link       https://grisinformatica.com/
*
*
*
* Copyright 2023 Gonzalo Iglesias
*
* Licensed under the Apache License, Version 2.0 (the "License");
* you may not use this file except in compliance with the License.
* You may obtain a copy of the License at
*
*     http://www.apache.org/licenses/LICENSE-2.0
*
* Unless required by applicable law or agreed to in writing, software
* distributed under the License is distributed on an "AS IS" BASIS,
* WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
* See the License for the specific language governing permissions and
* limitations under the License.
*
*/

class MySqlBackupLite
{
    const VERSION               = '0.0.2';
    const LANGUAGE              = 'es';     // Default: 'en'
    const ROWS_PER_INSERT       = 100;      // Default: 100

    private $host;
    private $user;
    private $pass;
    private $name;

    private $lang;
    private $fileName           = "mySqlBackup.sql";
    private $fileDir            = "./";
    private $fileCompression    = false;

    private $timeZone           = '+00:00';

    private $mySqli;
    private $sqlString          = "";

    /**
     * Array of tables to backup.
     * If left blank, all tables will be backuped
     * ex: ['myTable1', 'myTable2']
     */
    private $arrayTables        = array();

    /**
     * Array of tables to ignore in backup
     * ex: ['myTable1', 'myTable2']
     */
    private $arrayIgnoreTables  = [];

    /**
     * Array of tables to ignore data
     * Table structure will be backed up, but no data
     * ex: ['myTable1', 'myTable2']
     */
    private $arrayEmptyTables   = [];

    private $tableFieldCount    = 0;
    private $tableNumberOfRows  = 0;
    private $queryResult;

    public function __construct(array $arrayConnConfig)
    {
        if (! require __DIR__ . '/language/'.self::LANGUAGE.'.php')
            throw new Exception('Missing language file.');

        $this->setLanguage($lang);

        if (
            (!isset($arrayConnConfig['host'])) ||
            (!isset($arrayConnConfig['user'])) ||
            (!isset($arrayConnConfig['pass'])) ||
            (!isset($arrayConnConfig['name']))
        ) {
            throw new Exception($lang['missingConnectionData']);
        }
        $this->setHost($arrayConnConfig['host']);
        $this->setUser($arrayConnConfig['user']);
        $this->setPass($arrayConnConfig['pass']);
        $this->setName($arrayConnConfig['name']);
    }

    public function backUp()
    {
        $this->connectMySql();
        $this->getTables();
        $this->generateSqlHeader();
        $this->createTableStaments();
        $this->generateSqlFooter();
    }

    private function setLanguage($lang)
    {
        $this->lang = $lang;
    }

    private function setHost($host)
    {
        $this->host = $host;
    }

    private function setUser($user)
    {
        $this->user = $user;
    }

    private function setPass($password)
    {
        $this->pass = $password;
    }

    private function setName($name)
    {
        $this->name = $name;
    }

    public function setFileName($name)
    {
        $this->fileName = $name;
    }

    public function setFileDir($dir)
    {
        $this->fileDir = (substr($dir, -1)=='/') ? $dir : $dir.'/';
    }

    public function setFileCompression($compression)
    {
        $this->fileCompression = $compression;
    }

    public function setTables($arrayTables)
    {
        $this->arrayTables = $arrayTables;
    }

    public function setIgnoreTables($arrayIgnoreTables)
    {
        $this->arrayIgnoreTables = $arrayIgnoreTables;
    }

    public function setEmptyTables($arrayEmptyTables)
    {
        $this->arrayEmptyTables = $arrayEmptyTables;
    }

    private function connectMySql()
    {
        $this->mySqli = new mysqli($this->host, $this->user, $this->pass, $this->name);
        $this->mySqli->select_db($this->name);
        $this->mySqli->query("SET NAMES 'utf8'");
    }

    private function getTables()
    {
        if (! $this->arrayTables) {
            $queryTables = $this->mySqli->query('SHOW TABLES');
            while ($row = $queryTables->fetch_row()) {
                if (! in_array($row[0], $this->arrayIgnoreTables))
                    $this->arrayTables[] = $row[0];
            }
        }
    }

    private function generateSqlHeader()
    {
        $this->sqlString  = '-- MySqlBackupLite SQL Dump'. "\r\n";
        $this->sqlString .= '-- '.$this->lang['version'].' '.self::VERSION. "\r\n";
        $this->sqlString .= '-- https://github.com/grisinformatica/MySqlBackupLite'. "\r\n";
        $this->sqlString .= '--'. "\r\n";
        $this->sqlString .= '-- '.$this->lang['host'].': '.$_SERVER['SERVER_NAME']. "\r\n";
        $this->sqlString .= '-- '.$this->lang['generationTime'].': '.date('d-m-Y \a \l\a\s H:i:s') . "\r\n";
        $this->sqlString .= '-- '.$this->lang['serverVersion'].': '.$_SERVER['SERVER_SOFTWARE'].'-'.$this->mySqli->server_version. "\r\n";
        $this->sqlString .= '-- '.$this->lang['phpVersion'].': '.phpversion(). "\r\n\r\n";

        $this->sqlString .= 'SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";' . "\r\n";
        $this->sqlString .= 'START TRANSACTION;' . "\r\n";
        $this->sqlString .= 'SET time_zone = "' . $this->timeZone . '";' . "\r\n\r\n\r\n";

        $this->sqlString .= '/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;' . "\r\n";
        $this->sqlString .= '/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;' . "\r\n";
        $this->sqlString .= '/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;' . "\r\n";
        $this->sqlString .= '/*!40101 SET NAMES utf8 */;' . "\r\n\r\n";
        $this->sqlString .= '--' . "\r\n";
        $this->sqlString .= '-- '.$this->lang['database'].': `' . $this->name . '`' . "\r\n";
        $this->sqlString .= '--' . "\r\n\r\n";
    
        return;
    }

    private function createTableStaments()
    {
        foreach ($this->arrayTables as $table) {
            $this->sqlCreateTableStament($table);
            if (! in_array($table, $this->arrayEmptyTables))
                $this->sqlInsertStaments($table);
        }
    }

    private function sqlCreateTableStament($table)
    {
        $res = $this->mySqli->query('SHOW CREATE TABLE '.$table);
        $temp = $res->fetch_row();

        $this->sqlString .= '-- --------------------------------------------------------' . "\r\n\r\n";
        $this->sqlString .= '--' . "\r\n";
        $this->sqlString .= '-- '.$this->lang['tableStructure'].' '.$this->lang['forTable'].' `'.$table.'`' . "\r\n";
        $this->sqlString .= '--'  . "\r\n";
        $this->sqlString .= "\r\n" . str_ireplace('CREATE TABLE `','CREATE TABLE IF NOT EXISTS `', $temp[1]) . ";\n\n";
    }

    private function sqlInsertStaments($table)
    {
        $this->getTableData($table);

        if ($this->tableNumberOfRows == 0) {
            return;
        }

        $this->sqlString .= '--' . "\r\n";
        $this->sqlString .= '-- '.$this->lang['dumpingData'].' '.$this->lang['forTable'].' `'.$table.'`'  . "\r\n";
        $this->sqlString .= '--' . "\r\n";

        $i = 0;
        while ($row = $this->queryResult->fetch_row()) {
            $this->insertResultHeader($table, $i);
            $this->insertSingleResult($row);
            
            $i++;
            
            $this->sqlString .= (($i != 0) && ($i % self::ROWS_PER_INSERT == 0) || ($i == $this->tableNumberOfRows)) ? ";" : ",";
        }
        $this->sqlString .= "\n\n\n";
    }

    private function getTableData($table)
    {
        $this->queryResult = $this->mySqli->query('SELECT * FROM `' . $table . '`');
        $this->tableFieldCount = $this->queryResult->field_count;
        $this->tableNumberOfRows = $this->mySqli->affected_rows;
    }

    private function insertResultHeader($table, $currentRowNumber)
    {
        if ($currentRowNumber % self::ROWS_PER_INSERT == 0 || $currentRowNumber == 0 ) {
            $this->sqlString .= "\nINSERT INTO " . $table . " VALUES";
        }
    }

    private function insertSingleResult($row)
    {
        $this->sqlString .= "\n(";

        for($i = 0; $i < $this->tableFieldCount; $i++) {

            $row[$i] = str_replace("\n","\\n", addslashes($row[$i]));

            if ($row[$i] == 'true' or $row[$i] == 'false' or preg_match('/^-?[1-9][0-9]*$/', $row[$i]) or $row[$i] == 'NULL' or $row[$i] == 'null') {
                $this->sqlString .= $row[$i];
            }
            elseif (empty($row[$i])) {
                $this->sqlString .= 'NULL' ;
            }
            else {
                $this->sqlString .= '"'.$row[$i].'"' ;
            }

            if ($i < ($this->tableFieldCount-1)) {
                $this->sqlString.= ',';
            }
        }
        
        $this->sqlString .=")";
    }

    private function generateSqlFooter()
    {
        $this->sqlString .=  "\r\n\r\n";
        $this->sqlString .=  '/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;';
        $this->sqlString .=  "\r\n";
        $this->sqlString .=  '/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;';
        $this->sqlString .=  "\r\n";
        $this->sqlString .=  '/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;';
    }

    public function downloadFile()
    {
        ob_get_clean();
        header('Content-Type: application/octet-stream');
        header("Content-Transfer-Encoding: Binary");
        header('Content-Length: '. (function_exists('mb_strlen') ? mb_strlen($this->sqlString, '8bit'): strlen($this->sqlString)) );
        header("Content-disposition: attachment; filename=\"".$this->fileName."\"");
        echo $this->sqlString;
        exit;
    }

    public function saveToFile()
    {
        if (! is_dir($this->fileDir)) {
            if (! mkdir($this->fileDir, 0777, TRUE)) {
                throw new Exception($this->lang['pathDoesNotExist'].$this->fileDir);
            }
        }

        if ($this->fileCompression) {
            if (! $backupFile = gzopen($this->fileDir . $this->fileName.'.gz', "w9") ) {
                throw new Exception($this->lang['imposibleToCreateFile'].$this->fileDir.$this->fileName.'gz');
            }
        } else {
            if (! $backupFile = fopen($this->fileDir . $this->fileName, "w+") ) {
                throw new Exception($this->lang['imposibleToCreateFile'].$this->fileDir.$this->fileName);
            }
        }

        if ($this->fileCompression) {
            gzwrite($backupFile, $this->sqlString);
        } else {
            fwrite($backupFile, $this->sqlString);
        }

        fclose($backupFile);
    }
}
?>