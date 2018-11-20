<?php

namespace Model;


class CsvModel
{
    /**
     * configCity.php
     * @var array
     */
    protected $templateCity = [];
    /**
     * Шаблон, по которому будем удалять поля
     * @var array
     */
    protected $template = [];

    /**
     * Данные csv файла
     * @var array
     */
    public $csvData = [];

    /**
     * Данные шапки таблицы
     * @var array
     */
    public $headerTable = [];

    /**
     * Данные таблицы
     * @var array
     */
    public $dataTable = [];

    /**
     * Города доставки
     * @var array
     */
    public $cityTable = [];

    /**
     * Приоритет доставки (крРог = 0 - высший, Днепр - нижший, Павлоград)
     * Для выделения цветом в книге
     * беруться из configCity
     * @var string
     */
    public $priority = 0;

    /**
     * Имя ваодителя для листа в книге
     * @var string
     */
    public $driver;

    /**
     * Массив книги excel
     * @var array
     */
    public $worksheet = [];

    /**
     * Колво листов в книге
     * @var int
     */
    public $countWorksheetList = 0;


    public function __construct()
    {
        $this->template = require $_SERVER['DOCUMENT_ROOT'] . '/config/template-ml.php';
        $this->templateCity = require $_SERVER['DOCUMENT_ROOT'] . '/config/configCity.php';
    }

    /**
     * Читаем csv файл в массив
     * @param $path string - путь к файлу
     * @param $file string - имя файла
     * @return bool
     * @throws \Exception
     */
    public function readCsv($path, $file)
    {
        try {
            $path = $_SERVER['DOCUMENT_ROOT'] . '/' . $path;
            $file = new \SplFileObject($path . '/' . $file);
            while (!$file->eof()) {
                $this->csvData[] = $file->fgetcsv();
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Создаем шапку листа $this->headerTable
     * и саму таблицу $this->dataTable
     * Лишние поля и строки удалены
     *
     * @return bool
     */
    public function createWorkSheetList()
    {
        // формируем шапку документа
        $header = $this->template['header'];
        $endRows = $this->template['tableRowStart'];
        foreach ($this->csvData as $key => $val) {
            if ($endRows) {
                $keyTemplateHeader = array_search($key, array_keys($header));
                if ($keyTemplateHeader !== false) {
                    $template = $header[$key];
                    foreach ($template as $k => $v) {
                        $this->headerTable[] = $val[$v];
                    }
                }
                $endRows--;
            } else {
                break;
            }
        }
        // формируем таблицу документа
        $tableStart = $this->template['tableRowStart'];
        $deleteColumn = $this->template['table']['deleteColumn'];
        foreach ($this->csvData as $key => $val) {
            if (array_search($this->template['tableEnd'][17], $val) !== false) {
                // нашли конец таблицы по фразе "Итого:"
                break;
            }
            if ($key >= $tableStart) {
                foreach ($val as $k => $v) {
                    $findDelete = array_search($k, $deleteColumn);
                    if ($findDelete !== false) {
                        unset($val[$k]);
                    }
                }
                $this->dataTable[] = $val;
                // добавляем города в массив
                if ($key > $tableStart) {
                    $city = $val[$this->template['tableCity']];
                    if (!empty($city) && array_search($city, $this->cityTable) === false) {
                        $this->cityTable[] = $city;
                    }
                }
            }
        }
        // определяем город доставки (Днепр, КрРог, Павлоград и т.д.)

        // определяем имя водителя для листа
        // делаем универсально, ищем в массиве $this->headerTable
        foreach ($this->headerTable as $value) {
            preg_match('#(Водитель:)(.*)?#sui', $value, $matches);
            if ($matches) {
                $this->driver = trim($matches[2]);
                break;
            }
        }

        return true;
    }

    /**
     * Clear data
     * @return void
     */
    protected function clearProperties()
    {
        $this->dataTable = [];
        $this->priority = 0;
        $this->cityTable = [];
        $this->headerTable = [];
        $this->driver = null;
        $this->csvData = [];
    }

    /**
     * Создание массива книги
     *
     * @param string $pathCsv - путь к файла csv
     * @param int $uid - уникальный номер почтового сообщения
     * @param array $fileCsv - массив имен файлов csv
     * @return array
     * @throws \Exception
     */
    public function createArrayWorksheet(string $pathCsv, int $uid, array $fileCsv)
    {
        $this->countWorksheetList = 0;
        $this->worksheet = [];
        $pathCsv = $pathCsv . '/' . $uid;
        foreach ($fileCsv as $file) {
            $this->clearProperties();
            $this->readCsv($pathCsv, $file . '.csv ');
            $this->createWorkSheetList();
            $this->worksheet[$this->countWorksheetList]['city'] = $this->cityTable;
            $this->worksheet[$this->countWorksheetList]['driver'] = explode(' ', $this->driver)[0];
            $this->worksheet[$this->countWorksheetList]['priority'] = $this->priority;
            $this->worksheet[$this->countWorksheetList]['header'] = $this->headerTable;
            $this->worksheet[$this->countWorksheetList]['table'] = $this->dataTable;
            $this->countWorksheetList++;
        }
        $this->worksheet['countList'] = $this->countWorksheetList;
        return $this->worksheet;
    }
}