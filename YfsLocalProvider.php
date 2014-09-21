<?php
/**
 * YfsLocalProvider.php  
 *
 * @author    Kamilov Ramazan
 * @contact   ramazan@kamilov.ru
 *
 */
require_once 'YfsProvider.php';

class YfsLocalProvider extends YfsProvider
{
    /**
     * @throws CException
     */
    public function init()
    {
        if(!is_writable($this->internalPath)) {
            throw new \CException(\Yii::t('YfsProvider.provider', 'Директория {path} не доступна для записи.', [
                '{path}' => $this->internalPath
            ]));
        }
        parent::init();
    }

    /**
     * @param string $source
     * @param string $destination
     * @return bool
     */
    protected function _upload($source, $destination)
    {
        if(!is_dir(dirname($destination))) {
            mkdir(dirname($destination), 0777, true);
        }
        return @copy($source, $destination);
    }

    /**
     * @param string $remotePath
     * @param string $localPath
     * @return bool
     */
    protected function _download($remotePath, $localPath)
    {
        return @copy($remotePath, $localPath);
    }

    /**
     * @param string $path
     * @return bool
     */
    protected function _existsFile($path)
    {
        return is_file($path);
    }

    /**
     * @param string $path
     * @return bool
     */
    protected function _existsDirectory($path)
    {
        return is_dir($path);
    }

    /**
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
     * @param string $path
     * @return null
     */
    protected function _deleteDirectory($path)
    {
        foreach(CFileHelper::findFiles($path) as $file) {
            unlink($file);
        }

        while(substr_compare(rtrim($this->internalPath, '\\/'), $path, 0) < 0 and CFileHelper::findFiles($path) === []) {
            rmdir($path);
            $path = dirname($path);
        }
    }
}