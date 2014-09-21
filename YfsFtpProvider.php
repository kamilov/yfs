<?php
/**
 * YfsFtpProvider.php  
 *
 * @author    Kamilov Ramazan
 * @contact   ramazan@kamilov.ru
 *
 */
require_once 'YfsProvider.php';

class YfsFtpProvider extends YfsProvider
{
    /**
     * хост ftp-сервера
     * @var string
     */
    public $host;

    /**
     * порт
     * @var int
     */
    public $port = 21;

    /**
     * таймаут
     * @var int
     */
    public $timeout = 90;

    /**
     * имя пользователя ftp-сервера
     * @var string
     */
    public $username;

    /**
     * пароль пользователя
     * @var string
     */
    public $password;

    /**
     * флаг определяющий использование ssl соединения вместо обычного
     * @var bool
     */
    public $ssl = false;

    /**
     * ссылка на соединение с ftp-сервером
     * @var resource
     */
    private $_stream;

    /**
     * после завершения работы - закрываем соединение с сервером
     */
    public function __destruct()
    {
        ftp_close($this->_stream);
    }

    /**
     * @throws CException
     */
    public function init()
    {
        parent::init();

        if(!extension_loaded('ftp')) {
            throw new \CException(\Yii::t('YfsProvider.provider', 'Расширение для работы с FTP не установлено.'));
        }

        $this->_stream = $this->ssl ? ftp_ssl_connect($this->host, $this->port, $this->timeout) : ftp_connect($this->host, $this->port, $this->timeout);

        if($this->_stream === false) {
            throw new \CException(\Yii::t('YfsProvider.provider', 'Не удалось соедениться с FTP-сервером {host}:{port}.', [
                '{host}' => $this->host,
                '{port}' => $this->port
            ]));
        }

        if(ftp_login($this->_stream, $this->username, $this->password) === false) {
            throw new \CException(\Yii::t('YfsProvider.provider', 'Не удалось авторизоваться на сервере {host}:{port}.', [
                '{host}' => $this->host,
                '{port}' => $this->port
            ]));
        }

        ftp_pasv($this->_stream, true);
    }

    /**
     * @param string $source
     * @param string $destination
     * @return bool
     */
    protected function _upload($source, $destination)
    {
        if(ftp_chdir($this->_stream, dirname($destination)) === false) {
            $parts = explode(self::DIRECTORY_SEPARATOR, substr(dirname($destination), strlen($this->internalPath) + 1));

            ftp_chdir($this->_stream, $this->internalPath);

            foreach($parts as $part) {
                if(ftp_chdir($this->_stream, $part) === false) {
                    ftp_mkdir($this->_stream, $part);
                    ftp_chmod($this->_stream, 0777, $part);
                    ftp_chdir($this->_stream, $part);
                }
            }
        }
        return ftp_put($this->_stream, $destination, $source, FTP_BINARY);
    }

    /**
     * @param string $remotePath
     * @param string $localPath
     * @return bool
     */
    protected function _download($remotePath, $localPath)
    {
        return @ftp_get($this->_stream, $localPath, $remotePath, FTP_BINARY);
    }

    /**
     * @param string $path
     * @return bool
     */
    protected function _existsFile($path)
    {
        if(($list = @ftp_nlist($this->_stream, dirname($path))) !== false) {
            return in_array($path, $list) and @ftp_chdir($this->_stream, $path) === false;
        }
        return false;
    }

    /**
     * @param string $path
     * @return bool
     */
    public function _existsDirectory($path)
    {
        if(($list = @ftp_nlist($this->_stream, dirname($path))) !== false) {
            return in_array($path, $list) and @ftp_chdir($this->_stream, $path) === true;
        }
        return false;
    }

    /**
     * @param string $path
     * @return null
     */
    public function _deleteFile($path)
    {
        if($this->_existsFile($path)) {
            ftp_delete($this->_stream, $path);
        }
    }

    /**
     * @param string $path
     * @return null
     */
    public function _deleteDirectory($path)
    {
        if($this->_existsDirectory($path)) {
            foreach(ftp_nlist($this->_stream, $path) as $file) {
                ftp_delete($this->_stream, $file);
            }

            while(substr_compare(rtrim($this->internalPath, '\\/'), $path, 0) < 0 and ftp_nlist($this->_stream, $path) === []) {
                ftp_rmdir($this->_stream, $path);
                $path = dirname($path);
            }
        }
    }
}