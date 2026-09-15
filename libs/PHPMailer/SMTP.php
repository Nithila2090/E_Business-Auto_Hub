<?php
/**
 * PHPMailer RFC821 SMTP class.
 * PHP Version 8+
 */

namespace PHPMailer\PHPMailer;

class SMTP
{
    const VERSION = '6.9.1';
    const DEFAULT_PORT = 25;
    const DEFAULT_SECURE_PORT = 465;
    const CRLF = "\r\n";
    const DEBUG_OFF = 0;
    const DEBUG_CLIENT = 1;
    const DEBUG_SERVER = 2;

    public $do_debug = self::DEBUG_OFF;
    public $Debugoutput = 'echo';
    public $do_verp = false;
    public $Timeout = 300;
    public $Timelimit = 300;

    protected $smtp_conn;
    protected $error = ['error' => '', 'detail' => '', 'smtp_code' => '', 'smtp_code_ex' => ''];
    protected $helo_rply;
    protected $server_caps;
    protected $last_reply = '';

    public function connect($host, $port = null, $timeout = 30, $options = [])
    {
        $this->setError('');
        if ($this->connected()) {
            $this->setError('Already connected to a server');
            return false;
        }
        if (empty($port)) {
            $port = self::DEFAULT_PORT;
        }

        $socket_context = stream_context_create($options);
        set_error_handler([$this, 'errorHandler']);
        $this->smtp_conn = stream_socket_client(
            $host . ':' . $port,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $socket_context
        );
        restore_error_handler();

        if (!is_resource($this->smtp_conn)) {
            $this->setError(
                'Failed to connect to server',
                '',
                (string) $errno,
                $errstr
            );
            return false;
        }

        stream_set_timeout($this->smtp_conn, $this->Timeout, 0);
        $announce = $this->get_lines();
        return true;
    }

    public function startTLS()
    {
        if (!$this->sendCommand('STARTTLS', 'STARTTLS', 220)) {
            return false;
        }
        $crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
        }
        set_error_handler([$this, 'errorHandler']);
        $crypto_ok = stream_socket_enable_crypto($this->smtp_conn, true, $crypto_method);
        restore_error_handler();
        return (bool) $crypto_ok;
    }

    public function authenticate($username, $password, $authtype = 'LOGIN')
    {
        if (!$this->server_caps) {
            $this->setError('Authentication error: HELO/EHLO was not sent');
            return false;
        }
        if ($authtype === 'LOGIN') {
            if (!$this->sendCommand('AUTH LOGIN', 'AUTH LOGIN', 334)) {
                return false;
            }
            if (!$this->sendCommand('User name', base64_encode($username), 334)) {
                return false;
            }
            if (!$this->sendCommand('Password', base64_encode($password), 235)) {
                return false;
            }
            return true;
        }
        $this->setError("Authentication method \"$authtype\" is not supported");
        return false;
    }

    public function connected()
    {
        if (is_resource($this->smtp_conn)) {
            $sock_status = stream_get_meta_data($this->smtp_conn);
            if ($sock_status['eof']) {
                $this->close();
                return false;
            }
            return true;
        }
        return false;
    }

    public function close()
    {
        $this->setError('');
        $this->server_caps = null;
        $this->helo_rply = null;
        if (is_resource($this->smtp_conn)) {
            fclose($this->smtp_conn);
            $this->smtp_conn = null;
        }
    }

    public function hello($host = '')
    {
        return (bool) ($this->sendHello('EHLO', $host) || $this->sendHello('HELO', $host));
    }

    protected function sendHello($hello, $host)
    {
        $noerror = $this->sendCommand($hello, $hello . ' ' . $host, 250);
        $this->helo_rply = $this->last_reply;
        if ($noerror) {
            $this->parseHelloFields($hello);
        } else {
            $this->server_caps = null;
        }
        return $noerror;
    }

    protected function parseHelloFields($type)
    {
        $this->server_caps = [];
        $lines = explode("\n", $this->helo_rply);
        foreach ($lines as $n => $s) {
            $s = trim(substr($s, 4));
            if (empty($s)) {
                continue;
            }
            $fields = explode(' ', $s);
            if (!empty($fields)) {
                if (!$n) {
                    $name = $type;
                    $fields = $fields[0];
                } else {
                    $name = array_shift($fields);
                }
                $this->server_caps[$name] = (count($fields) ? $fields : true);
            }
        }
    }

    public function mail($from)
    {
        $useVerp = ($this->do_verp ? ' XVERP' : '');
        return $this->sendCommand('MAIL FROM', 'MAIL FROM:<' . $from . '>' . $useVerp, 250);
    }

    public function recipient($address)
    {
        return $this->sendCommand('RCPT TO', 'RCPT TO:<' . $address . '>', [250, 251]);
    }

    public function data()
    {
        return $this->sendCommand('DATA', 'DATA', 354);
    }

    public function quit($close_on_error = true)
    {
        $noerror = $this->sendCommand('QUIT', 'QUIT', 221);
        $err = $this->error;
        if ($noerror || $close_on_error) {
            $this->close();
            $this->error = $err;
        }
        return $noerror;
    }

    public function client_send($data, $check_chars = true)
    {
        if (is_resource($this->smtp_conn)) {
            return fwrite($this->smtp_conn, $data);
        }
        return false;
    }

    protected function sendCommand($commandName, $command, $expect)
    {
        if (!$this->connected()) {
            $this->setError("Called $commandName() without being connected");
            return false;
        }
        $this->client_send($command . self::CRLF);
        $this->last_reply = $this->get_lines();
        $code = (int) substr($this->last_reply, 0, 3);
        if (!in_array($code, (array) $expect, true)) {
            $this->setError("$commandName command failed", $this->last_reply, (string) $code);
            return false;
        }
        $this->setError('');
        return true;
    }

    protected function get_lines()
    {
        if (!is_resource($this->smtp_conn)) {
            return '';
        }
        $data = '';
        $endtime = 0;
        stream_set_timeout($this->smtp_conn, $this->Timeout);
        if ($this->Timelimit > 0) {
            $endtime = time() + $this->Timelimit;
        }
        while (is_resource($this->smtp_conn) && !feof($this->smtp_conn)) {
            $str = @fgets($this->smtp_conn, 515);
            $data .= $str;
            if ((isset($str[3]) && $str[3] === ' ') || !isset($str[3])) {
                break;
            }
            $info = stream_get_meta_data($this->smtp_conn);
            if ($info['timed_out']) {
                break;
            }
            if ($endtime && time() > $endtime) {
                break;
            }
        }
        return $data;
    }

    public function getError()
    {
        return $this->error;
    }

    public function getLastReply()
    {
        return $this->last_reply;
    }

    protected function setError($message, $detail = '', $smtp_code = '', $smtp_code_ex = '')
    {
        $this->error = [
            'error' => $message,
            'detail' => $detail,
            'smtp_code' => $smtp_code,
            'smtp_code_ex' => $smtp_code_ex,
        ];
    }

    protected function errorHandler($errno, $errmsg, $errfile = '', $errline = 0)
    {
        $this->setError('Connection failed', $errmsg, (string) $errno);
    }
}
