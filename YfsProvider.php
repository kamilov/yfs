<?php
/**
 * YfsProvider.php  
 *
 * @author    Kamilov Ramazan
 * @contact   ramazan@kamilov.ru
 *
 */

/**
 * Class YfsProvider
 * @property string $internalPath
 * @property string $externalPath
 */
abstract class YfsProvider extends CApplicationComponent
{
    /**
     * разделитель имён директорий
     */
    const DIRECTORY_SEPARATOR = '/';

    /**
     * внутренний(локальный) путь к директории с хранимыми файлами
     * @var string
     */
    private $_internalPath;

    /**
     * внешний(http, ftp и т.д.) путь к директории с файлами
     * @var string
     */
    private $_externalPath;

    /**
     * @param string $value
     * @throws CException
     */
    public function setInternalPath($value)
    {
        if($this->_existsDirectory($value) === false) {
            throw new CException(Yii::t('YfsProvider.provider', 'Внутренний путь указан неверно, директория "{path}" не найдена на сервере.', [
                '{path}' => $value
            ]));
        }
        $this->_internalPath = rtrim($value, '\\/');
    }

    /**
     * @return string
     * @throws CException
     */
    public function getInternalPath()
    {
        if($this->_internalPath === null) {
            throw new CException(Yii::t('YfsProvider.provider', 'Свойство {class}.internalPath не определено. Укажите путь к директории, в которой собираетесь хранить файлы.', [
                '{class}' => get_class($this)
            ]));
        }
        return $this->_internalPath;
    }

    /**
     * @param $value
     */
    public function setExternalPath($value)
    {
        $this->_externalPath = rtrim($value, '\\/');
    }

    /**
     * @return string
     * @throws CException
     */
    public function getExternalPath()
    {
        if($this->_externalPath === null) {
            throw new CException(Yii::t('YfsProvider.provider', 'Свойство {class}.externalPath не определено. Доступ к файлам невозможен.', [
                '{class}' => get_class($this)
            ]));
        }
        return $this->_externalPath;
    }

    /**
     * формирует и возвращает внутренний путь к указанному файлу
     * @param string $path
     * @return string
     */
    public function internal($path)
    {
        return rtrim($this->getInternalPath(), '\\/') . self::DIRECTORY_SEPARATOR . ltrim($path, '\\/');
    }

    /**
     * формирует и возвращает внешний путь к указанному файлу
     * @param string $path
     * @return string
     */
    public function external($path)
    {
        return rtrim($this->_externalPath, '\\/') . self::DIRECTORY_SEPARATOR . ltrim($path, '\\/');
    }

    /**
     * загрузка файла на сервер
     * если передан путь к файлу-прородителю, то исходный файл будет загружен в директорию этого файла
     * @param string $source
     * @param null|string $parent
     * @return null|string
     * @throws CException
     */
    public function upload($source, $parent = null)
    {
        if(!is_file($source) or !is_readable($source)) {
            throw new CException(Yii::t('YfsProvider.provider', 'Исходный файл {path} не найден или не доступен для чтения.', [
                '{path}' => $source
            ]));
        }

        if($parent !== null and $this->_normalizePath($parent) !== false) {
            $directory = $this->_existsFile($parent) ? dirname($parent) : $parent;
            unset($parent);
        }
        else {
            $directory = $this->_buildDirectoryPath($source);
        }

        $file = $this->_buildFilePath($source, $directory);

        return $this->_upload($source, $this->internal($file)) ? $file : null;
    }

    /**
     * удаление файла или целой директории
     * @param string $path
     */
    public function delete($path)
    {
        if($this->_normalizePath($path)) {
            if($this->_existsFile($path)) {
                $this->_deleteFile($path);
            }
            else {
                $this->_deleteDirectory($path);
            }
        }
    }

    /**
     * скачивание удалённого файла на сервер с приложением
     * @param string $remotePath
     * @param string $localPath
     * @return bool
     */
    public function download($remotePath, $localPath)
    {
        if($this->_normalizePath($remotePath)) {
            return $this->_download($remotePath, $localPath);
        }
        return false;
    }

    /**
     * @param string $source
     * @return string
     */
    private function _buildDirectoryPath($source)
    {
        $hash = md5_file($source);
        $path = implode(self::DIRECTORY_SEPARATOR, [
            substr($hash, 0, 2),
            substr($hash, 2, 2),
            sprintf('%x', crc32($hash))
        ]);

        while($this->_existsDirectory($this->internal($path))) {
            $path = dirname($path) . self::DIRECTORY_SEPARATOR . sprintf('%x', crc32(uniqid()));
        }

        return $path;
    }

    /**
     * @param string $source
     * @param string $directory
     * @return string
     */
    private function _buildFilePath($source, $directory)
    {
        if(($extension = \CFileHelper::getExtension($source)) !== '') {
            $extension = '.' . $extension;
        }

        $name = sprintf('%x', crc32(md5_file($source))) . $extension;

        while($this->_existsFile($this->internal($directory . self::DIRECTORY_SEPARATOR . $name))) {
            $name = sprintf('%x', crc32(md5_file($source))) . $extension;
        }

        return $directory . self::DIRECTORY_SEPARATOR . $name;
    }

    /**
     * @param string $path
     * @return bool|string
     */
    private function _normalizePath(&$path)
    {
        if($this->_existsFile($path) or $this->_existsDirectory($path)) {
            return $path;
        }
        else if($this->_existsFile($this->internal($path)) or $this->_existsDirectory($this->internal($path))) {
            return $path = $this->internal($path);
        }
        return false;
    }

    /**
     * @param string $source
     * @param string $destination
     * @return bool
     */
    abstract protected function _upload($source, $destination);

    /**
     * @param string $remotePath
     * @param string $localPath
     * @return bool
     */
    abstract protected function _download($remotePath, $localPath);

    /**
     * @param string $path
     * @return bool
     */
    abstract protected function _existsFile($path);

    /**
     * @param string $path
     * @return bool
     */
    abstract protected function _existsDirectory($path);

    /**
     * @param string $path
     * @return null
     */
    abstract protected function _deleteFile($path);

    /**
     * @param string $path
     * @return null
     */
    abstract protected function _deleteDirectory($path);
}