<?php
/**
 * Local.php
 *
 * @author    Kamilov Ramazan
 * @contact   ramazan@kamilov.ru
 *
 */
namespace yfs\providers;

use yfs\Provider;

class Local extends Provider
{
    /**
     * создание директории сервера
     */
    public function init()
    {
        parent::init();

        if(!is_dir($this->privatePath) or !is_writable($this->privatePath)) {
            throw new \CException(\Yii::t('yfs.server', 'Директория {path} не найдена или не доступна для записи.', [
                '{path}' => $this->privatePath
            ]));
        }
    }

    /**
     * копирование исходного файла в директорию сервера
     * @param string $source
     * @param string $destination
     * @return bool
     */
    protected function _upload($source, $destination)
    {
        $this->_createDirectory(dirname($destination));
        return @copy($source, $destination);
    }

    /**
     * возвращает флаг о наличии файла на сервере
     * @param string $path
     * @return bool
     */
    protected function _existsFile($path)
    {
        return is_file($path);
    }

    /**
     * возвращает флаг о наличии директории на сервере
     * @param string $path
     * @return bool
     */
    protected function _existsDirectory($path)
    {
        return is_dir($path);
    }

    /**
     * удаление файла с сервера
     * @param string $path
     * @return null
     */
    protected function _deleteFile($path)
    {
        if(is_file($path)) {
            unlink($path);
        }
    }

    /**
     * удаление директории с сервера
     * удаление происходит при условии, что директория не содержит файлов
     * если параметр $clean имеет значение true, директория будет очищена и удалена
     * @param string $path
     * @param bool $clean
     * @return null
     */
    protected function _deleteDirectory($path, $clean = false)
    {
        if($clean) {
            foreach(\CFileHelper::findFiles($path) as $file) {
                unlink($file);
            }
        }

        while($path !== $this->privatePath and \CFileHelper::findFiles($path) === []) {
            rmdir($path);
            $path = dirname($path);
        }
    }

    /**
     * создание директории
     * @param string $path
     */
    private function _createDirectory($path)
    {
        if(!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }

}