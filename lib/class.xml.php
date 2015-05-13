<?php

require_once __DIR__ . '/interface.transformer.php';
require_once __DIR__ . '/class.transformexception.php';

Class XMLFormatter implements Transformer
{
    public function accepts()
    {
        return 'text/xml, */*';
    }

    public function transform($data)
    {
        if (!General::validateXML($data, $errors, false, new XsltProcess)) {
            throw new TransformException('Data returned is invalid.', $errors);
        }

        return $data;
    }
}

return 'XMLFormatter';