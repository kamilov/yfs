<?php
/**
 * File.php
 *
 * @author    Kamilov Ramazan
 * @contact   ramazan@kamilov.ru
 *
 */
namespace yfs;

/**
 * Class File
 * @package yfs
 *
 * @property int $id
 * @property int $parentId
 * @property int $size
 * @property string $category
 * @property string $path
 * @property string $mime
 * @property string $index
 * @property string $modifiedAt
 * @property string $createdAt
 * @property string $updatedAt
 * @property string $source
 * @property string $link
 *
 * @property File $parent
 * @property File[] $child
 *
 * @method File[] findAll($condition = '', $params = array())
 * @method File[] findAllByPk($pk, $condition = '', $params = array())
 * @method File[] findAllByAttributes($attributes, $condition = '', $params = array())
 * @method File[] findAllBySql($sql, $params = array())
 * @method File find($condition = '', $params = array())
 * @method File findByPk($pk, $condition = '', $params = array())
 * @method File findByAttributes($attributes, $condition = '', $params = array())
 * @method File findBySql($sql, $params = array())
 * @method File with()
 */
class File extends \CActiveRecord implements iFile
{
    /**
     * имя таблицы по умолчанию
     */
    const DEFAULT_TABLE_NAME = '{{YiiFileStorage}}';

    /**
     * имя категории по умолчанию
     */
    const DEFAULT_CATEGORY_NAME = 'Undefined';

    /**
     * @var Provider
     */
    public static $provider;

    /**
     * путь к исходному файлу
     * @var string
     */
    private $_source;

    /**
     * список дочерних элементов по категориям
     * @var \CMap[]
     */
    private $_childCategories;

    /**
     * @param string $className
     * @return File
     */
    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    /**
     * метод быстрого создания объекта модели и передачи в неё переданных параметров
     * @param string $source
     * @param null|string $category
     * @param null|string $index
     * @return File
     */
    public static function create($source, $category = null, $index = null)
    {
        /** @var File $model */
        $model = new static();
        $model->setSource($source)
              ->setAttributes([
                  'category' => $category,
                  'index'    => $index
              ]);

        return $model;
    }

    /**
     * метод быстрой загрузки файла
     * @param string $source
     * @param null|string $category
     * @param null|string $index
     * @return File
     */
    public static function upload($source, $category = null, $index = null)
    {
        $model = self::create($source, $category, $index);
        $model->save();
        return $model;
    }

    /**
     * @return array
     */
    public function behaviors()
    {
        return [
            'CTimestampBehavior' => [
                'class'             => 'zii.behaviors.CTimestampBehavior',
                'createAttribute'   => 'createdAt',
                'updateAttribute'   => 'updatedAt',
                'setUpdateOnCreate' => true
            ]
        ];
    }

    /**
     * @return string
     */
    public function tableName()
    {
        return self::DEFAULT_TABLE_NAME;
    }

    /**
     * @return array
     */
    public function rules()
    {
        return [
            ['parentId', 'exist', 'className' => get_class($this), 'attributeName' => 'id', 'criteria' => ['condition' => 't.parentId IS NULL']],
            ['size', 'numerical', 'integerOnly' => true],
            ['category, path, mime', 'length', 'max' => 75],
            ['index', 'length', 'max' => 10],
            ['modifiedAt, createdAt, updatedAt', 'type', 'type' => 'datetime', 'datetimeFormat' => 'yyyy-MM-dd HH:mm:ss']
        ];
    }

    /**
     * @return array
     */
    public function relations()
    {
        return [
            'parent' => [self::BELONGS_TO, get_class($this), 'parentId'],
            'child'  => [self::HAS_MANY, get_class($this), 'parentId', 'index' => 'index']
        ];
    }

    /**
     * если определён исходный файл, то необходимая информация о нём будет передана в БД
     * @return bool
     */
    public function beforeSave()
    {
        if(parent::beforeSave()) {
            if($this->_source !== null) {
                if(($this->isNewRecord and ($path = $this->getProvider()->upload($this)) !== null) or ($this->isNewRecord === false and ($path = $this->getProvider()->update($this)) !== null)) {
                    $this->attributes = [
                        'size'       => filesize($this->_source),
                        'path'       => $path,
                        'mime'       => \CFileHelper::getMimeType($this->_source),
                        'modifiedAt' => date('Y-m-d H:i:s', filemtime($this->_source))
                    ];
                }
                return true;
            }
        }
        return false;
    }

