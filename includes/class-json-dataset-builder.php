<?php

namespace Ocd\AiConsultant;

class JsonDatasetBuilder
{
    /**
     * Формирует массив структур для fine-tuning
     */
    public static function buildUserDataset($user_id)
    {
        $input = self::getUserInputData($user_id);
        $kb = self::getKnowledgeBaseData();

        return array_merge($kb, $input); // обе части в формате OpenAI messages[]
    }

    /**
     * Возвращает ответы пользователя
     */
    private static function getUserInputData($user_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ocd_ai_user_ai_input';

        $rows = $wpdb->get_results($wpdb->prepare("
SELECT question, answer FROM $table
WHERE user_id = %d
ORDER BY created_at ASC
", $user_id));

        $data = [];
        foreach ($rows as $row) {
            if (trim($row->answer) === '') continue;

            $data[] = [
                'messages' => [
                    ['role' => 'user', 'content' => $row->question],
                    ['role' => 'assistant', 'content' => $row->answer],
                ]
            ];
        }

        return $data;
    }

    /**
     * Возвращает данные из базы знаний
     */
    private static function getKnowledgeBaseData()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ocd_ai_knowledge_base';
        $rows = $wpdb->get_results("SELECT * FROM $table");

        $data = [];
        foreach ($rows as $row) {
// Берём первые две колонки как question/answer
            $cols = (array)$row;
            $question = reset($cols);
            $answer = next($cols);

            if (!$question || !$answer) continue;

            $data[] = [
                'messages' => [
                    ['role' => 'user', 'content' => $question],
                    ['role' => 'assistant', 'content' => $answer],
                ]
            ];
        }

        return $data;
    }
}
