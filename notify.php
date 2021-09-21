<?php

namespace migration;

use dictionaryClass;
use MigrationException;

class notify
{
    /**
     * @var mixed|null
     */
    private $mailTo;

    /**
     * @throws MigrationException
     */
    public function __construct($mailTo = null)
    {
        $this->mailTo = $mailTo;
        $this->validateEmail($this->mailTo);
    }

    public static function init($mailTo = null)
    {
        return new self($mailTo);
    }

    public function sendNotify($body, $options = [])
    {
        return $this->sendMail($body, $options);
    }

    /**
     * @throws MigrationException
     */
    private function validateEmail($email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new MigrationException(dictionaryClass::dictionary("error.notValidEmail"));
        }
    }

    private function sendMail($body, $options)
    {
        return mail($this->mailTo, '[Migration] Service migrate', $this->getBody($body, $options), $this->getHeaders());
    }

    private function getHeaders()
    {
        return [
            'From' => 'gersonalvesdev@gmail.com',
            'Reply-To' => 'gerson.alves@census.inf.br',
            'X-Mailer' => 'PHP/'.phpversion()
        ];
    }

    private function getBody($body, $options)
    {
        $template[] = "Informações sobre migrações";
        $template[] = $body;
        if(isset($options['errors'])){
            $template[] = "Os seguintes erros ocorreram: \n\r";
            $template[] = implode("\n\r", $options['errors']);
        }

        return implode("\n\r", $template);
    }

}
