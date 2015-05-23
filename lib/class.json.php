<?php

require_once __DIR__ . '/interface.transformer.php';
require_once __DIR__ . '/class.transformexception.php';
require_once TOOLKIT . '/class.json.php';

Class JSONFormatter implements Transformer
{
    public function accepts()
    {
        return 'application/json, */*';
    }

    public function transform($data)
    {
        try {
            $data = JSON::convertToXML($data);
        } catch (Exception $ex) {
            throw new TransformException($ex->getMessage(), array(
                'message' => $ex->getMessage()
            ));
        }

        return $data;
    }
}

return 'JSONFormatter';