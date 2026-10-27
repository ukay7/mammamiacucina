<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class ProductImportTemplate
{
    /** Append an optional ID without rebuilding or losing the existing template's formatting/formulas. */
    public function path(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mmc-dna-');
        if ($path === false) {
            throw new RuntimeException('Cannot create product template.');
        }
        $zip = new ZipArchive;
        $opened = false;
        try {
            if (! copy(resource_path('templates/product-import-template.xlsx'), $path)) {
                throw new RuntimeException('Cannot copy product template.');
            }
            if ($zip->open($path) !== true) {
                throw new RuntimeException('Cannot open product template.');
            }
            $opened = true;
            $doc = new DOMDocument;
            if (! $doc->loadXML($zip->getFromName('xl/worksheets/sheet1.xml'), LIBXML_NONET)) {
                throw new RuntimeException('Cannot read product template.');
            }
            $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
            $xpath = new DOMXPath($doc);
            $xpath->registerNamespace('m', $ns);
            $row = $xpath->query('//m:sheetData/m:row[@r="1"]')->item(0);
            $cell = $doc->createElementNS($ns, 'x:c');
            $cell->setAttribute('r', 'AM1');
            $cell->setAttribute('s', '3');
            $cell->setAttribute('t', 'inlineStr');
            $inline = $doc->createElementNS($ns, 'x:is');
            $inline->appendChild($doc->createElementNS($ns, 'x:t', 'Supplier ID'));
            $cell->appendChild($inline);
            $row->appendChild($cell);
            $zip->addFromString('xl/worksheets/sheet1.xml', $doc->saveXML());
            $zip->close();
            $opened = false;
        } catch (\Throwable $e) {
            if ($opened) {
                $zip->close();
            }
            if (is_file($path)) {
                unlink($path);
            }
            throw $e;
        }

        return $path;
    }
}
