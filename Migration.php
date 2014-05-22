<?php
/**
 * Migration.php
 *
 * @author    Kamilov Ramazan
 * @contact   ramazan@kamilov.ru
 *
 */
namespace yfs;

require_once 'File.php';

class Migration extends \CDbMigration
{
    /**
     * @return bool|void
     */
    public function safeUp()
    {
        $this->createTable($this->getTableName(), [
            'id'         => 'INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT',
            'parentId'   => 'INT UNSIGNED NULL DEFAULT NULL',
            'size'       => 'INT UNSIGNED NOT NULL',
            'category'   => 'VARCHAR(75) NULL DEFAULT NULL',
            'path'       => 'VARCHAR(75) NOT NULL',
            'mime'       => 'VARCHAR(75) NOT NULL',
            'index'      => 'VARCHAR(10) NULL DEFAULT NULL',
            'modifiedAt' => 'DATETIME NOT NULL',
            'createdAt'  => 'TIMESTAMP NULL DEFAULT NULL',
            'updatedAt'  => 'TIMESTAMP NULL DEFAULT NULL'
        ]);

        $this->createIndex('searchByCategory', $this->getTableName(), 'category');
        $this->createIndex('uniqueIndex', $this->getTableName(), 'parentId, index', true);
        $this->createIndex(sprintf('fk%sParentIndex', $this->getTableName()), $this->getTableName(), 'parentId', true);

        $this->addForeignKey(sprintf('fk%sParent', $this->getTableName()), $this->getTableName(), 'parentId', $this->getTableName(), 'id', 'CASCADE', 'CASCADE');
    }

    /**
     * @return bool|void
     */
    public function safeDown()
    {
        $this->dropTable($this->getTableName());
    }

    /**
     * @return string
     */
    public function getTableName()
    {
        return File::DEFAULT_TABLE_NAME;
    }
}