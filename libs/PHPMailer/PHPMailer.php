<?php
/**
 * PHPMailer - PHP email creation and transport class.
 * PHP Version 8+
 */

namespace PHPMailer\PHPMailer;

require_once __DIR__ . '/Exception.php';
require_once __DIR__ . '/SMTP.php';

class PHPMailer
{
    const CHARSET_UTF8 = 'utf-8';
    const ENCODING_BASE64 = 'base64';

    public $Priority;
    public $CharSet = self::CHARSET_UTF8;
    public $ContentType = 'text/html';
    public $Encoding = self::ENCODING_BASE64;
    public $From = 'root@localhost';
    public $FromName = 'Root User';
    public $Sender = '';
    public $Subject = '';
    public $Body = '';
    public $AltBody = '';
    public $WordWrap = 0;
    public $Mailer = 'smtp';
    public $Host = 'localhost';
    public $Port = 25;
    public $SMTPSecure = '';
    public $SMTPAuth = false;
    public $Username = '';
    public $Password = '';
    public $Timeout = 300;
    public $SMTPDebug = 0;
    public $Debugoutput = 'echo';
    public $SMTPOptions = [];

    protected $smtp;
    protected $to = [];
    protected $cc = [];
    protected $bcc = [];
    protected $ReplyTo = [];
    protected $all_recipients = [];
    protected $attachment = [];
    protected $CustomHeader = [];
    protected $message_type = '';
    protected $boundary = [];
    protected $language = [];
    protected $error_count = 0;
    protected $sign_cert_file = '';
    protected $sign_key_file = '';
    protected $sign_extracerts_file = '';
    protected $sign_key_pass = '';
    protected $exceptions = false;
    protected $ErrorInfo = '';

    public function __construct($exceptions = null)
    {
        if ($exceptions !== null) {
            $this->exceptions = (bool) $exceptions;
        }
    }

    public function isSMTP()
    {
        $this->Mailer = 'smtp';
    }

    public function isHTML($isHtml = true)
    {
        if ($isHtml) {
            $this->ContentType = 'text/html';
        } else {
            $this->ContentType = 'text/plain';
        }
    }

    public function setFrom($address, $name = '', $auto = true)
    {
        $this->From = $address;
        $this->FromName = $name;
        if ($auto && empty($this->Sender)) {
            $this->Sender = $address;
        }
        return true;
    }

    public function addAddress($address, $name = '')
    {
        return $this->addOrEnqueueAnAddress('to', $address, $name);
    }

    public function addReplyTo($address, $name = '')
    {
        return $this->addOrEnqueueAnAddress('Reply-To', $address, $name);
    }

    protected function addOrEnqueueAnAddress($kind, $address, $name)
    {
        $address = trim($address);
        $name = trim(preg_replace('/[\r\n]+/', '', $name));
        $params = [$address, $name];
        $this->all_recipients[strtolower($address)] = true;
        if ($kind === 'to') {
            $this->to[] = $params;
        } elseif ($kind === 'Reply-To') {
            $this->ReplyTo[] = $params;
        }
        return true;
    }

    public function send()
    {
        try {
            if (!$this->preSend()) {
                return false;
            }
            return $this->postSend();
        } catch (Exception $exc) {
            $this->setError($exc->getMessage());
            if ($this->exceptions) {
                throw $exc;
            }
            return false;
        }
    }

    public function preSend()
    {
        if (empty($this->to)) {
            throw new Exception('You must provide at least one recipient email address.');
        }
        return true;
    }

    public function postSend()
    {
        if ($this->Mailer === 'smtp') {
            return $this->smtpSend();
        }
        return mail($this->to[0][0], $this->Subject, $this->Body, "From: " . $this->From);
    }

    protected function smtpSend()
    {
        $this->smtp = new SMTP();
        $this->smtp->do_debug = $this->SMTPDebug;
        $this->smtp->Debugoutput = $this->Debugoutput;
        $this->smtp->Timeout = $this->Timeout;

        $hosts = explode(';', $this->Host);
        $connected = false;
        foreach ($hosts as $hostentry) {
            $host = trim($hostentry);
            $port = $this->Port;
            $tls = (strtolower($this->SMTPSecure) === 'tls');
            $ssl = (strtolower($this->SMTPSecure) === 'ssl');
            if ($ssl) {
                $host = 'ssl://' . $host;
            }

            if ($this->smtp->connect($host, $port, $this->Timeout, $this->SMTPOptions)) {
                $connected = true;
                break;
            }
        }

        if (!$connected) {
            $err = $this->smtp->getError();
            throw new Exception('SMTP connect() failed. ' . ($err['error'] ?? ''));
        }

        if (!$this->smtp->hello(gethostname() ?: 'localhost')) {
            throw new Exception('SMTP EHLO/HELO failed: ' . $this->smtp->getLastReply());
        }

        if (strtolower($this->SMTPSecure) === 'tls') {
            if (!$this->smtp->startTLS()) {
                throw new Exception('SMTP STARTTLS failed: ' . $this->smtp->getLastReply());
            }
            $this->smtp->hello(gethostname() ?: 'localhost');
        }

        if ($this->SMTPAuth) {
            if (!$this->smtp->authenticate($this->Username, $this->Password)) {
                throw new Exception('SMTP Authentication failed: ' . $this->smtp->getLastReply());
            }
        }

        if (!$this->smtp->mail($this->From)) {
            throw new Exception('SMTP MAIL FROM failed: ' . $this->smtp->getLastReply());
        }

        foreach ($this->to as $toAddr) {
            if (!$this->smtp->recipient($toAddr[0])) {
                throw new Exception('SMTP RCPT TO failed for ' . $toAddr[0] . ': ' . $this->smtp->getLastReply());
            }
        }

        if (!$this->smtp->data()) {
            throw new Exception('SMTP DATA command failed: ' . $this->smtp->getLastReply());
        }

        $header = "Date: " . date('r') . "\r\n";
        $header .= "To: " . $this->to[0][0] . "\r\n";
        $header .= "From: =?utf-8?B?" . base64_encode($this->FromName) . "?= <" . $this->From . ">\r\n";
        $header .= "Subject: =?utf-8?B?" . base64_encode($this->Subject) . "?=\r\n";
        $header .= "MIME-Version: 1.0\r\n";
        $header .= "Content-Type: " . $this->ContentType . "; charset=" . $this->CharSet . "\r\n";
        $header .= "Content-Transfer-Encoding: " . $this->Encoding . "\r\n\r\n";

        $body = ($this->Encoding === self::ENCODING_BASE64) ? chunk_split(base64_encode($this->Body)) : $this->Body;

        $this->smtp->client_send($header . $body . "\r\n.\r\n");
        $reply = $this->smtp->getLastReply();
        $this->smtp->quit();

        return true;
    }

    protected function setError($msg)
    {
        $this->error_count++;
        $this->ErrorInfo = $msg;
    }

    public function getErrorInfo()
    {
        return $this->ErrorInfo;
    }
}
