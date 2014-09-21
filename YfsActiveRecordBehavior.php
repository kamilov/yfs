<?php
/**
 * YfsActiveRecordBehavior.php
 *
 * @author    Kamilov Ramazan
 * @contact   ramazan@kamilov.ru
 *
 */

/**
 * Class YfsActiveRecordBehavior
 * @property YfsProvider $provider
 * @property string $sourcePath
 */
class YfsActiveRecordBehavior extends CActiveRecordBehavior
{
    /**
     * идентификатор компонента для работы с файлами
     * @var string
     */
    public $componentId = 'storage';

    /**
     * имя атрибута в который будет передан путь к загруженному файлу
     * @var string
     */
    public $pathAttributeName = 'path';

    /**
     * имя аттрибута модели, в который передаётся путь к исходному файлу
     * @var string
     */
    public $sourceAttributeName;

    /**
     * имя связи с родительским файлом.
     * @var string
     */
    public $parentRelationName;

    /**
     * @var YfsProvider
     */
    private $_provider;

    /**
     * @var string
     */
    private $_sourcePath;

    /**
     * @return YfsProvider
     * @throws CException
     */
    public function getProvider()
    {
        if($this->_provider === null and (($this->_provider = Yii::app()->getComponent($this->componentId)) === null or !$this->_provider instanceof YfsProvider)) {
            throw new CException(Yii::t('YfsProvider.behavior', '{class} требуется компонент с идентификатором "{id}" YfsProvider.', [
                '{class}' => get_class($this),
                '{id}'    => $this->componentId
            ]));
        }
        return $this->_provider;
    }

    /**
     * @param string $value
     * @throws CException
     */
    public function setSourcePath($value)
    {
        if(($path = realpath($value)) === false or !is_file($path)) {
            throw new CException(Yii::t('YfsProvider.behavior', 'Неверно задан путь к исходному файлу "{path}"', [
                '{path}' => $value
            ]));
        }

        if($this->sourceAttributeName !== null) {
            $this->owner->setAttribute($this->sourceAttributeName, $value);
        }
        else {
            $this->_sourcePath = $value;
        }
    }

    /**
     * @return null|string
     */
    public function getSourcePath()
    {
        return $this->sourceAttributeName !== null ? $this->owner->getAttribute($this->sourceAttributeName) : $this->_sourcePath;
    }

    /**
     * @return null|string
     * @throws CException
     */
    public function getUrl()
    {
        if($this->owner->isNewRecord) {
            return null;
        }
        return $this->getProvider()->external($this->owner->getAttribute($this->pathAttributeName));
    }

    /**
     *
     */
    public function beforeSave($event)
    {
        if(($sourcePath = $this->getSourcePath()) !== null) {
            if($this->owner->isNewRecord === false) {
                $this->getProvider()->delete($this->owner->getAttribute($this->pathAttributeName));
            }

            $directory = null;

            if($this->parentRelationName !== null and ($parent = $this->owner->getRelated($this->parentRelationName, true)) !== null) {
                $directory = dirname($parent->path);
            }

            if(($path = $this->getProvider()->upload($sourcePath, $directory)) === null) {
                $event->isValid = false;
                $this->owner->addError($this->pathAttributeName, Yii::t('YfsProvider.behavior', 'Не удалось загрузить файл "{path}".', [
                    '{path}' => $sourcePath
                ]));
                return;
            }

            $this->owner->setAttribute($this->pathAttributeName, $path);
        }
        parent::beforeSave($event);
    }

    /**
     *
     */
    public function afterDelete()
    {
        $path = $this->owner->getAttribute($this->pathAttributeName);

        if($this->parentRelationName === null or $this->owner->getRelated($this->parentRelationName, true) === null) {
            $path = dirname($path);
        }

        $this->getProvider()->delete($path);
    }

    /**
     * @param string $path
     * @return bool
     * @throws CException
     */
    public function download($path)
    {
        return $this->getProvider()->download($this->owner->getAttribute($this->pathAttributeName), $path);
    }
}