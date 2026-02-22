<?php
/**
 * export.php – eksport danych uczestników do CSV lub XLSX
 * Dostępny tylko dla zalogowanego administratora
 */

declare(strict_types=1);
require_once __DIR__ . '/config.php';

startSecureSession();

// Ochrona – tylko admin
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

$format = strtolower(trim($_GET['format'] ?? ''));
if (!in_array($format, ['csv', 'xlsx'], true)) {
    die('Nieprawidłowy format eksportu.');
}

$pdo  = getDB();
$stmt = $pdo->query(
    'SELECT id, imie, nazwisko, adres, miasto, email, telefon, wiek, wzrost, waga, data_zapisu
     FROM participants
     ORDER BY data_zapisu ASC'
);
$rows = $stmt->fetchAll();

$headers = ['ID', 'Imię', 'Nazwisko', 'Adres', 'Miasto', 'E-mail', 'Telefon', 'Wiek', 'Wzrost (cm)', 'Waga (kg)', 'Data zapisu'];
$filename = 'uczestnicy_' . date('Ymd_His');

// ---------------------------------------------------------------
// CSV
// ---------------------------------------------------------------
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'wb');
    // BOM dla Excel (UTF-8)
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers, ';');
    foreach ($rows as $row) {
        fputcsv($out, [
            $row['id'],
            $row['imie'],
            $row['nazwisko'],
            $row['adres'],
            $row['miasto'],
            $row['email'],
            $row['telefon'],
            $row['wiek'],
            $row['wzrost'],
            $row['waga'],
            $row['data_zapisu'],
        ], ';');
    }
    fclose($out);
    exit;
}

// ---------------------------------------------------------------
// XLSX (czyste PHP – bez zewnętrznych bibliotek)
// ---------------------------------------------------------------
if ($format === 'xlsx') {
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo buildXlsx($headers, $rows);
    exit;
}

/* ---------------------------------------------------------------
   Buduje plik .xlsx bez zewnętrznych zależności (używa ZipArchive)
   --------------------------------------------------------------- */
function buildXlsx(array $headers, array $rows): string
{
    // Zbieramy wszystkie wiersze
    $sheetRows   = [];
    $sheetRows[] = $headers;
    foreach ($rows as $row) {
        $sheetRows[] = [
            $row['id'],
            $row['imie'],
            $row['nazwisko'],
            $row['adres'],
            $row['miasto'],
            $row['email'],
            $row['telefon'],
            $row['wiek'],
            $row['wzrost'],
            $row['waga'],
            $row['data_zapisu'],
        ];
    }

    // Generujemy XML arkusza
    $sharedStrings = [];
    $ssIndex       = [];

    // Funkcja zamieniająca wartość na indeks w sharedStrings
    $getSSIndex = function (string $val) use (&$sharedStrings, &$ssIndex): int {
        if (!isset($ssIndex[$val])) {
            $ssIndex[$val]  = count($sharedStrings);
            $sharedStrings[] = $val;
        }
        return $ssIndex[$val];
    };

    $sheetXml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $sheetXml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    $sheetXml .= '<sheetData>';

    // Kolumny numeryczne według indeksu (0-based):
    // 0=id, 7=wiek, 8=wzrost, 9=waga – reszta jako tekst
    $numericColumns = [0 => true, 7 => true, 8 => true, 9 => true];

    foreach ($sheetRows as $rIdx => $rowData) {
        $rowNum   = $rIdx + 1;
        $sheetXml .= '<row r="' . $rowNum . '">';
        foreach ($rowData as $cIdx => $cellVal) {
            $colLetter = colLetter($cIdx);
            $cellRef   = $colLetter . $rowNum;
            $cellVal   = (string) $cellVal;
            // Traktuj jako liczbę tylko kolumny jawnie oznaczone jako numeryczne
            // (pomijamy wiersz nagłówka rIdx=0)
            if ($rIdx > 0 && isset($numericColumns[$cIdx]) && is_numeric($cellVal)) {
                $sheetXml .= '<c r="' . $cellRef . '" t="n"><v>' . htmlspecialchars($cellVal, ENT_XML1) . '</v></c>';
            } else {
                $sIdx      = $getSSIndex($cellVal);
                $sheetXml .= '<c r="' . $cellRef . '" t="s"><v>' . $sIdx . '</v></c>';
            }
        }
        $sheetXml .= '</row>';
    }

    $sheetXml .= '</sheetData></worksheet>';

    // sharedStrings XML
    $ssCount  = count($sharedStrings);
    $ssXml    = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $ssXml   .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . $ssCount . '" uniqueCount="' . $ssCount . '">';
    foreach ($sharedStrings as $s) {
        $ssXml .= '<si><t xml:space="preserve">' . htmlspecialchars($s, ENT_XML1, 'UTF-8') . '</t></si>';
    }
    $ssXml .= '</sst>';

    // Pliki OOXML
    $files = [
        '_rels/.rels'                                        => rels(),
        'xl/_rels/workbook.xml.rels'                        => workbookRels(),
        '[Content_Types].xml'                               => contentTypes(),
        'xl/workbook.xml'                                   => workbookXml(),
        'xl/worksheets/sheet1.xml'                          => $sheetXml,
        'xl/sharedStrings.xml'                              => $ssXml,
        'xl/styles.xml'                                     => stylesXml(),
    ];

    // Tworzymy ZIP w pamięci
    $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
    $zip     = new ZipArchive();
    if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        die('Nie można utworzyć pliku ZIP.');
    }
    foreach ($files as $name => $content) {
        $zip->addFromString($name, $content);
    }
    $zip->close();

    $data = file_get_contents($tmpFile);
    unlink($tmpFile);
    return $data;
}

function colLetter(int $index): string
{
    $letter = '';
    $index++;
    while ($index > 0) {
        $index--;
        $letter = chr(65 + ($index % 26)) . $letter;
        $index  = (int) ($index / 26);
    }
    return $letter;
}

function rels(): string
{
    return '<?xml version="1.0" encoding="UTF-8"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';
}

function workbookRels(): string
{
    return '<?xml version="1.0" encoding="UTF-8"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
        . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';
}

function contentTypes(): string
{
    return '<?xml version="1.0" encoding="UTF-8"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml"  ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';
}

function workbookXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Uczestnicy" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';
}

function stylesXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
        . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
        . '</styleSheet>';
}