    /**
     * если индекс не был определён, то его значением будет идентификатор (pk)
     */
    public function afterSave()
    {
        parent::afterSave();
        if($this->index === null) {
            $this->setIsNewRecord(false);
            $this->setScenario('update');

            $this->index = $this->id;
            $this->update(['index']);
        }
    }

    /**
     * после удаления файла из БД удаляем его с сервера
     */
    public function afterDelete()
    {
        $this->getProvider()->delete($this);
        parent::afterDelete();
    }

    /**
     * возвращает объект компонента для загрузки файлов
     * @return Provider
     * @throws \CException
     */
    public function getProvider()
    {
        if(self::$provider === null) {
            if(!(self::$provider = \Yii::app()->getComponent('storage')) instanceof Provider) {
                throw new \CException(\Yii::t('yfs.file', '{class} запрашивает компонент приложения "storage" который наследует класс yfs\Provider.'));
            }
        }
        return self::$provider;
    }

    /**
     * сохраняет путь к исходному файлу, который должен быть загружен
     * @param string $value
     * @return $this
     * @throws \CException
     */
    public function setSource($value)
    {
        if(!is_file($value) or !is_readable($value)) {
            throw new \CException(\Yii::t('yfs.file', 'Указанный файл {path} не найден или не доступен для чтения.', [
                '{path}' => $value
            ]));
        }
        $this->_source = $value;

        return $this;
    }

    /**
     * возвращает путь к исходному файлу
     * @return string
     */
    public function getSource()
    {
        return $this->_source;
    }

    /**
     * возвращает путь к загруженому файлу относительно корня хранилища
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * возвращает объект родительского файла
     * @return File|null
     */
    public function getParent()
    {
        return $this->getRelated('parent');
    }

    /**
     * возвращает флаш определяющий, что файл уже загружен
     * @return bool
     */
    public function getIsUploaded()
    {
        return $this->isNewRecord === false and $this->_source === null;
    }

    /**
     * возвращает дочерний элемент по его индексу
     * @param string $index
     * @return null|File
     */
    public function child($index)
    {
        return isset($this->child[$index]) ? $this->child[$index] : null;
    }

    /**
     * добавляет объект дочернего элемента в общий список
     * @param File $file
     */
    public function addChild(File $file)
    {
        $file->parentId = $this->id;
        $file->addRelatedRecord('parent', $this, false);

        if($file->save()) {
            $this->addRelatedRecord('child', $file, $file->index);
        }
    }

    /**
     * возвращает дочерние файлы сгруппированные по категориям
     * @param string $name
     * @param bool $refresh
     * @return \CMap|null
     */
    public function childCategory($name, $refresh = false)
    {
        if($this->_childCategories === null or $refresh) {
            $this->_childCategories = [];

            foreach($this->child as $child) {
                if(!isset($this->_childCategories[$child->category])) {
                    $this->_childCategories[$child->category] = new \CMap();
                }
                $this->_childCategories[$child->category]->add($child->index, $child);
            }
        }
        return isset($this->_childCategories[$name]) ? $this->_childCategories[$name] : null;
    }

    /**
     * формирует и возвращает путь к файлу для клиентского доступа
     * @return string
     */
    public function getLink()
    {
        return $this->getProvider()->publicPath . Provider::DIRECTORY_SEPARATOR . $this->path;
    }
}

interface iFile
{
    /**
     * сохраняет путь к исходному файлу, который должен быть загружен
     * @param string $value
     * @return iFile
     */
    public function setSource($value);

    /**
     * возвращает путь к исходному файлу
     * @return string
     */
    public function getSource();

    /**
     * возвращает путь к загруженому файлу относительно корня хранилища
     * @return string
     */
    public function getPath();

    /**
     * возвращает объект родительского файла
     * @return iFile
     */
    public function getParent();

    /**
     * возвращает флаш определяющий, что файл уже загружен
     * @return bool
     */
    public function getIsUploaded();
}