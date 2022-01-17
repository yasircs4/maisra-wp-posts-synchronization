<?php


class MSC_helper
{
    static $XOOSH_ID = 31;
    static $SYNC_POST_TYPES = ['audio', 'books', 'post'];
    static $NOT_SYNC_POST_STATUES = ['pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash'];
    static $REST_BASE_POST_TYPES = ['audio' => 'audios-api', 'books' => 'books-api', 'post' => 'posts'];

    static $SYNC_POST_TAXONOMIES = ['audio_category', 'audio_tags', 'books_category', 'books_tags', 'category', 'post_tag'];
    static $SYNC_POST_TAXONOMIES_API_BASE = [
        'audio_category' => 'audio_category-api',
        'audio_tags' => 'audio_tags-api',
        'books_category' => 'books_category-api',
        'books_tags' => 'books_tags-api',
        'category' => 'categories',
        'post_tag' => 'tags'
    ];

    static $XOOSH_POSTS_SYNC_COUNTER_SUCCESS = 'xoosh_posts_sync_counter_success';
    static $XOOSH_POSTS_SYNC_COUNTER_FAILED = 'xoosh_posts_sync_counter_failed';
    static $META_POST_KEY_SYNC_XOOSH_POST_ID = 'sync_xoosh_post_id';

    static $XOOSH_TERMS_SYNC_COUNTER_SUCCESS = 'xoosh_terms_sync_counter_success';
    static $XOOSH_TERMS_SYNC_COUNTER_FAILED = 'xoosh_terms_sync_counter_failed';
    static $META_TERM_KEY_SYNC_XOOSH_TERM_ID = 'sync_xoosh_term_id';


    /**
     * @param false $renew
     * @return false|mixed|string|void
     */
    function get_xoosh_user_bot_token($renew = false)
    {
        $_xoosh_user_bot_token = '';
        $jwt_token = get_option('sync_token_xoosh');
        $xoosh_user_bot_token = get_option('xoosh_user_bot_token');
        $options = get_option("maisra-options-theme");


        if (!$renew && !empty($xoosh_user_bot_token)) return $xoosh_user_bot_token;


        $site = !empty($options['xoosh_url']) ? $options['xoosh_url'] : 'https://xoosh.com';
        $URL = "$site/wp-json/jwt-auth/v1/token";

        $data = [
            'username' => $options['xoosh_username'],
            'password' => $options['xoosh_password'],
        ];
        $data_string = json_encode($data);

        $crl_process = curl_init();

        $header = array();
        $header[] = 'Content-type: application/json';
        curl_setopt($crl_process, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($crl_process, CURLOPT_URL, $URL);
        curl_setopt($crl_process, CURLOPT_HTTPHEADER, $header);
        curl_setopt($crl_process, CURLOPT_POST, true);
        curl_setopt($crl_process, CURLOPT_POSTFIELDS, $data_string);
        // allow us to use the returned data from the request
        curl_setopt($crl_process, CURLOPT_RETURNTRANSFER, true);
        $rest_response = curl_exec($crl_process);

        if ($rest_response === false) {
            // throw new Exception('Curl error: ' . curl_error($crl));
//            print_r('Curl error: ' . curl_error($crl_process));
        } else {
            $rest_array = json_decode($rest_response);
            update_option('xoosh_user_bot_token', $rest_array->token);
            $_xoosh_user_bot_token = $rest_array->token;
        }

        curl_close($crl_process);

        return $_xoosh_user_bot_token;
    }

    /**
     *
     * @param $post_id
     * @param $tax_name
     * @return array
     */
    function get_post_terms_ids($post_id, $tax_name): array
    {
        $ids = [];
        $terms = wp_get_post_terms($post_id, $tax_name);
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                array_push($ids, $term->term_id);
            }
        }
        return $ids;
    }

    /**
     * @param $key
     * @param $post_id
     * @param false $old_id
     */
    function xoosh_posts_sync_counter($key, $post_id, $old_id = false)
    {
        $counter = get_option($key);
        if ($counter) {
            if ($old_id) {
                array_push($counter, ['xoosh_id' => $post_id, 'manhaj_post_id' => $old_id]);
            } else {
                array_push($counter, $post_id);
            }
        } else {
            $counter = [$post_id];
        }
        update_option($key, $counter);
    }

    function is_xoosh($post_id): bool
    {
        return in_array($this::$XOOSH_ID, $this->get_post_terms_ids($post_id, 'sheikh'));
    }


    /**
     * @param $parent_id
     * @return int|mixed
     */
    function get_xoosh_parent_term_id($parent_id): int
    {
        $xoosh_parent_term_id = 0;
        if ($parent_id) {
            $xoosh_parent_term_id = get_term_meta($parent_id, MSC_Sync_helper::$META_TERM_KEY_SYNC_XOOSH_TERM_ID, true);
        }

        return $xoosh_parent_term_id ? $xoosh_parent_term_id : $parent_id;
    }
}