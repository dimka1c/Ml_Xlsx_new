<?php


namespace Controller;

use Model\ExcelModel;
use Model\MailModel;

class AppController
{
    /**
     * Пути сохранения файлов
     * @var array
     */
    public $pathFiles = [];


    public function __construct()
    {
        $config = require $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
        $this->pathFiles = $config['pathFiles'];
    }

    /**
     * Создание общего листа МЛ
     * @throws \Exception
     */
    public function createML()
    {
        $mailModel = new MailModel();
        $mailModel->receiveEmail();
        if (empty($mailModel->rightEmails)) {
            // если нет писем с правильным шаблоном в subj
            require $_SERVER['DOCUMENT_ROOT'] . '/Views/NoRightMails.php';
            exit;
        }
        foreach ($mailModel->rightEmails as $uid => $value) {
            if ($mailModel->loadAttach($uid, $this->pathFiles['attachSave']) == false) {
                throw new \Exception('ошибка при сохранении файлов', 422);
            };
        }
        // Преобразуем файлы xls -> csv
        // Если будет xlsToCsv = false, выкинем ошибку и остановим выполнение программы
        $excelModel = new ExcelModel();
        foreach ($mailModel->attachmentsFiles as $uid => $files) {
            $xlsToCsv = $excelModel->xlsToCsv($this->pathFiles['attachSave'] . '/' . $uid, $this->pathFiles['csvSave'] . '/' . $uid, $files, $uid);
            if (!$xlsToCsv) {
                throw new \Exception('ошибка преобразования файлов xls -> csv', 422);
            }
        }

      /*
        // читаем csv файлы и редактируем:
        // - удаляем пустые строки
        // - удаляем ненужные строки и значения
        $worksheet = new WorksheetModel();
        $csvModel = new CsvModel();
        $csvModel->readCsv($this->pathFiles['csvSave'] . '/435', 'ml0218598_5600.csv ');
        var_dump($csvModel->csvData);
      */

      $excelModel->createWorksheet($this->pathFiles['csvSave']);
      echo 'exit';
    }
}