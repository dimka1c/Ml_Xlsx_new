<?php

namespace Model;


class ExcelModel
{
    /**
     * Массив csv файлов для обработки
     * Файлы хранятся в папке files/csv/{uid}
     * где uid - порядкойвый номер письма в gmail
     * @var array
     */
    public $arrayCsv = [];

    /**
     * Массив книги
     * @var array
     */
    public $worksheet = [];

    /**
     * Метод преобразования файлов xls в csv для дальнейшей обработки
     *
     * @param string $pathXls - путь файлов xls
     * @param string $pathCsv - путь для сохранения файлов csv (files/csv/{uid}
     * @param array $files - массив имен файлов для преобразования
     * @param int $uid - уникальный номер письма
     * @return bool
     * @throws \PHPExcel_Writer_Exception
     */
    public function xlsToCsv(string $pathXls, string $pathCsv, array $files, int $uid): bool
    {
        try {
            if (!$this->dirExists($pathCsv)) {
                throw new \Exception('ошибка создания папки CSV', 422);
            }
            foreach ($files as $file) {
                $objReader = \PHPExcel_IOFactory::createReader('Excel5');
                $objPHPExcel = $objReader->load($pathXls . '/' . $file);
                $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'CSV');
                $pathName = pathinfo($file);
                $fileNameCsv = $pathName['filename'];
                $objWriter->save($pathCsv . '/' . $fileNameCsv . '.csv');
                $this->arrayCsv[$uid][] = $fileNameCsv;
            }
        } catch (\PHPExcel_Reader_Exception $e) {
            return false;
        }
        unset($objWriter);
        unset($objPHPExcel);
        unset($objReader);
        if (empty($this->arrayCsv)) {
            throw new \Exception('нет файлов для обработки', 422);
        }
        return true;
    }

    /**
     * Проверка существования папки
     * Если нет, создаем
     * Если есть - удаляем из нее все файлы
     * @param string $dir
     * @return bool
     */
    protected function dirExists(string $dir): bool
    {
        $dir = $_SERVER['DOCUMENT_ROOT'] . '/' . $dir;
        if (!file_exists($dir)) {
            if (!mkdir($dir, 0700, true)) {
                return false;
            } else {
                return true;
            }
        } else {
            array_map('unlink', glob($dir . '/*.*'));
            return true;
        }
    }

    /**
     * Создание xls Worksheet
     *
     * @param int $uid
     * @return bool
     */
    protected function createXlsWorksheet($uid): bool
    {
        $pExcel = new \PHPExcel();
        //** выбираем заведомо второй лист, на первом листе сделаем общие данные */
        /** получаем колво листов */
        $countList = $this->worksheet[$uid]['countList'];
        /** номер текущего листа задаем 1, что в excel'e будет вторым листом*/
        for ($numList = 1; $numList<= $countList; $numList++) {
            /** создаем лист с номером numList */
            $pExcel->createSheet($numList);
            /** устанавливаем лист как текущий */
            $pExcel->setActiveSheetIndex($numList);
            /** получаем на него ссылку */
            $aSheet = $pExcel->getActiveSheet();
            /** устанавливаем имя листа в книге */
            if (!empty($this->worksheet[$uid][$numList-1]['driver'])) {
                $aSheet->setTitle($this->worksheet[$uid][$numList-1]['driver']);
            } else {
                $aSheet->setTitle('NA');
            }
            /** заполняем данными */
            /** 1. заголовок листа */
            $header = $this->worksheet[$uid][$numList-1]['header'];
            $aSheet->setCellValue('A1', $header[0]);    //Маршрутный лист № ХВ-0218598 от 14.11.2018
            $aSheet->setCellValue('A2', $header[1]);    //4ДнепрСр, 34Днепр(РС)СР
            $aSheet->setCellValue('A3', $header[2]);    //Авто: AE4790IM
            $aSheet->setCellValue('A4', $header[3]);    //Рейс № 831790
            $aSheet->setCellValue('A5', $header[4]);    //Водитель: Слисарчук Владимир Зиновьевич
            $aSheet->setCellValue('A6', $header[5]);    //Моб. телефон водителя: 0962322353
            /** 2. заголовок листа */
            $table = $this->worksheet[$uid][$numList-1]['table'];
            $row = 8;   // выводим таблицу с 8 строки
            foreach ($table as $key => $data) {
                $aSheet->setCellValue('A'.$row, $data[0]);
                $aSheet->setCellValue('B'.$row, $data[3]);
                $aSheet->setCellValue('C'.$row, $data[4]);
                $aSheet->setCellValue('D'.$row, $data[7]);
                $aSheet->setCellValue('E'.$row, $data[8]);
                $aSheet->setCellValue('F'.$row, $data[9]);
                $aSheet->setCellValue('G'.$row, $data[12]);
                $aSheet->setCellValue('H'.$row, $data[15]);
                $aSheet->setCellValue('I'.$row, $data[16]);
                $aSheet->setCellValue('J'.$row, $data[17]);
                $aSheet->setCellValue('K'.$row, $data[20]);
                $aSheet->setCellValue('L'.$row, $data[21]);
                $aSheet->setCellValue('M'.$row, $data[22]);
                $row++;
            }

        }
        $objWriter = \PHPExcel_IOFactory::createWriter($pExcel, 'Excel2007');
        $objWriter->save($_SERVER['DOCUMENT_ROOT'] . '/files/ml/test.xlsx');

    }

    /**
     * Создание книги МЛ
     *
     * @param string $pathCsv - путь к файлам csv
     * @throws \Exception
     */
    public function createWorksheet(string $pathCsv): bool
    {
        $csvModel = new CsvModel();
        foreach ($this->arrayCsv as $uid => $fileCsv) {
            /** создаем массив с данными книги */
            $this->worksheet[$uid] = $csvModel->createArrayWorksheet($pathCsv, $uid, $fileCsv);
            //var_dump($this->worksheet[$uid]);
            if (empty($this->worksheet)) {
                throw new \Exception('ошибка генерации книги XLS', 422);
            }
            /** генерируем файл xls */
            $this->createXlsWorksheet($uid);
            return true; // test, формируем пока только одну книгу
        }

    }

}