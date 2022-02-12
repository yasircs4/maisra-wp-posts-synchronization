<?php
/**
 * @package maisra-sync-content
 */


class MSC_posts
{
    public function __construct()
    {
        add_action('wp_insert_post', [$this, 'sync_update_posts'], 10, 3);
        add_action('wp_trash_post', [$this, 'sync_trashed_post']);
    }

    function sync_trashed_post($post_id)
    {
        $helper = new MSC_helper();
        $post = get_post($post_id);
        $post_type = $post->post_type;
        $post_status = $post->post_status;
        $is_xoosh = $helper->is_xoosh($post_id);
        // If this is a revision
        if (wp_is_post_revision($post_id)) return;
        // and if tax=Sheikh=xoosh_id
        if (!$is_xoosh) return;
        // is post_type is ['audio', 'books', 'post']
        if (!in_array($post_type, MSC_helper::$SYNC_POST_TYPES)) return;
        // if post_status not in the array of $NOT_SYNC_POST_STATUES
        if (in_array($post_status, MSC_helper::$NOT_SYNC_POST_STATUES)) return;

        $this->request_sync_trash($post_id, $post);

    }

    function sync_update_posts($post_id, $post, $is_update)
    {
        $helper = new MSC_helper();
        $post_type = $post->post_type;
        $post_status = $post->post_status;
        $is_xoosh = $helper->is_xoosh($post_id);

        // If this is a revision
        if (wp_is_post_revision($post_id)) return;
        // and if tax=Sheikh=xoosh_id
        if (!$is_xoosh) return;
        // is post_type is ['audio', 'books', 'post']
        if (!in_array($post_type, MSC_helper::$SYNC_POST_TYPES)) return;
        // if post_status not in the array of $NOT_SYNC_POST_STATUES
        if (in_array($post_status, MSC_helper::$NOT_SYNC_POST_STATUES)) return;

        $this->post_request($post, true);
    }

