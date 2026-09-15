<?php
/**
 * PHPMailer Exception class.
 * PHP Version 8+
 */

namespace PHPMailer\PHPMailer;

class Exception extends \Exception
{
    /**
     * Prettify the exception message.
     *
     * @return string
     */
    public function errorMessage()
    {
        return '<strong>' . htmlspecialchars($this->getMessage(), ENT_COMPAT | ENT_HTML401) . "</strong><br />\n";
    }
}
