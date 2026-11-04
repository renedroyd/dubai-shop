<?php
namespace App\Services\CatalogImport;

use DOMDocument;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class ExcelFileReader
{
    public function read(string $path): array
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'csv' => $this->readCsv($path),
            'xlsx' => $this->readXlsx($path),
            default => throw new RuntimeException('El archivo debe estar en formato XLSX o CSV.'),
        };
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) throw new RuntimeException('No fue posible abrir el archivo.');

        try {
            $headers = fgetcsv($handle, null, ',', '"', '\\');
            if (! is_array($headers) || $headers === []) throw new RuntimeException('El archivo está vacío o no contiene encabezados.');
            $headers = array_map(fn ($value) => $this->normalizeHeader((string) $value), $headers);
            $rows = [];
            $number = 1;

            while (($values = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
                $number++;
                if ($this->isBlankRow($values)) continue;
                $rows[] = ['row' => $number, 'data' => $this->combine($headers, $values)];
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function readXlsx(string $path): array
    {
        if (! class_exists(ZipArchive::class)) throw new RuntimeException('El servidor no tiene habilitada la extensión PHP ZIP necesaria para leer archivos XLSX.');
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new RuntimeException('El archivo XLSX no es válido o está dañado.');

        try {
            $shared = $this->readSharedStrings($zip);
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($xml === false) throw new RuntimeException('No se encontró la primera hoja de cálculo del archivo.');
            $document = new DOMDocument();
            if (! $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) throw new RuntimeException('No fue posible interpretar la hoja de cálculo.');

            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $matrix = [];

            foreach ($xpath->query('//x:sheetData/x:row') as $row) {
                $line = [];
                foreach ($xpath->query('./x:c', $row) as $cell) {
                    preg_match('/^[A-Z]+/', $cell->getAttribute('r'), $matches);
                    $column = $this->columnIndex($matches[0] ?? 'A');
                    $type = $cell->getAttribute('t');

                    if ($type === 'inlineStr') {
                        $value = '';
                        foreach ($xpath->query('.//x:is//x:t', $cell) as $part) $value .= $part->textContent;
                    } else {
                        $value = $xpath->query('./x:v', $cell)->item(0)?->textContent ?? '';
                        if ($type === 's') $value = $shared[(int) $value] ?? '';
                    }

                    $line[$column] = $value;
                }

                if ($line !== []) {
                    ksort($line);
                    $matrix[] = $line;
                }
            }

            if ($matrix === []) throw new RuntimeException('La hoja de cálculo no contiene datos.');
            $headers = [];
            foreach ($matrix[0] as $index => $value) $headers[$index] = $this->normalizeHeader((string) $value);
            $rows = [];

            foreach (array_slice($matrix, 1) as $offset => $values) {
                if ($this->isBlankRow($values)) continue;
                $rows[] = ['row' => $offset + 2, 'data' => $this->combine($headers, $values)];
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) return [];
        $document = new DOMDocument();
        if (! $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) return [];
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $strings = [];

        foreach ($xpath->query('//x:si') as $item) {
            $text = '';
            foreach ($xpath->query('.//x:t', $item) as $part) $text .= $part->textContent;
            $strings[] = $text;
        }

        return $strings;
    }

    private function normalizeHeader(string $value): string
    {
        return strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value));
    }

    private function combine(array $headers, array $values): array
    {
        $data = [];
        foreach ($headers as $index => $header) if ($header !== '') $data[$header] = trim((string) ($values[$index] ?? ''));
        return $data;
    }

    private function isBlankRow(array $values): bool
    {
        foreach ($values as $value) if (trim((string) $value) !== '') return false;
        return true;
    }

    private function columnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split($letters) as $letter) $index = ($index * 26) + (ord($letter) - 64);
        return $index - 1;
    }
}
