<?php

require_once __DIR__ . '/interface.transformer.php';
require_once __DIR__ . '/class.transformexception.php';

class CSVFormatter implements Transformer
{
    public function accepts()
    {
        return 'text/csv, */*';
    }

    public function transform($data)
    {
        try {
            $data = self::convertToXML($data);
        } catch (Exception $ex) {
            throw new TransformException($ex->getMessage(), array(
                'message' => $ex->getMessages()
            ));
        }

        return $data;
    }

    /**
     * Given a CSV file, generate a resulting XML tree
     *
     * @param  string $data
     * @return string
     */
    public static function convertToXML($data)
    {
        $headers = array();

        // Get CSV settings
        $settings = array(
            'csv-delimiter' => ',',
            'csv-enclosure' => '"',
            'csv-escape' => '\\'
        );
        $settings = array_merge($settings, (array) Symphony::Configuration()->get('remote_datasource'));

        // DOMDocument
        $doc = new DOMDocument('1.0', 'utf-8');
        $doc->formatOutput = true;

        $root = $doc->createElement('data');
        $doc->appendChild($root);

        foreach (str_getcsv($data, PHP_EOL) as $i => $row) {
            if (empty($row)) {
                continue;
            }

            if ($i == 0) {
                foreach (str_getcsv($row, $settings['csv-delimiter'], $settings['csv-enclosure'], $settings['csv-escape']) as $i => $head) {
                    if (class_exists('Lang')) {
                        $head = Lang::createHandle($head);
                    }
                    $headers[] = $head;
                }
            } else {
                self::addRow($doc, $root, str_getcsv($row, $settings['csv-delimiter'], $settings['csv-enclosure'], $settings['csv-escape']), $headers);
            }
        }

        $output = $doc->saveXML($doc->documentElement);

        return trim($output);
    }

    /**
     * @param DOMDocument $doc
     * @param DOMElement  $root
     * @param array       $row
     * @param array       $headers
     */
    public static function addRow(DOMDocument $doc, DOMElement $root, $row, $headers)
    {
        // Create <entry><header>value</header></entry>
        $entry = $doc->createElement('entry');

        foreach ($headers as $i => $header) {
            $col = $doc->createElement($header);
            $col = $entry->appendChild($col);

            $value = $doc->createTextNode($row[$i]);
            $value = $col->appendChild($value);
        }

        $root->appendChild($entry);
    }
}

return 'CSVFormatter';
