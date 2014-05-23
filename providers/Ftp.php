<?php
/**
 * Ftp.php
 *
 * @author    Kamilov Ramazan
 * @contact   ramazan@kamilov.ru
 *
 */
namespace yfs\providers;

use yfs\Provider;

class Ftp extends Provider
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
     * соединение с сервером и авторизация
     * @throws \CException
     */
    public function init()
    {
        if(!extension_loaded('ftp')) {
            throw new \CException(\Yii::t('yfs.provider', 'Расширение для работы с FTP не установлено.'));
        }

        parent::init();

        $this->_stream = $this->ssl ? ftp_ssl_connect($this->host, $this->port, $this->timeout) : ftp_connect($this->host, $this->port, $this->timeout);

        if($this->_stream === false) {
            throw new \CException(\Yii::t('yfs.category', 'Не удалось соедениться с FTP-сервером {host}:{port}.', [
                '{host}' => $this->host,
                '{port}' => $this->port
            ]));
        }

        if(@ftp_login($this->_stream, $this->username, $this->password) === false) {
            throw new \CException(\Yii::t('yfs.category', 'Не удалось авторизоваться на сервере {host}:{port}.', [
                '{host}' => $this->host,
                '{port}' => $this->port
            ]));
        }

        ftp_pasv($this->_stream, true);

        if(@ftp_chdir($this->_stream, $this->privatePath) === false) {
            throw new \CException(\Yii::t('yfs.category', 'Директория "{path}" не найдена на FTP-сервере {host}:{port}.', [
                '{path}' => $this->privatePath,
                '{host}' => $this->host,
                '{port}' => $this->port
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
        return @ftp_put($this->_stream, $destination, $source, FTP_BINARY);
    }

    /**
     * возвращает флаг о наличии файла на сервере
     * @param string $path
     * @return bool
     */
    protected function _existsFile($path)
    {
        if(($list = @ftp_nlist($this->_stream, dirname($path))) !== false) {
            return in_array($path, $list);
        }
        return false;
    }

    /**
     * возвращает флаг о наличии директории на сервере
     * @param string $path
     * @return bool
     */
    protected function _existsDirectory($path)
    {
        if(($list = @ftp_nlist($this->_stream, dirname($path))) !== false) {
            return in_array($path, $list);
        }
        return false;
    }

    /**
     * удаление файла с сервера
     * @param string $path
     * @return null
     */
    protected function _deleteFile($path)
    {
        if($this->_existsFile($path)) {
            ftp_delete($this->_stream, $path);
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
            foreach(@ftp_nlist($this->_stream, $path) as $file) {
                ftp_delete($this->_stream, $file);
            }
        }

        while($path !== $this->privatePath and @ftp_nlist($this->_stream, $path) === []) {
            ftp_rmdir($this->_stream, $path);
            $path = dirname($path);
        }
    }

    /**
     * метод рекурсиного создания указанной директории
     * @param string $path
     */
    private function _createDirectory($path)
    {
        if(@ftp_chdir($this->_stream, $path) === false) {
            $names = explode(self::DIRECTORY_SEPARATOR, trim($path, self::DIRECTORY_SEPARATOR));

            ftp_chdir($this->_stream, '/');

            foreach($names as $name) {
                if(@ftp_chdir($this->_stream, $name) === false) {
                    ftp_mkdir($this->_stream, $name);
                    ftp_chmod($this->_stream, 0777, $name);
                    ftp_chdir($this->_stream, $name);
                }
            }
        }
    }
}