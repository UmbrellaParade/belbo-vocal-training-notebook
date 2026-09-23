<?php
if (!defined('ABSPATH')) {
    exit;
}

$statuses = BVTN_Plugin::statuses();
$categories = BVTN_Plugin::categories();
$value = static function (string $key, string $fallback = '') use ($note): string {
    if (!$note) {
        return $fallback;
    }
    return (string) get_post_meta($note->ID, '_bvtn_' . $key, true);
};
$current_status = $value('status', 'idea');
$current_category = $value('category', 'other');
?>
<div class="wrap bvtn-wrap">
    <div class="bvtn-hero">
        <div>
            <p class="bvtn-eyebrow">BELBO VOCAL LAB</p>
            <h1>歌の研究ノート</h1>
            <p>気づきを残し、仮説を立て、歌って確かめる。自分の声の取扱説明書を育てていく場所です。</p>
        </div>
        <a class="button button-primary bvtn-new" href="<?php echo esc_url(admin_url('admin.php?page=belbo-vocal-notebook')); ?>">＋ 新しい気づき</a>
    </div>

    <?php if ($notice === 'saved') : ?>
        <div class="notice notice-success is-dismissible"><p>研究ノートを保存しました。</p></div>
    <?php elseif ($notice === 'deleted') : ?>
        <div class="notice notice-success is-dismissible"><p>研究ノートをゴミ箱へ移動しました。</p></div>
    <?php endif; ?>

    <div class="bvtn-stats">
        <?php foreach ($statuses as $key => $label) : ?>
            <a href="<?php echo esc_url(add_query_arg(array('page' => 'belbo-vocal-notebook', 'status' => $key), admin_url('admin.php'))); ?>">
                <strong><?php echo esc_html((string) ($counts[$key] ?? 0)); ?></strong>
                <span><?php echo esc_html($label); ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="bvtn-layout">
        <section class="bvtn-panel bvtn-editor">
            <div class="bvtn-panel-title">
                <div>
                    <span class="dashicons dashicons-edit"></span>
                    <h2><?php echo $note ? '研究ノートを編集' : '今日の気づきを記録'; ?></h2>
                </div>
                <?php if ($note) : ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=belbo-vocal-notebook')); ?>">編集を閉じる</a>
                <?php endif; ?>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="bvtn_save_note">
                <input type="hidden" name="note_id" value="<?php echo esc_attr($note ? (string) $note->ID : '0'); ?>">
                <?php wp_nonce_field('bvtn_save_note'); ?>

                <label class="bvtn-field bvtn-title-field">
                    <span>タイトル</span>
                    <input type="text" name="title" required value="<?php echo esc_attr($note ? $note->post_title : ''); ?>" placeholder="例：アタックと音価でバンドに埋もれない声を作る">
                </label>

                <div class="bvtn-row bvtn-row-three">
                    <label class="bvtn-field">
                        <span>気づいた日</span>
                        <input type="date" name="observed_on" value="<?php echo esc_attr($value('observed_on', wp_date('Y-m-d'))); ?>">
                    </label>
                    <label class="bvtn-field">
                        <span>テーマ</span>
                        <select name="category">
                            <?php foreach ($categories as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($current_category, $key); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="bvtn-field">
                        <span>仮説の状態</span>
                        <select name="status">
                            <?php foreach ($statuses as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($current_status, $key); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <label class="bvtn-field bvtn-accent-user">
                    <span>自分の気づき・観察</span>
                    <textarea name="insight" rows="6" placeholder="感じたことを、まずはそのまま書く"><?php echo esc_textarea($value('insight')); ?></textarea>
                </label>

                <label class="bvtn-field">
                    <span>自分の仮説</span>
                    <textarea name="hypothesis" rows="4" placeholder="なぜそうなるのか？ 何に効くのか？"><?php echo esc_textarea($value('hypothesis')); ?></textarea>
                </label>

                <label class="bvtn-field bvtn-accent-codex">
                    <span>Codexの意見・発展仮説</span>
                    <textarea name="codex_opinion" rows="6" placeholder="別視点、仕組みの推測、注意点など"><?php echo esc_textarea($value('codex_opinion')); ?></textarea>
                </label>

                <label class="bvtn-field bvtn-accent-test">
                    <span>次に試すこと</span>
                    <textarea name="experiment" rows="5" placeholder="比較する歌い方、録音条件、確認ポイントなど"><?php echo esc_textarea($value('experiment')); ?></textarea>
                </label>

                <label class="bvtn-field bvtn-accent-result">
                    <span>試した結果・次の気づき</span>
                    <textarea name="result" rows="5" placeholder="実際に歌って分かったこと。うまくいかなかったことも残す"><?php echo esc_textarea($value('result')); ?></textarea>
                </label>

                <label class="bvtn-field">
                    <span>タグ（カンマ区切り）</span>
                    <input type="text" name="tags" value="<?php echo esc_attr($value('tags')); ?>" placeholder="テヌート, アタック, 音価">
                </label>

                <div class="bvtn-actions">
                    <button type="submit" class="button button-primary button-large">保存する</button>
                    <?php if ($note) :
                        $delete_url = wp_nonce_url(
                            add_query_arg(array('action' => 'bvtn_delete_note', 'note_id' => $note->ID), admin_url('admin-post.php')),
                            'bvtn_delete_' . $note->ID
                        );
                    ?>
                        <a class="button button-link-delete" href="<?php echo esc_url($delete_url); ?>" onclick="return confirm('このノートをゴミ箱へ移動しますか？');">削除</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <aside class="bvtn-panel bvtn-library">
            <div class="bvtn-panel-title">
                <div><span class="dashicons dashicons-book-alt"></span><h2>これまでの研究</h2></div>
            </div>

            <form class="bvtn-filters" method="get">
                <input type="hidden" name="page" value="belbo-vocal-notebook">
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="キーワードで探す">
                <div class="bvtn-row">
                    <select name="category">
                        <option value="">すべてのテーマ</option>
                        <?php foreach ($categories as $key => $label) : ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($category_filter, $key); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status">
                        <option value="">すべての状態</option>
                        <?php foreach ($statuses as $key => $label) : ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($status_filter, $key); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="button">絞り込む</button>
                </div>
            </form>

            <div class="bvtn-note-list">
                <?php if (!$notes->have_posts()) : ?>
                    <div class="bvtn-empty">該当する研究ノートはまだありません。</div>
                <?php else : ?>
                    <?php while ($notes->have_posts()) : $notes->the_post();
                        $id = get_the_ID();
                        $item_status = get_post_meta($id, '_bvtn_status', true);
                        $item_category = get_post_meta($id, '_bvtn_category', true);
                        $item_date = get_post_meta($id, '_bvtn_observed_on', true);
                        $item_insight = get_post_meta($id, '_bvtn_insight', true);
                    ?>
                        <article class="bvtn-note-card">
                            <div class="bvtn-note-meta">
                                <time><?php echo esc_html($item_date); ?></time>
                                <span class="bvtn-chip"><?php echo esc_html($categories[$item_category] ?? 'その他'); ?></span>
                                <span class="bvtn-status bvtn-status-<?php echo esc_attr($item_status); ?>"><?php echo esc_html($statuses[$item_status] ?? '未検証'); ?></span>
                            </div>
                            <h3><a href="<?php echo esc_url(add_query_arg(array('page' => 'belbo-vocal-notebook', 'edit' => $id), admin_url('admin.php'))); ?>"><?php the_title(); ?></a></h3>
                            <p><?php echo esc_html(wp_trim_words($item_insight, 58, '…')); ?></p>
                            <a class="bvtn-edit-link" href="<?php echo esc_url(add_query_arg(array('page' => 'belbo-vocal-notebook', 'edit' => $id), admin_url('admin.php'))); ?>">開いて振り返る →</a>
                        </article>
                    <?php endwhile; wp_reset_postdata(); ?>
                <?php endif; ?>
            </div>

            <div class="bvtn-export">
                <h3>バックアップ</h3>
                <p>研究ノート全件をJSONファイルとして保存できます。</p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="bvtn_export">
                    <?php wp_nonce_field('bvtn_export'); ?>
                    <button type="submit" class="button">JSONを書き出す</button>
                </form>
            </div>
        </aside>
    </div>
</div>
