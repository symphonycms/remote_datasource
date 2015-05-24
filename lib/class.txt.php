<?php

require_once __DIR__ . '/interface.transformer.php';
require_once __DIR__ . '/class.transformexception.php';

Class TXTFormatter implements Transformer
{
    public function accepts()
    {
        return 'text/plain, */*';
    }

    public function transform($data)
    {
        $txtElement = new XMLElement('data');
        $txtElement->setValue(General::wrapInCDATA($data));
        $data = $txtElement->generate();

        return $data;
    }
}

return 'TXTFormatter';