<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * ImportService — CSV/Excel 商品批次匯入
 *
 * 支援格式：CSV、XLSX、XLS
 * 欄位對應（第一列標題）：
 *   code*, name*, category_id*, unit_id*, description,
 *   tax_type, reorder_point, safety_stock, lead_time_days,
 *   sku_code, cost_price, selling_price, attributes
 *
 * 標 * 為必填。
 */
class ImportService
{
    private const REQUIRED_HEADERS = ['code', 'name', 'category_id', 'unit_id'];
    private const MAX_ROWS         = 2000;

    /**
     * 從暫存檔案路徑載入並解析資料
     *
     * @return array{rows: array[], errors: array[]}
     */
    public function parseFile(string $filePath, string $extension): array
    {
        $spreadsheet = $this->loadSpreadsheet($filePath, $extension);
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = $sheet->toArray(null, true, true, false);

        if (empty($rows)) {
            return ['rows' => [], 'errors' => [['row' => 0, 'message' => '檔案無資料']]];
        }

        // 第一列作為標題列
        $headers = array_map('trim', array_map('strtolower', $rows[0]));
        $missing = array_diff(self::REQUIRED_HEADERS, $headers);

        if (! empty($missing)) {
            return [
                'rows'   => [],
                'errors' => [['row' => 1, 'message' => '缺少必要欄位：' . implode(', ', $missing)]],
            ];
        }

        $dataRows = array_slice($rows, 1);

        if (count($dataRows) > self::MAX_ROWS) {
            return [
                'rows'   => [],
                'errors' => [['row' => 0, 'message' => "單次最多匯入 " . self::MAX_ROWS . " 筆"]],
            ];
        }

        $errors  = [];
        $parsed  = [];

        foreach ($dataRows as $rowIndex => $row) {
            $lineNo = $rowIndex + 2; // 1-based + header row
            $mapped = array_combine($headers, $row);

            // 跳過空白列
            if (empty(array_filter($mapped))) {
                continue;
            }

            $rowErrors = $this->validateRow($mapped, $lineNo);

            if (! empty($rowErrors)) {
                $errors = array_merge($errors, $rowErrors);
                continue;
            }

            $parsed[] = $this->normalizeRow($mapped);
        }

        return ['rows' => $parsed, 'errors' => $errors];
    }

    /**
     * 驗證單列資料
     *
     * @return array[]  驗證錯誤陣列
     */
    private function validateRow(array $row, int $lineNo): array
    {
        $errors = [];

        foreach (self::REQUIRED_HEADERS as $field) {
            if (empty($row[$field])) {
                $errors[] = ['row' => $lineNo, 'message' => "第 {$lineNo} 列：{$field} 為必填"];
            }
        }

        if (isset($row['tax_type']) && ! empty($row['tax_type'])) {
            if (! in_array($row['tax_type'], ['taxable', 'zero', 'exempt'], true)) {
                $errors[] = ['row' => $lineNo, 'message' => "第 {$lineNo} 列：tax_type 必須是 taxable/zero/exempt"];
            }
        }

        return $errors;
    }

    /**
     * 正規化單列資料型別
     */
    private function normalizeRow(array $row): array
    {
        $item = [
            'code'           => trim((string) ($row['code'] ?? '')),
            'name'           => trim((string) ($row['name'] ?? '')),
            'category_id'    => (int) ($row['category_id'] ?? 0),
            'unit_id'        => (int) ($row['unit_id'] ?? 0),
            'description'    => trim((string) ($row['description'] ?? '')) ?: null,
            'tax_type'       => $row['tax_type'] ?? 'taxable',
            'reorder_point'  => (float) ($row['reorder_point'] ?? 0),
            'safety_stock'   => (float) ($row['safety_stock'] ?? 0),
            'lead_time_days' => (int) ($row['lead_time_days'] ?? 0),
        ];

        // SKU 資料（可為單一 SKU）
        if (! empty($row['sku_code'])) {
            $item['skus'] = [[
                'sku_code'      => trim((string) $row['sku_code']),
                'cost_price'    => (float) ($row['cost_price'] ?? 0),
                'selling_price' => (float) ($row['selling_price'] ?? 0),
                'attributes'    => $this->parseAttributes($row['attributes'] ?? null),
            ]];
        }

        return $item;
    }

    private function parseAttributes(mixed $value): ?array
    {
        if (empty($value)) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function loadSpreadsheet(string $filePath, string $extension): Spreadsheet
    {
        $type = match (strtolower($extension)) {
            'xlsx' => 'Xlsx',
            'xls'  => 'Xls',
            'csv'  => 'Csv',
            default => throw new \InvalidArgumentException("不支援的檔案格式：{$extension}"),
        };

        $reader = IOFactory::createReader($type);

        if ($type === 'Csv') {
            /** @var \PhpOffice\PhpSpreadsheet\Reader\Csv $reader */
            $reader->setInputEncoding('UTF-8');
        }

        return $reader->load($filePath);
    }
}
