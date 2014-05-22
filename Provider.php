<?php
/**
 * Provider.php
 *
 * @author    Kamilov Ramazan
 * @contact   ramazan@kamilov.ru
 *
 */
namespace yfs;

abstract class Provider extends \CApplicationComponent
{
    /**
     * разделитель директорий
     */
    const DIRECTORY_SEPARATOR = '/';

    /**
     * внутренний путь на сервере к директории с файлами
     * @var string
     */
    public $privatePath;

    /**
     * публичный путь к директории с файлами для клиентского доступа
     * @var string
     */
    public $publicPath;

    /**
     * инициализация компонента:
     * - добавление объекта поведения отвечающего за хранение информации о файлах
     * - регистрация функции загрузки файлов по завершению работы скрипта
     * @throws \CException
     */
    public function init()
    {
        if($this->privatePath === null) {
            throw new \CException(\Yii::t('yfs.provider', 'Не определено свойство {class}.privatePath', [
                '{class}' => get_class($this)
            ]));
        }

        $this->privatePath = rtrim($this->privatePath, '\\/');

        if($this->publicPath === null) {
            throw new \CException(\Yii::t('yfs.provider', 'Не определено свойство {class}.publicPath', [
                '{class}' => get_class($this)
            ]));
        }

        $this->publicPath = rtrim($this->publicPath, '/');
    }

    /**
     * загрузка файла на сервер
     * @param iFile $file
     * @return bool|string
     */
    public function upload(iFile $file)
    {
        if($file->getIsUploaded()) {
            return null;
        }

        if($file->getParent() !== null) {
            $path = dirname($file->getParent()->getPath());
        }
        else if($file->getPath() !== null) {
            $path = dirname($file->getPath());
        }
        else {
            $path = $this->_buildDirectoryPath($file->getSource());
        }

        $path = $path . self::DIRECTORY_SEPARATOR . $this->_buildFileName($file->getSource(), $path);

        if($this->_upload($file->getSource(), $this->privatePath . self::DIRECTORY_SEPARATOR . $path)) {
            \Yii::trace(\Yii::t('yfs.provider', 'Файл {source} загружен.'), 'application.yfs');
            return $path;
        }
        else {
            \Yii::trace(\Yii::t('yfs.provider', 'Не удалось загрузить Файл {source}.'), 'application.yfs');
            return null;
        }
    }

    public function update(iFile $file)
    {
        if($file->getIsUploaded()) {
            return null;
        }
        $this->_deleteFile($this->privatePath . self::DIRECTORY_SEPARATOR . $file->getPath());
        return $this->upload($file);
    }

    /**
     * удаление файла с сервера
     * @param iFile $file
     */
    public function delete(iFile $file)
    {
        $path = $this->privatePath . self::DIRECTORY_SEPARATOR . $file->getPath();

        if($file->getParent() === null) {
            $this->_deleteDirectory(dirname($path), true);
        }
        else {
            $this->_deleteFile($path);
            $this->_deleteDirectory(dirname($path));
        }
    }

    /**
     * формирование пути к директории файла
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

        while($this->_existsDirectory($this->privatePath . self::DIRECTORY_SEPARATOR . $path)) {
            $path = dirname($path) . self::DIRECTORY_SEPARATOR . sprintf('%x', crc32(uniqid()));
        }

        return $path;
    }

    /**
     * генерация имени файла
     * @param string $source
     * @param string $directory
     * @return string
     */
    private function _buildFileName($source, $directory)
    {
        if(($extension = \CFileHelper::getExtension($source)) !== '') {
            $extension = '.' . $extension;
        }

        $name = sprintf('%x', crc32(md5_file($source))) . $extension;

        while($this->_existsFile($directory . self::DIRECTORY_SEPARATOR . $name)) {
            $name = sprintf('%x', crc32(uniqid())) . $extension;
        }

        return $name;
    }

    /**
     * метод загрузки файла на сервер относительно провайдера
     * @param string $source
     * @param string $destination
     * @return bool
     */
    abstract protected function _upload($source, $destination);

    /**
     * метод проверки наличия файла на сервере
     * @param string $path
     * @return bool
     */
    abstract protected function _existsFile($path);

    /**
     * метод проверки наличия директории на сервере
     * @param string $path
     * @return bool
     */
    abstract protected function _existsDirectory($path);

    /**
     * удаление файла
     * @param string $path
     * @return null
     */
    abstract protected function _deleteFile($path);

    /**
     * удаление директории
     * @param string $path
     * @param bool $clean
     * @return null
     */
    abstract protected function _deleteDirectory($path, $clean = false);
}

if(($messages = \Yii::app()->messages) !== null and property_exists($messages, 'extensionPaths')) {
    $messages->extensionPaths['yfs'] = __DIR__;
}

\Yii::$classMap[__NAMESPACE__ . '\iFile'] = __DIR__ . '/File.php';