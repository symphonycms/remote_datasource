<?php

Class TransformException extends Exception
{
    private $errors;

    public function __construct($message, $errors)
    {
        $this->errors = $errors;
        parent::__construct($message);
    }

    public function getErrors()
    {
        return $this->errors;
    }
}