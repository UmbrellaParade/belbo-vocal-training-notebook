=== べるぼ ボーカル研究ノート ===
Contributors: umbrellaparade
Tags: vocal, training, notebook, private
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later

歌の気づき、Codexの考察、検証結果を分けて蓄積・振り返る個人用研究ノートです。

== Description ==

WordPress管理画面の「歌の研究ノート」から利用します。

* 自分の気づきと仮説を分けて保存
* Codexの意見・発展仮説を別欄に保存
* 次に試すこと、試した結果を記録
* 状態・テーマ・キーワードで振り返り
* 全件をJSONバックアップとして書き出し
* GitHub Releases経由の更新通知

ノートは非公開のカスタム投稿としてWordPress内に保存され、管理画面にログインできるユーザーだけが操作できます。

== Changelog ==

= 1.0.2 =
* WordPressのプラグイン一覧で自動更新判定がnullの場合に起きる型エラーを修正。
* 自動更新はWordPress標準のサイト設定へ安全に登録する方式に変更。

= 1.0.1 =
* 最初の気づきの日付を2026年9月23日に修正。
* GitHub Releaseで公開した更新をWordPressの自動更新対象に追加。

= 1.0.0 =
* 初回リリース。
* 最初の歌唱メモを初期データとして追加。
