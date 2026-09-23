<?php

if (!defined('ABSPATH')) {
    exit;
}

final class BVTN_Plugin
{
    private static $instance = null;

    private const POST_TYPE = 'bvtn_note';
    private const MENU_SLUG = 'belbo-vocal-notebook';

    private const STATUSES = array(
        'idea' => '未検証',
        'testing' => '検証中',
        'promising' => '有力',
        'confirmed' => '定着',
        'review' => '見直し',
    );

    private const CATEGORIES = array(
        'voice' => '発声',
        'pitch' => '音程',
        'rhythm' => 'リズム・音価',
        'expression' => '表現',
        'band' => 'バンドでの抜け',
        'other' => 'その他',
    );

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('init', array($this, 'register_post_type'));
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_post_bvtn_save_note', array($this, 'save_note'));
        add_action('admin_post_bvtn_delete_note', array($this, 'delete_note'));
        add_action('admin_post_bvtn_export', array($this, 'export_notes'));
    }

    public static function activate(): void
    {
        self::register_post_type_static();

        $seeded = get_option('bvtn_seeded_version', '');
        if ($seeded !== '') {
            return;
        }

        $note_id = wp_insert_post(array(
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => 'アタックと音価でバンドに埋もれない声を作る',
            'post_content' => '',
        ));

        if (!is_wp_error($note_id)) {
            update_post_meta($note_id, '_bvtn_observed_on', wp_date('Y-m-d'));
            update_post_meta($note_id, '_bvtn_category', 'band');
            update_post_meta($note_id, '_bvtn_status', 'idea');
            update_post_meta($note_id, '_bvtn_tags', 'テヌート, アタック, 音価, バンド');
            update_post_meta($note_id, '_bvtn_insight', "テヌートでしっかり音価を保ち、伸ばす。\n入りの頭の音（アタック）は、少し高めの音を入れる。\n\nドラムサウンドとして捉えたとき、頭に高めの音が入ることでアタックが明確になり、音価があることでボーカルの音が残る。");
            update_post_meta($note_id, '_bvtn_hypothesis', '明確なアタックと十分な音価を組み合わせることで、バンドサウンドに埋もれにくい声を作れるのではないか。');
            update_post_meta($note_id, '_bvtn_codex_opinion', "筋のよい仮説。ボーカルが埋もれないためには、音量だけでなく「音の始まりが認識できること」と「始まった音が途中で痩せずに存在し続けること」が重要。\n\n「少し高め」は、実際のピッチを外すのではなく、明るい倍音や子音によってアタックを作っている可能性もある。テヌートでは母音の響き、息の流れ、音の終わりまでの芯を観察したい。");
            update_post_meta($note_id, '_bvtn_experiment', "1. 音程をわずかに上から入れる\n2. 音程は正確なまま、明るい倍音や子音だけを強くする\n3. それぞれをバンド音源に重ね、抜け方・音程・喉の力みを比較する");
            update_post_meta($note_id, '_bvtn_result', '');
        }

        update_option('bvtn_seeded_version', BVTN_VERSION, false);
    }

    public function register_post_type(): void
    {
        self::register_post_type_static();
    }

    private static function register_post_type_static(): void
    {
        register_post_type(self::POST_TYPE, array(
            'labels' => array('name' => 'ボーカル研究ノート'),
            'public' => false,
            'show_ui' => false,
            'supports' => array('title', 'author', 'revisions'),
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ));
    }

    public function register_menu(): void
    {
        add_menu_page(
            'べるぼ ボーカル研究ノート',
            '歌の研究ノート',
            'edit_posts',
            self::MENU_SLUG,
            array($this, 'render_page'),
            'dashicons-microphone',
            26
        );
    }

    public function enqueue_assets(string $hook): void
    {
        if ($hook !== 'toplevel_page_' . self::MENU_SLUG) {
            return;
        }
        wp_enqueue_style('bvtn-admin', BVTN_URL . 'assets/admin.css', array(), BVTN_VERSION);
    }

    public function render_page(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('このページを表示する権限がありません。', 'belbo-vocal-training-notebook'));
        }

        $edit_id = isset($_GET['edit']) ? absint($_GET['edit']) : 0;
        $notice = isset($_GET['bvtn_notice']) ? sanitize_key(wp_unslash($_GET['bvtn_notice'])) : '';
        $note = $edit_id ? get_post($edit_id) : null;
        if ($note && ($note->post_type !== self::POST_TYPE || !current_user_can('edit_post', $edit_id))) {
            $note = null;
            $edit_id = 0;
        }

        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $status_filter = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
        $category_filter = isset($_GET['category']) ? sanitize_key(wp_unslash($_GET['category'])) : '';
        $notes = $this->query_notes($search, $status_filter, $category_filter);
        $counts = $this->get_status_counts();

        include BVTN_DIR . 'views/admin-page.php';
    }

    private function query_notes(string $search, string $status, string $category): WP_Query
    {
        $meta_query = array('relation' => 'AND');
        if (isset(self::STATUSES[$status])) {
            $meta_query[] = array('key' => '_bvtn_status', 'value' => $status);
        }
        if (isset(self::CATEGORIES[$category])) {
            $meta_query[] = array('key' => '_bvtn_category', 'value' => $category);
        }

        $args = array(
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 100,
            'orderby' => 'meta_value',
            'meta_key' => '_bvtn_observed_on',
            'order' => 'DESC',
            's' => $search,
        );
        if (count($meta_query) > 1) {
            $args['meta_query'] = $meta_query;
        }
        return new WP_Query($args);
    }

    private function get_status_counts(): array
    {
        $counts = array_fill_keys(array_keys(self::STATUSES), 0);
        $ids = get_posts(array(
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ));
        foreach ($ids as $id) {
            $status = get_post_meta($id, '_bvtn_status', true);
            if (isset($counts[$status])) {
                ++$counts[$status];
            }
        }
        return $counts;
    }

    public function save_note(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die('権限がありません。');
        }
        check_admin_referer('bvtn_save_note');

        $note_id = isset($_POST['note_id']) ? absint($_POST['note_id']) : 0;
        if ($note_id && !current_user_can('edit_post', $note_id)) {
            wp_die('このノートを編集できません。');
        }

        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        if ($title === '') {
            $title = '無題の気づき';
        }

        $post_data = array(
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $title,
        );
        if ($note_id) {
            $post_data['ID'] = $note_id;
            $saved_id = wp_update_post($post_data, true);
        } else {
            $post_data['post_author'] = get_current_user_id();
            $saved_id = wp_insert_post($post_data, true);
        }

        if (is_wp_error($saved_id)) {
            wp_die(esc_html($saved_id->get_error_message()));
        }

        $observed_on = isset($_POST['observed_on']) ? sanitize_text_field(wp_unslash($_POST['observed_on'])) : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $observed_on)) {
            $observed_on = wp_date('Y-m-d');
        }
        $category = isset($_POST['category']) ? sanitize_key(wp_unslash($_POST['category'])) : 'other';
        $status = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : 'idea';

        update_post_meta($saved_id, '_bvtn_observed_on', $observed_on);
        update_post_meta($saved_id, '_bvtn_category', isset(self::CATEGORIES[$category]) ? $category : 'other');
        update_post_meta($saved_id, '_bvtn_status', isset(self::STATUSES[$status]) ? $status : 'idea');
        update_post_meta($saved_id, '_bvtn_tags', $this->clean_line('tags'));
        update_post_meta($saved_id, '_bvtn_insight', $this->clean_textarea('insight'));
        update_post_meta($saved_id, '_bvtn_hypothesis', $this->clean_textarea('hypothesis'));
        update_post_meta($saved_id, '_bvtn_codex_opinion', $this->clean_textarea('codex_opinion'));
        update_post_meta($saved_id, '_bvtn_experiment', $this->clean_textarea('experiment'));
        update_post_meta($saved_id, '_bvtn_result', $this->clean_textarea('result'));

        $this->redirect('saved', array('edit' => $saved_id));
    }

    public function delete_note(): void
    {
        $note_id = isset($_GET['note_id']) ? absint($_GET['note_id']) : 0;
        if (!$note_id || !current_user_can('delete_post', $note_id)) {
            wp_die('このノートを削除できません。');
        }
        check_admin_referer('bvtn_delete_' . $note_id);
        wp_trash_post($note_id);
        $this->redirect('deleted');
    }

    public function export_notes(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die('権限がありません。');
        }
        check_admin_referer('bvtn_export');

        $ids = get_posts(array(
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'ASC',
            'fields' => 'ids',
        ));
        $items = array();
        foreach ($ids as $id) {
            $items[] = $this->note_to_array($id);
        }

        $payload = array(
            'format' => 'belbo-vocal-training-notebook',
            'version' => 1,
            'exported_at' => current_time('c'),
            'notes' => $items,
        );
        $filename = 'belbo-vocal-notes-' . wp_date('Ymd-His') . '.json';
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    private function note_to_array(int $id): array
    {
        return array(
            'id' => $id,
            'title' => get_the_title($id),
            'observed_on' => get_post_meta($id, '_bvtn_observed_on', true),
            'category' => get_post_meta($id, '_bvtn_category', true),
            'status' => get_post_meta($id, '_bvtn_status', true),
            'tags' => get_post_meta($id, '_bvtn_tags', true),
            'insight' => get_post_meta($id, '_bvtn_insight', true),
            'hypothesis' => get_post_meta($id, '_bvtn_hypothesis', true),
            'codex_opinion' => get_post_meta($id, '_bvtn_codex_opinion', true),
            'experiment' => get_post_meta($id, '_bvtn_experiment', true),
            'result' => get_post_meta($id, '_bvtn_result', true),
            'updated_at' => get_post_modified_time('c', false, $id),
        );
    }

    private function clean_line(string $key): string
    {
        return isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
    }

    private function clean_textarea(string $key): string
    {
        return isset($_POST[$key]) ? sanitize_textarea_field(wp_unslash($_POST[$key])) : '';
    }

    private function redirect(string $notice, array $extra = array()): void
    {
        $args = array_merge(array('page' => self::MENU_SLUG, 'bvtn_notice' => $notice), $extra);
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    public static function statuses(): array
    {
        return self::STATUSES;
    }

    public static function categories(): array
    {
        return self::CATEGORIES;
    }
}
