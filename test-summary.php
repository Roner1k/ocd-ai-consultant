<?php
/**
 * Тестовый скрипт для проверки работы UserDataBuilder
 * Запускать только в админке WordPress
 */

// Проверяем, что мы в админке WordPress
if (!defined('ABSPATH')) {
    require_once('../../../wp-load.php');
}

// Проверяем права администратора
if (!current_user_can('manage_options')) {
    wp_die('Access denied');
}

// Подключаем наш класс
require_once __DIR__ . '/includes/class-user-data-builder.php';

use Ocd\AiConsultant\UserDataBuilder;

echo "<h1>Тест UserDataBuilder</h1>";

// Получаем всех пользователей
$users = get_users(['fields' => ['ID', 'display_name']]);

echo "<h2>Найдено пользователей: " . count($users) . "</h2>";

foreach ($users as $user) {
    echo "<hr>";
    echo "<h3>Пользователь: {$user->display_name} (ID: {$user->ID})</h3>";
    
    // Проверяем, есть ли данные тестов
    $test_ids = [59, 60, 96, 97, 98];
    $has_test_data = false;
    
    foreach ($test_ids as $test_id) {
        $meta_key = 'results_test_ocd_' . $test_id;
        $data = get_user_meta($user->ID, $meta_key, true);
        if (!empty($data)) {
            $has_test_data = true;
            echo "<p>✓ Тест {$test_id}: есть данные</p>";
        }
    }
    
    // Проверяем данные компульсий
    $compulsion_data = get_user_meta($user->ID, 'results_test_ocd_compulsion_results', true);
    if (!empty($compulsion_data)) {
        $has_test_data = true;
        echo "<p>✓ Компульсии: есть данные</p>";
    }
    
    if (!$has_test_data) {
        echo "<p>❌ Нет данных тестов</p>";
        continue;
    }
    
    // Генерируем summary
    try {
        $summary = UserDataBuilder::buildUserSummary($user->ID);
        
        echo "<h4>Сгенерированный summary:</h4>";
        echo "<pre>" . json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        
        // Сохраняем в базу
        UserDataBuilder::saveUserSummary($user->ID, $summary);
        echo "<p>✅ Summary сохранен в базу данных</p>";
        
    } catch (Exception $e) {
        echo "<p>❌ Ошибка: " . $e->getMessage() . "</p>";
    }
}

echo "<hr>";
echo "<h2>Проверка данных в таблице ocd_ai_user_ai_input:</h2>";

global $wpdb;
$table = $wpdb->prefix . 'ocd_ai_user_ai_input';
$results = $wpdb->get_results("SELECT * FROM $table ORDER BY user_id");

if (empty($results)) {
    echo "<p>❌ В таблице нет данных</p>";
} else {
    echo "<p>✅ Найдено записей: " . count($results) . "</p>";
    foreach ($results as $row) {
        echo "<h4>Пользователь ID: {$row->user_id}</h4>";
        echo "<p>Обновлено: {$row->last_updated}</p>";
        $summary = json_decode($row->summary_json, true);
        echo "<pre>" . json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    }
} 