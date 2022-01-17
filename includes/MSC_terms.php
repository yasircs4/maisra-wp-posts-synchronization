<?php
/**
 * @package maisra-sync-content
 */


class MSC_terms
{
    public function __construct()
    {
        add_action('saved_term', [$this, 'sync_create_term'], 10, 4);
        add_action('pre_delete_term', [$this, 'sync_delete_term'], 10, 2);
//        add_action('delete_term', [$this, 'sync_delete_term'], 10, 4);
    }

    /**
     * @param int $term_id Term ID.
     * @param int $tt_id Term taxonomy ID.
     * @param string $taxonomy Taxonomy slug.
     * @param bool $update Whether this is an existing term being updated.
     *
     */
    function sync_create_term($term_id, $tt_id, $taxonomy, $is_update)
    {
        $helper = new MSC_helper();

        // If this is targeted taxonomies
        if (!in_array($taxonomy, MSC_helper::$SYNC_POST_TAXONOMIES)) return;

        $this->term_request($term_id, $taxonomy, $is_update);

        die();
    }

    /////////////////////////////////////////////////////
    ///
    ///    Make Http request to update or create term,
    ///    -  first assume it's update if not found id, then create new post in xoosh
    ///    -  store result
    ///
    /// /////////////////////////////////////////////////
    private function term_request($term_id, $taxonomy, $is_update)
    {
        $helper = new MSC_helper();
        $options = get_option("maisra-options-theme");
        $xoosh_user_bot_token = $helper->get_xoosh_user_bot_token();

        $term = get_term($term_id, $taxonomy);
        $term_metas = get_term_meta($term_id);


        $sync_xoosh_term_id = get_term_meta($term_id, MSC_helper::$META_TERM_KEY_SYNC_XOOSH_TERM_ID, true);
        $get_term_id_case_update = $sync_xoosh_term_id ? $sync_xoosh_term_id : $term_id;
        $is_update_part = $is_update ? '/' . $get_term_id_case_update : '';


        $REST_BASE = MSC_helper::$SYNC_POST_TAXONOMIES_API_BASE[$taxonomy];
        $site = !empty($options['xoosh_url']) ? $options['xoosh_url'] : 'xoosh.com';
        $URL = "$site/wp-json/wp/v2/$REST_BASE$is_update_part";
        $parent = $helper->get_xoosh_parent_term_id($term->parent);

        $term_data = [
            'name' => $term->name,
            'description' => $term->description,
            'meta' => [
                'sync_manhaj_term_id' => $term_id
            ]
        ];

        if ($is_update) $term_data['id'] = $term_id;
        if ($parent) $term_data['parent'] = $parent;

        $data_string = json_encode($term_data);
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
            $helper->xoosh_posts_sync_counter(MSC_helper::$XOOSH_POSTS_SYNC_COUNTER_FAILED, $term_id);
            // throw new Exception('Curl error: ' . curl_error($crl));
            // print_r('Curl error: ' . curl_error($crl_process));
        } else {
            $rest_array = (array)json_decode($rest);
            // if success store $term_id of new post in other website
            if (isset($rest_array['id'])) {
                $xoosh_term_id = $rest_array['id'];
                // update dashboard status
                $helper->xoosh_posts_sync_counter(MSC_helper::$XOOSH_TERMS_SYNC_COUNTER_SUCCESS, $xoosh_term_id);
                // store new post_id of xoosh to manhaj post
                update_term_meta($term_id, MSC_helper::$META_TERM_KEY_SYNC_XOOSH_TERM_ID, $xoosh_term_id);

                // do make request to save meta-boxes
                if ($term_metas) {
                    foreach ($term_metas as $meta_key => $meta_value) {
                        if (empty($meta_value)) continue;
                        $this->request_term_meta($term_id, $taxonomy, $is_update, $meta_key, $meta_value);
                    }
                }

            } else {
                // if FAILED store $term_id of new post in this website
                $helper->xoosh_posts_sync_counter(MSC_helper::$XOOSH_TERMS_SYNC_COUNTER_FAILED, $term_id, false);
            }

            // in case of jwt_auth_invalid_token do it again with new token
            if (isset($rest_array['code']) && $rest_array['code'] === 'jwt_auth_invalid_token') {
                // get_new_token
                $new_xoosh_user_bot_token = $helper->get_xoosh_user_bot_token($renew = true);
                // do it again with new token
                $this->term_request($term_id, $taxonomy, $is_update);
            }
            if (isset($rest_array['code']) && $rest_array['code'] === 'rest_post_invalid_id') {
                $this->term_request($term_id, $taxonomy, $is_update);
            }
        }

        curl_close($crl_process);
    }

    /**
     * @param int $term_id Term ID.
     * @param string $taxonomy Taxonomy slug.
     * @param bool $update Whether this is an existing term being updated.
     * @param $meta_key
     * @param $meta_value
     */
    private function request_term_meta($term_id, $taxonomy, $is_update, $meta_key, $meta_value)
    {

    }

    /**
     * @param int $term_id Term ID.
     * @param string $taxonomy Taxonomy slug.
     *
     */
    function sync_delete_term($term_id, $taxonomy)
    {
        $deleted_term = get_term($term_id, $taxonomy);
        $helper = new MSC_helper();

        // If this is targeted taxonomies
        if (!in_array($taxonomy, MSC_helper::$SYNC_POST_TAXONOMIES)) return;

        $this->request_term_delete($term_id, $taxonomy, $deleted_term);
    }

    private function request_term_delete($term_id, $taxonomy, $deleted_term)
    {
        $helper = new MSC_helper();
        $options = get_option("maisra-options-theme");
        $xoosh_user_bot_token = $helper->get_xoosh_user_bot_token();

        $term = $deleted_term;
        $term_metas = get_term_meta($term_id);


        $sync_xoosh_term_id = get_term_meta($term_id, MSC_helper::$META_TERM_KEY_SYNC_XOOSH_TERM_ID, true);
        $get_term_id = $sync_xoosh_term_id ? $sync_xoosh_term_id : $term_id;
        $is_update_part = '/' . $get_term_id;


        $REST_BASE = MSC_helper::$SYNC_POST_TAXONOMIES_API_BASE[$taxonomy];
        $site = !empty($options['xoosh_url']) ? $options['xoosh_url'] : 'xoosh.com';
        $URL = "$site/wp-json/wp/v2/$REST_BASE$is_update_part";

        $term_data = [
            'id' => $get_term_id,
            'force' => true,
        ];

        $data_string = json_encode($term_data);
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
            $helper->xoosh_posts_sync_counter(MSC_helper::$XOOSH_POSTS_SYNC_COUNTER_FAILED, $term_id);
            // throw new Exception('Curl error: ' . curl_error($crl));
            // print_r('Curl error: ' . curl_error($crl_process));
        } else {
            $rest_array = (array)json_decode($rest);

            // in case of jwt_auth_invalid_token do it again with new token
            if (isset($rest_array['code']) && $rest_array['code'] === 'jwt_auth_invalid_token') {
                // get_new_token
                $new_xoosh_user_bot_token = $helper->get_xoosh_user_bot_token($renew = true);
                // do it again with new token
                $this->request_term_delete($term_id, $taxonomy, $deleted_term);
            }
            if (isset($rest_array['code']) && $rest_array['code'] === 'rest_post_invalid_id') {
                $this->request_term_delete($term_id, $taxonomy, $deleted_term);
            }
        }

        curl_close($crl_process);
    }
}

new MSC_terms();