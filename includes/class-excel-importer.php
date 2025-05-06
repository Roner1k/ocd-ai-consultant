<?php

namespace Ocd\AiConsultant;

use PhpOffice\PhpSpreadsheet\IOFactory;

class ExcelImporter
{
    public static function importFromUpload(array $file): array
    {
        $upload_dir = wp_upload_dir();
        $target_dir = trailingslashit($upload_dir['basedir']) . 'ocd-ai-imports';

        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $base_name = pathinfo($file['name'], PATHINFO_FILENAME);
        $timestamp = current_time('Ymd_His');
        $filename = sanitize_file_name($base_name . '_' . $timestamp . '.' . $extension);
        $target_path = trailingslashit($target_dir) . $filename;

        if (!move_uploaded_file($file['tmp_name'], $target_path)) {
            return ['success' => false, 'message' => 'Failed to upload file.'];
        }

        try {
            $spreadsheet = IOFactory::load($target_path);
            $allSheets = $spreadsheet->getAllSheets();

            global $wpdb;
            $table = $wpdb->prefix . 'ocd_ai_knowledge_base';
            $inserted = 0;
            $columnCount = 0;

            // определим кол-во колонок по первому листу
            if (isset($allSheets[0])) {
                $firstRow = $allSheets[0]->getRowIterator()->current();
                $columnCount = iterator_count($firstRow->getCellIterator());
                self::recreateTable($columnCount);
            }

            foreach ($allSheets as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = [];

                    foreach ($row->getCellIterator() as $cell) {
                        $cells[] = $cell->getValue();
                    }

                    if (!empty(array_filter($cells))) {
                        $data = [];
                        for ($i = 0; $i < min(count($cells), $columnCount); $i++) {
                            $data["col" . ($i + 1)] = $cells[$i];
                        }
                        $wpdb->insert($table, $data);
                        $inserted++;
                    }
                }
            }

            $settings = get_option('ocd_ai_settings', []);
            $settings['last_import_log'] = sprintf(
                'File: %s | Sheets: %d | Columns: %d | Imported at: %s | Rows: %d | Status: success',
                $filename,
                count($allSheets),
                $columnCount,
                current_time('mysql'),
                $inserted
            );
            update_option('ocd_ai_settings', $settings);

            return ['success' => true, 'message' => 'Imported ' . $inserted . ' rows from ' . count($allSheets) . ' sheet(s).'];
        } catch (\Throwable $e) {
            $settings = get_option('ocd_ai_settings', []);
            $settings['last_import_log'] = sprintf(
                'File: %s | Imported at: %s | Status: failed | Error: %s',
                $filename,
                current_time('mysql'),
                $e->getMessage()
            );
            update_option('ocd_ai_settings', $settings);

            return ['success' => false, 'message' => 'Import failed: ' . $e->getMessage()];
        }
    }


    private static function recreateTable(int $columnCount): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ocd_ai_knowledge_base';

        $wpdb->query("DROP TABLE IF EXISTS `$table`");

        $columns_sql = [];
        for ($i = 1; $i <= $columnCount; $i++) {
            $columns_sql[] = "col$i TEXT NULL";
        }

        $sql = "
            CREATE TABLE `$table` (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                " . implode(",\n", $columns_sql) . ",
                PRIMARY KEY (id)
            ) " . $wpdb->get_charset_collate() . ";
        ";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
