<?php

namespace Model;


class MailModel
{

    /**
     * Email host
     * @var string
     */
    public $host;

    /**
     * Login for email
     * @var string
     */
    public $username;

    /**
     * Password for Email
     * @var string
     */
    public $password;

    /**
     * Attacmetns from maillist
     * @var array
     */
    public $attachmentsFiles = [];

    /**
     * Все письма из почтового ящика
     * @var array
     */
    public $allEmails = [];

    /**
     * Пимьма, которые соответствуют шаблону
     * @var array
     */
    public $rightEmails = [];

    /**
     * Pattern subj
     * @var array
     */
    public $pattern = [];

    /**
     * Разрешенные адреса, от кторых можно обрабатывать файл
     * @var array
     */
    protected $allowAddressEmail = [];

    public function __construct()
    {
        $config = require $_SERVER['DOCUMENT_ROOT'] . '/config/config-email.php';
        $this->host = $config['host'];
        $this->username = $config['username'];
        $this->password = $config['password'];
        $this->pattern = $config['pattern'];
        $this->allowAddressEmail = $config['allowFromEmail'];
    }

    protected function create_part_array($structure, $prefix = '')
    {
        global $part_array;
        if (sizeof($structure->parts) > 0) {
            foreach ($structure->parts as $count => $part) {
                $this->add_part_to_array($part, $prefix . ($count + 1), $part_array);
            }
        }else{
            $part_array[] = array('part_number' => $prefix . '1', 'part_object' => $obj);
        }
        return $part_array;
    }

    protected function add_part_to_array($obj, $partno, $part_array)
    {
        global $part_array;
        $part_array[] = array('part_number' => $partno, 'part_object' => $obj);
        if ($obj->type == 2) {
            if (sizeof($obj->parts) > 0) {
                foreach ($obj->parts as $count => $part) {
                    if (sizeof($part->parts) > 0) {
                        foreach ($part->parts as $count2 => $part2) {
                            $this->add_part_to_array($part2, $partno.".".($count2+1), $part_array);
                        }
                    } else {
                        $part_array[] = array('part_number' => $partno.'.'.($count+1), 'part_object' => $obj);
                    }
                }
            } else {
                $part_array[] = array('part_number' => $prefix.'.1', 'part_object' => $obj);
            }
        } else {
            if (sizeof($obj->parts) > 0) {
                foreach ($obj->parts as $count => $p) {
                    $this->add_part_to_array($p, $partno.".".($count+1), $part_array);
                }
            }
        }
    }

    /**
     * Получаем все письма, находящиесяв почтовом ящике
     * @return bool
     * @throws \Exception
     */
    public function receiveEmail()
    {
        try {
            set_time_limit(60000);
            $ml = imap_open($this->host, $this->username, $this->password);
            if($ml) {
                $n = imap_num_msg($ml); //колво писем в ящике
                if ($n > 0) {
                    for ($i = 1; $i <= $n; $i++) {
                        $h = imap_header($ml, $i);
                        $email = $h->from[0]->mailbox . '@' . $h->from[0]->host;
                        $headerArr = imap_headerinfo($ml, $i);
                        $uid = imap_uid($ml, $headerArr->Msgno);
                        $s = imap_fetch_overview($ml, $uid, FT_UID);
                        $this->allEmails[$uid]['email'] = $email;
                        $this->allEmails[$uid]['subj'] = imap_utf8($s[0]->subject);
                    }
                }
            }
            imap_close($ml);
            $this->findRightMail();
            return true;
        } catch (\Exception $e) {
            throw new \Exception('ошибка получения почты', 422);
        }
    }

    /**
     * Получаем письма, которые соответствуют шаблону
     */
    protected function findRightMail()
    {
        foreach ($this->allEmails as $uid => $value) {
            if (preg_match($this->pattern['subj'], $value['subj'])) {
                if (array_search(strtolower($value['email']), $this->allowAddressEmail) !== false) {
                    $this->rightEmails[$uid] = $value;
                }
            }
        }
    }

