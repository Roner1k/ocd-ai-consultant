<?php

namespace Ocd\AiConsultant;

defined('ABSPATH') || exit;

class UserDataBuilder
{
    /**
     * Собирает summary по всем пользователям и сохраняет в таблицу
     */
    public static function rebuildAll()
    {
        $users = get_users(['fields' => ['ID']]);
        foreach ($users as $user) {
            self::rebuildForUser($user->ID);
        }
    }

    /**
     * Собирает summary для одного пользователя и сохраняет в таблицу
     */
    public static function rebuildForUser($user_id)
    {
        $summary = self::buildUserSummary($user_id);
        self::saveUserSummary($user_id, $summary);
        // Логирование
        $method = (function_exists('wp_doing_cron') && wp_doing_cron()) ? 'cron' : 'manual';
        \Ocd\AiConsultant\OcdLog::aiLog('User summary updated', [
            'time' => current_time('mysql'),
            'user_id' => $user_id,
            'method' => $method
        ]);
    }

    /**
     * Синхронизирует summary для пользователя (например, после заполнения формы)
     */
    public static function syncUserData($user_id)
    {
        $summary = self::buildUserSummary($user_id);
        self::saveUserSummary($user_id, $summary);
    }

    /**
     * Формирует агрегированную сводку по результатам тестов из user_meta
     */
    private static function buildUserSummary($user_id)
    {
        $summary = [
            'user_id' => $user_id,
            'compulsion_summary' => [],
            'tests' => []
        ];

        // Проверка: установлен ли ocd-portal (по функции или классу)
        if (!function_exists('get_user_meta')) {
            $summary['error'] = 'ocd-portal or WordPress usermeta functions not available';
            return $summary;
        }

        // 1. Агрегированные данные по компульсиям
        $compulsion_data = get_user_meta($user_id, 'results_test_ocd_compulsion_results', true);
        if (!empty($compulsion_data['compulsion_results']['compulsion_themes']) && is_array($compulsion_data['compulsion_results']['compulsion_themes'])) {
            $themes = $compulsion_data['compulsion_results']['compulsion_themes'];
            
            usort($themes, function ($a, $b) {
                return ($b['result'] ?? 0) <=> ($a['result'] ?? 0);
            });

            $top_themes = array_filter($themes, function($theme) {
                return !empty($theme['result']);
            });
            $summary['compulsion_summary'] = array_slice($top_themes, 0, 5);
        }

        // 2. Данные по отдельным тестам с новой, чистой структурой
        $test_ids = [3, 4, 5, 6, 98]; 
        foreach ($test_ids as $test_id) {
            $meta_key = 'results_test_ocd_' . $test_id;
            $data = get_user_meta($user_id, $meta_key, true);

            if (!$data || !is_array($data)) continue;

            $test_data = $data[$test_id] ?? $data;
            if (empty($test_data)) continue;

            $test_summary = ['test_id' => $test_id];
            $has_data = false;

            switch ($test_id) {
                case 3: // 4 Stages of OCD
                     if (isset($test_data[1], $test_data[2], $test_data[3], $test_data[4])) {
                        $test_summary['stages_scores'] = [
                            'stage_1' => $test_data[1] ?? 0,
                            'stage_2' => $test_data[2] ?? 0,
                            'stage_3' => $test_data[3] ?? 0,
                            'stage_4' => $test_data[4] ?? 0,
                        ];
                        $has_data = true;
                    }
                    break;

                case 4: // OCD Cycle Test
                    if (isset($test_data['current'], $test_data['results']['initial'])) {
                        $current = $test_data['current'];
                        $initial = $test_data['results']['initial'];
                        
                        $test_summary['current_score'] = $current['total_points'] ?? null;
                        $test_summary['initial_score'] = $initial['total_points'] ?? null;
                        
                        if (isset($test_summary['current_score'], $test_summary['initial_score'])) {
                            $progress = $test_summary['current_score'] - $test_summary['initial_score'];
                            $test_summary['progress'] = $progress;
                            if ($progress < -5) $test_summary['progress_evaluation'] = 'significant_improvement';
                            elseif ($progress < 0) $test_summary['progress_evaluation'] = 'improvement';
                            elseif ($progress > 5) $test_summary['progress_evaluation'] = 'significant_worsening';
                            elseif ($progress > 0) $test_summary['progress_evaluation'] = 'worsening';
                            else $test_summary['progress_evaluation'] = 'stable';
                        }
                        $test_summary['completed_count'] = count($test_data['results']['results_history'] ?? []) + 1;
                        $has_data = true;
                    }
                    break;

                case 5: // Types of OCD Test
                    $ocd_types = $test_data['types_of_ocd_answers']['ocd_types_result'] ?? [];
                    if (!empty($ocd_types)) {
                        usort($ocd_types, fn($a, $b) => ($b['ocd_type_percents'] ?? 0) <=> ($a['ocd_type_percents'] ?? 0));
                        $top_types_full = array_slice(array_filter($ocd_types, fn($t) => !empty($t['ocd_type_percents'])), 0, 3);
                        // Формируем новый массив с нужными ключами
                        $test_summary['top_ocd_types'] = array_map(fn($t) => [
                            'type' => $t['ocd_question'] ?? 'Unknown Type',
                            'score' => $t['ocd_type_percents']
                        ], $top_types_full);
                        $test_summary['completed_count'] = $test_data['completed_count'] ?? 1;
                        $has_data = true;
                    }
                    break;

                case 6: // Stuck Test
                    $stuck_result = $test_data['stuck_result'] ?? null;
                    if ($stuck_result && is_array($stuck_result)) {
                        arsort($stuck_result); // Сортируем массив по убыванию, сохраняя ключи
                        $top_themes = array_slice($stuck_result, 0, 3, true);
                        $test_summary['dominant_stuck_theme'] = ['theme' => key($top_themes), 'score' => current($top_themes)];
                        array_shift($top_themes); // убираем доминантную, чтобы не дублировать
                        $test_summary['other_top_themes'] = array_map(fn($k, $v) => ['theme' => $k, 'score' => $v], array_keys($top_themes), array_values($top_themes));
                        $has_data = true;
                    }
                    break;
                
                case 98: // Tricks of OCD Test
                    $tricks = $test_data['results']['tricks_of_ocd'] ?? null;
                    if ($tricks) {
                         usort($tricks, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));
                         $test_summary['top_tricks'] = array_slice($tricks, 0, 3);
                         $has_data = true;
                    }
                    break;
            }

            if ($has_data) {
                $summary['tests'][] = $test_summary;
            }
        }

        return $summary;
    }

    /**
     * Сохраняет summary в таблицу ocd_ai_user_ai_input
     */
    private static function saveUserSummary($user_id, $summary)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ocd_ai_user_ai_input';
        $json = wp_json_encode($summary, JSON_UNESCAPED_UNICODE);
        $now = current_time('mysql');
        // Проверяем, есть ли уже запись для этого пользователя
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE user_id = %d", $user_id));
        if ($exists) {
            $wpdb->update($table, [
                'summary_json' => $json,
                'last_updated' => $now,
            ], ['user_id' => $user_id]);
            } else {
            $wpdb->insert($table, [
                'user_id' => $user_id,
                'summary_json' => $json,
                'last_updated' => $now,
            ]);
        }
    }
}
