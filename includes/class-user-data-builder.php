<?php

namespace Ocd\AiConsultant;

defined('ABSPATH') || exit;

class UserDataBuilder
{
    /**
     * Rebuilds the AI input table for all users.
     */
    public static function rebuildAll()
    {
        $users = get_users(['fields' => ['ID', 'user_email']]);

        foreach ($users as $user) {
            self::rebuildForUser($user->ID, $user->user_email);
        }
    }

    /**
     * Rebuilds AI input entries for a single user.
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
                    if (!isset($field->label)) continue;

                    $field_id = $field->id;
                    $question = $field->label;
                    $raw_answer = rgar($entry, (string)$field_id);

                    if ($raw_answer === null || $raw_answer === '') continue;

                    // Convert 1/0 to Yes/No
                    $answer = ($raw_answer === '1') ? 'Yes' :
                        (($raw_answer === '0') ? 'No' : $raw_answer);

                    // Handle gquiz answers by mapping ID to choice label
                    if (strpos($answer, 'gquiz') === 0 && !empty($field->choices)) {
                        foreach ($field->choices as $choice) {
                            if ($choice['value'] === $answer) {
                                $answer = $choice['text'];
                                break;
                            }
                        }
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


    /**
     * Inserts or updates a user's Q&A input entry.
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

<<<<<<< Updated upstream
=======
    /**
     * Rebuilds AI input entries for a single user.
     * Can be used independently (e.g., after entry or subscription).
     */
//    public static function syncUserData($user_id, $email = null)
//    {
//        global $wpdb;
//
//        if (!$email) {
//            $user = get_userdata($user_id);
//            if (!$user) return;
//            $email = $user->user_email;
//        }
//
//        $forms = \GFAPI::get_forms();
//
//        foreach ($forms as $form) {
//            $entries = \GFAPI::get_entries($form['id'], [
//                'field_filters' => [
//                    ['key' => 'created_by', 'value' => $user_id]
//                ]
//            ]);
//
//            foreach ($entries as $entry) {
//                foreach ($form['fields'] as $field) {
//                    if (!isset($field->label)) continue;
//
//                    $field_id = $field->id;
//                    $question = $field->label;
//                    $answer = rgar($entry, (string) $field_id);
//
//                    if ($answer === null || $answer === '') {
//                        continue;
//                    }
//
//                    self::upsertInput([
//                        'user_id'       => $user_id,
//                        'email'         => $email,
//                        'question'      => $question,
//                        'answer'        => self::normalizeAnswer($answer),
//                        'source_form'   => $form['title'],
//                        'source_entry'  => $entry['id'],
//                    ]);
//                }
//            }
//        }
//    }

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
                    $raw_answer = rgar($entry, (string)$field_id);

                    if ($raw_answer === null || $raw_answer === '') {
                        continue;
                    }

                    // Convert 1/0 to Yes/No
                    $answer = ($raw_answer === '1') ? 'Yes' :
                        (($raw_answer === '0') ? 'No' : $raw_answer);

                    // Handle gquiz answers by mapping ID to choice label
                    if (strpos($answer, 'gquiz') === 0 && !empty($field->choices)) {
                        foreach ($field->choices as $choice) {
                            if ($choice['value'] === $answer) {
                                $answer = $choice['text'];
                                break;
                            }
                        }
                    }

                    $answer = self::normalizeAnswer($answer);

                    self::upsertInput([
                        'user_id' => $user_id,
                        'email' => $email,
                        'question' => $question,
                        'answer' => $answer,
                        'source_form' => $form['title'],
                        'source_entry' => $entry['id'],
                    ]);
                }
            }
        }
    }


    /**
     * Normalizes raw answer for consistent AI dataset.
     */
    private static function normalizeAnswer($answer)
    {
        // Boolean values from radio/checkboxes
        if ($answer === '1') return 'Yes';
        if ($answer === '0') return 'No';

        // Try to deserialize if serialized
        if (is_serialized($answer)) {
            $unserialized = maybe_unserialize($answer);
            if (is_scalar($unserialized)) {
                return (string)$unserialized;
            } else {
                return json_encode($unserialized, JSON_UNESCAPED_UNICODE);
            }
        }

        return (string)$answer;
    }
>>>>>>> Stashed changes
}