    /**
     * Метод проверки имен файлов
     * выбиравем только, например, ml0179578_1610.xls
     * @param string $fname
     * @return bool
     */
    private static function isFileName(string $fname): bool
    {
        $f = substr($fname, 0, 2);
        if($f == 'ml') {
            $pos = stripos($fname, 'ReestrVozvratov');
            if ($pos === false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Сохранение вложений
     * @param int $uid
     * @return bool
     * @throws \Exception
     */
    public function loadAttach(int $uid, string $pathAttachment)
    {
        global $part_array;
        $part_array = null;
        try {
            $path = $_SERVER['DOCUMENT_ROOT'] . '/' . $pathAttachment . '/' . $uid;
            if (!file_exists($path)) {
                if (!mkdir($path, 0700, true)) {
                    throw new \Exception('ошибка создания папки для файлов', 422);
                }
            } else {
                array_map('unlink', glob($path . '/*.xls'));
            }
            set_time_limit(40000);
            $inbox = imap_open($this->host, $this->username, $this->password);
            $structure = imap_fetchstructure($inbox, $uid, FT_UID);
            $part_array = $this->create_part_array($structure);
            foreach ($part_array as $key => $attach) {
                if (($attach['part_object']->type == 3) &&
                    (strtoupper($attach['part_object']->disposition) == 'ATTACHMENT') &&
                    ($attach['part_object']->ifdparameters == 1) &&
                    ((strtoupper($attach['part_object']->dparameters[0]->attribute) == 'FILENAME') OR
                        (strtoupper($attach['part_object']->dparameters[1]->attribute) == 'FILENAME'))) {
                    if (isset($attach['part_object']->dparameters)) {
                        foreach ($attach['part_object']->dparameters as $k => $fname) {
                            if (strtoupper($fname->attribute) == 'FILENAME') {
                                $structureKey = $k;
                                continue;
                            }
                        }
                    }
                    if ($structureKey == -1) {
                        throw new \Exception('Не возможно найти вложения. Программа завершена', 200);
                    }
                    $filename = $attach['part_object']->dparameters[$structureKey]->value;
                    if (self::isFileName($filename)) {
                        //echo($filename) . '<br>';
                        $file_attachment = imap_fetchbody($inbox, $uid, $attach['part_number'], FT_UID);
                        if ($attach['part_object']->encoding == 3) {
                            $file_attachment = base64_decode($file_attachment);
                        } elseif ($attach['part_object']->encoding == 4) {
                            $file_attachment = quoted_printable_decode($file_attachment);
                        }
                        // сохраняем по пути files/attachments/{uid}/filename.xls
                        $fp = fopen($path . '/' . $filename, "w+");
                        if (fwrite($fp, $file_attachment)) {
                            $this->attachmentsFiles[$uid][] = $filename;
                        };
                        fclose($fp);
                    }
                }
            }
            imap_close($inbox);
            return true;
        } catch (\Exception $e) {
            throw new \Exception('ошибка при сохранении файлов', 422);
        }
        return false;
    }


    /**
     * Отправка почтового сообщения
     *
     * @param $mailTo string
     * @param $from string
     * @param $subject string
     * @param $message string
     * @param bool $file
     * @return mixed
     */
    public function sendMailAttachment($mailTo, $from, $subject, $message, $file = false)
    {
        $separator = "---"; // разделитель в письме
        // Заголовки для письма
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "From: $from\nReply-To: $from\n"; // задаем от кого письмо
        $headers .= "Content-Type: multipart/mixed; boundary=\"$separator\""; // в заголовке указываем разделитель
        // если письмо с вложением
        if ($file) {
            $bodyMail = "--$separator\n"; // начало тела письма, выводим разделитель
            $bodyMail .= "Content-type: text/html; charset='utf-8'\n"; // кодировка письма
            $bodyMail .= "Content-Transfer-Encoding: quoted-printable"; // задаем конвертацию письма
            $bodyMail .= "Content-Disposition: attachment; filename==?utf-8?B?" . base64_encode(basename($file)) . "?=\n\n"; // задаем название файла
            $bodyMail .= $message . "\n"; // добавляем текст письма
            $bodyMail .= "--$separator\n";
            $fileRead = fopen($file, "r"); // открываем файл
            $contentFile = fread($fileRead, filesize($file)); // считываем его до конца
            fclose($fileRead); // закрываем файл
            $bodyMail .= "Content-Type: application/octet-stream; name==?utf-8?B?" . base64_encode(basename($file)) . "?=\n";
            $bodyMail .= "Content-Transfer-Encoding: base64\n"; // кодировка файла
            $bodyMail .= "Content-Disposition: attachment; filename==?utf-8?B?" . base64_encode(basename($file)) . "?=\n\n";
            $bodyMail .= chunk_split(base64_encode($contentFile)) . "\n"; // кодируем и прикрепляем файл
            $bodyMail .= "--" . $separator . "--\n";
            // письмо без вложения
        } else {
            $bodyMail = $message;
        }
        $mailSMTP = new SendMailSmtpClass(MAIL_USER_NAME_GOOGLE, MAIL_USER_PASSWORD_GOOGLE, 'ssl://smtp.gmail.com', 'DmitryPHP', 465); // создаем экземпляр класса
        $result = $mailSMTP->send($mailTo, $subject, $bodyMail, $headers); // отправляем письмо
        return $result;
    }


    public function sendMail($to, $subj, $file, $attach, $drivers)
    {
        $file = DIR_SAVE_ML . '/' . $file; // файл аттач
        //$mailTo = $to;  //" turchin.vladimir@omega-auto.biz"; // кому
        $mailTo = "dima@udt.dp.ua"; // кому
        $from = MAIL_USER_NAME_SSQ_PP_UA; //"omega@udt.dp.ua"; // от кого
        $subject = 'Сформированный файл по запросу ' . $subj; //"Test file"; // тема письма

        if(isset($drivers)) {
            $message = '<br>Информация по водителям:</b><br><br>';
            foreach ($drivers as $driver) {
                if($driver['ml'] == 1) {
                    $message .= $driver['driver'] . ' запланирован в маршруте<br>';
                } elseif ($driver['ml'] == 0) {
                    $message .= "<b style='color:#ff081b'>" . $driver['driver'] . " не запланирован в маршруте</b><br>";
                }
            }
        }


        $message .= "<br>Обработанные файлы:<br>";
        foreach ($attach as $key => $val) {
            $message .= $key + 1 .' - ' . $val . "<br>";
        }
        //print_r($message);
        //$message = //"Тестовое письмо с вложением"; // текст письма
        $r = $this->sendMailAttachment($mailTo, $from, $subject, $message, $file); // отправка письма c вложением
        //echo ($r) ? 'Письмо отправлено' : 'Ошибка. Письмо не отправлено!'
        return $r;
    }

    // удаляем письмо из почтового ящика
    public function delMail($host, $uid) {
        $arr_attach = Array();
        if($host == MAIL_HOST_YANDEX) {
            $username = MAIL_USER_NAME_YANDEX;
            $password = MAIL_USER_PASSWORD_YANDEX;
        } elseif ($host == MAIL_HOST_GOOGLE) {
            $username = MAIL_USER_NAME_GOOGLE;
            $password = MAIL_USER_PASSWORD_GOOGLE;
        } elseif ($host == MAIL_HOST_SSQ_PP_UA) {
            $username = MAIL_USER_NAME_SSQ_PP_UA;
            $password = MAIL_USER_PASSWORD_SSQ_PP_UA;
        } elseif ($host == MAIL_HOST_YAHOO) {
            $username = MAIL_USER_NAME_YAHOO;
            $password = MAIL_USER_PASSWORD_YAHOO;
        }
        $inbox = imap_open($host, $username, $password) or die('Cannot connect to mailbox: ' . imap_last_error());
        imap_delete ($inbox, $uid, FT_UID);
        imap_expunge ($inbox);
        imap_close($inbox);
    }
}