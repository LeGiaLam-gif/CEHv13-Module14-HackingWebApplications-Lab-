<?php
function excel_column_name(int $column): string {
    $name = "";
    while ($column > 0) {
        $column--;
        $name = chr(65 + ($column % 26)) . $name;
        $column = intdiv($column, 26);
    }

    return $name;
}

function excel_xml_string(string $value): string {
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, "UTF-8");
}

function stream_xlsx(string $filename, array $headers, array $rows, array $numericColumns = []): void {
    if (!class_exists(ZipArchive::class)) {
        throw new RuntimeException("Không thể xuất Excel vì PHP chưa bật tiện ích ZIP.");
    }

    $temporaryFile = tempnam(sys_get_temp_dir(), "product-export-");
    if ($temporaryFile === false) {
        throw new RuntimeException("Không thể tạo tệp Excel tạm thời.");
    }

    try {
        $sheetRows = [];
        $allRows = array_merge([$headers], $rows);
        foreach ($allRows as $rowIndex => $row) {
            $cells = [];
            foreach (array_values($row) as $columnIndex => $value) {
                $reference = excel_column_name($columnIndex + 1) . ($rowIndex + 1);
                if ($rowIndex > 0 && in_array($columnIndex, $numericColumns, true)) {
                    if (!is_numeric($value) || !is_finite((float) $value)) {
                        throw new RuntimeException("Dữ liệu số trong tệp Excel không hợp lệ.");
                    }
                    $cells[] = '<c r="' . $reference . '"><v>' . (string) $value . '</v></c>';
                    continue;
                }

                $cells[] = '<c r="' . $reference . '" t="inlineStr"><is><t xml:space="preserve">'
                    . excel_xml_string((string) ($value ?? ""))
                    . '</t></is></c>';
            }
            $sheetRows[] = '<row r="' . ($rowIndex + 1) . '">' . implode("", $cells) . '</row>';
        }

        $files = [
            "[Content_Types].xml" => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '</Types>',
            "_rels/.rels" => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                . '</Relationships>',
            "xl/workbook.xml" => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<sheets><sheet name="San pham" sheetId="1" r:id="rId1"/></sheets>'
                . '</workbook>',
            "xl/_rels/workbook.xml.rels" => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                . '</Relationships>',
            "xl/worksheets/sheet1.xml" => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
                . implode("", $sheetRows)
                . '</sheetData></worksheet>',
        ];

        $zip = new ZipArchive();
        if ($zip->open($temporaryFile, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Không thể khởi tạo tệp Excel.");
        }

        foreach ($files as $path => $content) {
            if (!$zip->addFromString($path, $content)) {
                $zip->close();
                throw new RuntimeException("Không thể ghi dữ liệu vào tệp Excel.");
            }
        }
        if (!$zip->close()) {
            throw new RuntimeException("Không thể hoàn tất tệp Excel.");
        }

        if (!is_file($temporaryFile) || !is_readable($temporaryFile)) {
            throw new RuntimeException("Không thể đọc tệp Excel đã tạo.");
        }

        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header("X-Content-Type-Options: nosniff");
        header("Cache-Control: private, no-store, max-age=0");
        if (readfile($temporaryFile) === false) {
            throw new RuntimeException("Không thể gửi tệp Excel đã tạo.");
        }
    } finally {
        if (is_file($temporaryFile)) {
            unlink($temporaryFile);
        }
    }
}