    /////////////////////////////////////////////////////
    ///
    ///    make Http request to update or create post in xoosh,
    ///    -  first assume it's update if not found id, then create new post in xoosh
    ///    -  store result
    ///
    /// /////////////////////////////////////////////////
    private function post_request($post, $is_update)
    {
        $helper = new MSC_helper();

        $options = get_option("maisra-options-theme");
        $xoosh_user_bot_token = $helper->get_xoosh_user_bot_token();

        $post_id = $post->ID;
        $post_metas = get_post_meta($post->ID);
        $post_type = $post->post_type;
        $sheikhs_ids = $helper->get_post_terms_ids($post_id, 'sheikh');
        $category_ids = $helper->get_post_terms_ids($post_id, 'category');
        $audio_category_ids = $helper->get_post_terms_ids($post_id, 'audio_category');
        $books_category_ids = $helper->get_post_terms_ids($post_id, 'books_category');
        $_thumbnail_url = isset($post_metas['_thumbnail_id'][0]) ? wp_get_attachment_image_url($post_metas['_thumbnail_id'][0], 'large') : '';
        $_thumbnail_url = str_replace('http', 'https', $_thumbnail_url);

        $sync_xoosh_post_id = get_post_meta($post_id, MSC_helper::$META_POST_KEY_SYNC_XOOSH_POST_ID, true);
        $get_post_id_case_update = $sync_xoosh_post_id ? $sync_xoosh_post_id : $post_id;
        $is_update_part = $is_update ? '/' . $get_post_id_case_update : '';

        $REST_BASE = MSC_helper::$REST_BASE_POST_TYPES[$post_type];
        $site = !empty($options['xoosh_url']) ? $options['xoosh_url'] : 'xoosh.com';
        $URL = "$site/wp-json/wp/v2/$REST_BASE$is_update_part";

        $post_metas = [
            'post_book_text_pdf' => isset($post_metas['post_book_text_pdf'][0]) ? $post_metas['post_book_text_pdf'][0] : '',
            'audio_text' => isset($post_metas['audio_text'][0]) ? $post_metas['audio_text'][0] : '',
            'audio_file_mp3' => isset($post_metas['audio_file_mp3'][0]) ? $post_metas['audio_file_mp3'][0] : '',
            'book_text_doc' => isset($post_metas['book_text_doc'][0]) ? $post_metas['book_text_doc'][0] : '',
            'book_text_pdf' => isset($post_metas['book_text_pdf'][0]) ? $post_metas['book_text_pdf'][0] : '',
            'sync_thumbnail_id' => isset($post_metas['_thumbnail_id'][0]) ? $post_metas['_thumbnail_id'][0] : '',
            'sync_thumbnail_url' => $_thumbnail_url,

        ];
        $post_data = [
            'title' => $post->post_title,
            'status' => $post->post_status,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'meta' => [
            ],
            'categories' => $category_ids,
            'sheikhs' => $sheikhs_ids,
            'audio_category-api' => $audio_category_ids,
            'books_category-api' => $books_category_ids,

        ];
        $data_string = json_encode($post_data);
        $crl_process = curl_init();
        $header = [];
        $header[] = 'Content-type: application/json';
        $header[] = 'Authorization: Bearer ' . $xoosh_user_bot_token;
        curl_setopt($crl_process, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($crl_process, CURLOPT_URL, $URL);
        curl_setopt($crl_process, CURLOPT_HTTPHEADER, $header);
        curl_setopt($crl_process, CURLOPT_POST, true);
        curl_setopt($crl_process, CURLOPT_POSTFIELDS, $data_string);
        // allow us to use the returned data from the request
        curl_setopt($crl_process, CURLOPT_RETURNTRANSFER, true);
        $rest = curl_exec($crl_process);

        if ($rest === false) {
            $helper->xoosh_posts_sync_counter(MSC_helper::$XOOSH_POSTS_SYNC_COUNTER_FAILED, $post_id);
            // throw new Exception('Curl error: ' . curl_error($crl));
            // print_r('Curl error: ' . curl_error($crl_process));
        } else {
            $rest_array = (array)json_decode($rest);
            // if success store $post_id of new post in other website
            if (isset($rest_array['id'])) {
                $xoosh_post_id = $rest_array['id'];
                // update dashboard status
                $helper->xoosh_posts_sync_counter(MSC_helper::$XOOSH_POSTS_SYNC_COUNTER_SUCCESS, $xoosh_post_id);
                // store new post_id of xoosh to manhaj post
                update_post_meta($post_id, MSC_helper::$META_POST_KEY_SYNC_XOOSH_POST_ID, $xoosh_post_id);

                // do make request to save meta-boxes
                if ($post_metas) {
                    foreach ($post_metas as $meta_key => $meta_value) {
                        if (empty($meta_value)) continue;
                        $this->request_post_meta($xoosh_post_id, $post_type, $meta_key, $meta_value);
                    }
                }

            } else {
                // if FAILED store $post_id of new post in this website
                $helper->xoosh_posts_sync_counter(MSC_helper::$XOOSH_POSTS_SYNC_COUNTER_FAILED, $post_id, false);
            }

            // in case of jwt_auth_invalid_token do it again with new token
            if (isset($rest_array['code']) && $rest_array['code'] === 'jwt_auth_invalid_token') {
                // get_new_token
                $new_xoosh_user_bot_token = $helper->get_xoosh_user_bot_token($renew = true);
                // do it again with new token
                $this->post_request($post, false);
            }
            if (isset($rest_array['code']) && $rest_array['code'] === 'rest_post_invalid_id') {
                $this->post_request($post, false);
            }
        }

        curl_close($crl_process);
    }



    /////////////////////////////////////////////////////
    ///
    ///    make Http request to trash post in xoosh,
    ///
    /// /////////////////////////////////////////////////
    private function request_sync_trash($post_id, $post)
    {
        $helper = new MSC_helper();

        $options = get_option("maisra-options-theme");
        $xoosh_user_bot_token = $helper->get_xoosh_user_bot_token();

        $post_id = $post->ID;
        $post_metas = get_post_meta($post->ID);
        $post_type = $post->post_type;

        $sync_xoosh_post_id = get_post_meta($post_id, MSC_helper::$META_POST_KEY_SYNC_XOOSH_POST_ID, true);
        $the_id = $sync_xoosh_post_id ? $sync_xoosh_post_id : $post_id;
        $get_post_id_case_delete = '/' . $the_id;

        $REST_BASE = MSC_helper::$REST_BASE_POST_TYPES[$post_type];
        $site = !empty($options['xoosh_url']) ? $options['xoosh_url'] : 'xoosh.com';
        $URL = "$site/wp-json/wp/v2/$REST_BASE$get_post_id_case_delete";


        $post_data = [
            'id' => $the_id,
        ];
        $data_string = json_encode($post_data);

        $crl_process = curl_init();
        $header = [];
        $header[] = 'Content-type: application/json';
        $header[] = 'Authorization: Bearer ' . $xoosh_user_bot_token;
        curl_setopt($crl_process, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($crl_process, CURLOPT_URL, $URL);
        curl_setopt($crl_process, CURLOPT_HTTPHEADER, $header);
        curl_setopt($crl_process, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($crl_process, CURLOPT_POSTFIELDS, $data_string);
        // allow us to use the returned data from the request
        curl_setopt($crl_process, CURLOPT_RETURNTRANSFER, true);
        $rest = curl_exec($crl_process);

        if ($rest === false) {
            $helper->xoosh_posts_sync_counter(MSC_helper::$XOOSH_POSTS_SYNC_COUNTER_FAILED, $post_id);
            // throw new Exception('Curl error: ' . curl_error($crl));
        } else {
            $rest_array = (array)json_decode($rest);
        }

        curl_close($crl_process);
    }

    private function request_post_meta($xoosh_post_id, $post_type, $meta_key, $meta_value)
    {
        $helper = new MSC_helper();

        $options = get_option("maisra-options-theme");
        $xoosh_user_bot_token = $helper->get_xoosh_user_bot_token();
        $site = !empty($options['xoosh_url']) ? $options['xoosh_url'] : 'xoosh.com';
        $URL = "$site/wp-json/cmb2/v1/boxes/$post_type/fields/$meta_key/";

        $data = [
            'object_type' => 'post',
            'object_id' => $xoosh_post_id,
            'value' => $meta_value,
        ];
        $data_string = json_encode($data);

        $crl_process = curl_init();
        $header = [];
        $header[] = 'Content-type: application/json';
        $header[] = 'Authorization: Bearer ' . $xoosh_user_bot_token;
        curl_setopt($crl_process, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($crl_process, CURLOPT_URL, $URL);
        curl_setopt($crl_process, CURLOPT_HTTPHEADER, $header);
        curl_setopt($crl_process, CURLOPT_POST, true);
        curl_setopt($crl_process, CURLOPT_POSTFIELDS, $data_string);
        // allow us to use the returned data from the request
        curl_setopt($crl_process, CURLOPT_RETURNTRANSFER, true);
        $rest = curl_exec($crl_process);

        if ($rest === false) {
            // throw new Exception('Curl error: ' . curl_error($crl));
            // print_r('Curl error: ' . curl_error($crl_process));
        } else {
            $rest_array = (array)json_decode($rest);
        }

        curl_close($crl_process);
    }

}

new MSC_posts();