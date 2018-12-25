<?php

/*
Plugin Name: Outeside Postform
Plugin URI:
Description: 外部フォームから投稿を可能にするプラグイン
Version: 1.0
Author: MATSUI KAZUKI
Author URI:
License:
*/

$outside_postform = new OutsidePostform();
class OutsidePostform
{
    private $plugin_path;
    private $post_data;
    private $get_data;
    private $this_post;


    public function __construct()
    {
        $this->plugin_path = WP_PLUGIN_URL.'/'.str_replace(basename(__FILE__), "", plugin_basename(__FILE__));
        add_shortcode('outside_postform', array($this, 'outside_postform_func'));
        add_action('wp_enqueue_scripts', array($this, 'theme_name_scripts'));

        //POSTデータの取得
        $args = array(
            'nonce_outside_postform' => FILTER_SANITIZE_ENCODED
        );
        $this->post_data = filter_input_array(INPUT_POST, $args);
        if ($this->post_data['nonce_outside_postform']) {
            add_action('get_header', array($this, 'run_insert_post'));
        }

        //GETデータ取得
        $args = array(
            'outside_postform_func_status' => FILTER_SANITIZE_ENCODED
        );
        $this->get_data = filter_input_array(INPUT_GET, $args);
        if ($this->get_data['outside_postform_func_status']) {
            add_action('wp_head', array($this, 'run_alert'));
        }
    }


    //ショートコード
    public function outside_postform_func()
    {
        $form = '';
        if (is_user_logged_in()) {
            $nonce = wp_nonce_field('outside_postform', 'nonce_outside_postform', true, false);
            $action_url = $_SERVER['REQUEST_URI'];

            //投稿タイプ
            $select_option = '';
            $args = array(
                'public' => true,
            );
            $post_types = get_post_types($args, 'names');
            unset($post_types['attachment']);
            foreach ($post_types as $v) {
                $select_option .= '<option>' . $v . '</option>';
            }
            unset($v);

            //カテゴリー、タグ
            $cats_li = $this->get_post_category_terms('category');
            $tags_li = $this->get_post_category_terms('post_tag');

            //formタグ
            $form = <<< EOT
            <form id="outside_postform_func" action="{$action_url}" method="POST">
                {$nonce}
                <input type="number" name="ID" value="" placeholder="ID"><span class="sub">※修正の場合はIDを入力する</span>
                <input type="text" name="post_title" value="" placeholder="ここにタイトルを入力">
                <input type="text" name="post_name" value="" placeholder="スラッグ">

                <span class="heading">投稿タイプ</span>
                <select name="post_type">{$select_option}</select>

                <span class="heading">カテゴリー</span>
                {$cats_li}

                <span class="heading">タグ</span>
                {$tags_li}

                <textarea name="post_content"></textarea>
                <button class="button button-large button-primary" type="submit">送信する</button>
            </form>
EOT;
        }
        return $form;
    }


    //termsのリスト取得
    private function get_post_category_terms($taxonomies)
    {
        $checkbox_li = '<ul class="terms_li">';
        $args = array(
            'hide_empty' => false,
        );
        $cats = get_terms($taxonomies, $args);
        foreach ($cats as $v) {
            $checkbox_li .= <<< EOT
                <li>
                    <label><input type="checkbox" name="{$taxonomies}[]" value="{$v->term_id}">{$v->name}</label>
                </li>
EOT;
        }
        unset($v);
        $checkbox_li .= '</ul>';
        return $checkbox_li;
    }


    //外部ファイル読み込み
    public function theme_name_scripts()
    {
        wp_enqueue_style('style-name', $this->plugin_path . 'assets/css/style.css');
    }


    //インサートの実行
    public function run_insert_post()
    {
        $this->insert_post_tag();
        if (is_user_logged_in()) {
            $nonce = $_REQUEST['nonce_outside_postform'];
            if (wp_verify_nonce($nonce, 'outside_postform')) {
                $my_post = array(
                    'post_status' => 'publish',
                    'post_title' => $_POST['post_title'],
                    'post_name' => $_POST['post_name'],
                    'post_type' => $_POST['post_type'],
                    'post_content' => $_POST['post_content'],
                    'post_category' => $_POST['category'],
                    'tags_input' => $this->insert_post_tag()
                );

                //IDがある場合投稿の編集
                if (isset($_POST['ID']) && get_post($_POST['ID'])) {
                    $my_post['ID'] = $_POST['ID'];
                }
                $insert_status = wp_insert_post($my_post);
                if ($insert_status) {
                    $mesg = '投稿完了';
                } else {
                    $mesg = '投稿失敗';
                }

            } else {
                $mesg = '不正な投稿';
            }

            global $post;
            $this->this_post = $post;
            $url = add_query_arg(array('outside_postform_func_status' => $mesg), get_the_permalink($this->this_post->ID));
            wp_redirect($url);
            exit;
        }
    }


    //タグの配列を文字列に変更
    private function insert_post_tag() {
        $tag_array = array();
        if(is_array($_POST['post_tag'])){
            foreach($_POST['post_tag'] as $v) {
                $tag_data = get_tag($v);
                $tag_array[] = $tag_data->name;
            }
            unset($v);
        }
        return implode(",", $tag_array);
    }


    //アラート後にページの遷移
    public function run_alert()
    {
        $link = get_the_permalink($this->this_post->ID);
        $insert_status = urldecode($this->get_data['outside_postform_func_status']);
        print <<< EOT
        <script>
            alert("{$insert_status}");
            location.href = "{$link}";
        </script>
EOT;
    }
}