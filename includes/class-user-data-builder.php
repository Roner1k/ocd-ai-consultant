<?php

namespace Ocd\AiConsultant;

defined('ABSPATH') || exit;

class UserDataBuilder
{
    /**
     * Rebuilds the AI input table for all users
     */
    public static function rebuildAll()
    {
        $users = get_users(['fields' => ['ID', 'user_email']]);

        foreach ($users as $user) {
            self::rebuildForUser($user->ID, $user->user_email);
        }
    }

    /**
     * Rebuilds AI input entries for a single user
     */
    public static function rebuildForUser($user_id, $email)
    {
        global $wpdb;

        $forms = \GFAPI::get_forms();
        foreach ($forms as $form) {
            $entries = \GFAPI::get_entries($form['id'], [
                'field_filters' => [
                    ['key' => 'created_by', 'value' => $user_id]
                ]
            ]);

            foreach ($entries as $entry) {
                foreach ($form['fields'] as $field) {
                    $field_id = $field->id;
                    $question = $field->label;
                    $answer = rgar($entry, (string)$field_id);

                    if ($answer !== null && $answer !== '') {
                        self::upsertInput([
                            'user_id' => $user_id,
                            'email' => $email,
                            'question' => $question,
                            'answer' => maybe_serialize($answer),
                            'source_form' => $form['title'],
                            'source_entry' => $entry['id'],
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Insert or update AI input row
     */
    private static function upsertInput($data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ocd_ai_user_ai_input';

        $hash = md5($data['user_id'] . $data['source_form'] . $data['question']);

        $existing = $wpdb->get_var($wpdb->prepare("
        SELECT id FROM $table WHERE input_hash = %s
    ", $hash));

        if ($existing) {
            $wpdb->update($table, [
                'answer' => $data['answer'],
                'source_entry' => $data['source_entry'],
                'created_at' => current_time('mysql'),
            ], ['id' => $existing]);
        } else {
            $wpdb->insert($table, [
                'user_id' => $data['user_id'],
                'email' => $data['email'],
                'question' => $data['question'],
                'answer' => $data['answer'],
                'source_form' => $data['source_form'],
                'source_entry' => $data['source_entry'],
                'input_hash' => $hash,
                'created_at' => current_time('mysql'),
            ]);
        }
    }

    /**
     * Rebuilds AI input entries for a single user
     */
    //
    public static function syncUserData($user_id, $email = null)
    {
        global $wpdb;

        if (!$email) {
            $user = get_userdata($user_id);
            if (!$user) return;
            $email = $user->user_email;
        }

        $forms = \GFAPI::get_forms();

        foreach ($forms as $form) {
            $entries = \GFAPI::get_entries($form['id'], [
                'field_filters' => [
                    ['key' => 'created_by', 'value' => $user_id]
                ]
            ]);

            foreach ($entries as $entry) {
                foreach ($form['fields'] as $field) {
                    if (!isset($field->label)) continue;

                    $field_id = $field->id;
                    $question = $field->label;
                    $answer = rgar($entry, (string) $field_id);

                    if ($answer === null || $answer === '') {
                        continue;
                    }

                    self::upsertInput([
                        'user_id' => $user_id,
                        'email' => $email,
                        'question' => $question,
                        'answer' => maybe_serialize($answer),
                        'source_form' => $form['title'],
                        'source_entry' => $entry['id'],
                    ]);
                }
            }
        }
    }


}
