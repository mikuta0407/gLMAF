# gLMAF

## gLMAFとは
**g**et**L**atest**M**ail**A**ttached**F**ile = **gLMAF**

受信メールから添付ファイルを保存するPHPスクリプトです。

## 用語定義
- 取得: ファイル・外部サーバからの情報をPHPの変数内へ格納すること
- 出力: 標準出力への文字列出力
- 保存: ファイルシステムへの書き出し

## 動作要件

### 必須
- 安全にインターネットに接続できる一般的なLinux環境
	- 自動実行を行う場合cronが利用可能なLinux
	-  Windows上でWSL1/WSL2を使用しても動作可能です
		- WSLでも自動実行を利用する場合は、cronを有効にしてください
- 993番ポート宛接続が塞がれていないインターネット回線
- PHP(5.3以上)
	- shell_exec関数が実行可能であること
- curl(シェルから叩けるもの)


### 推奨環境
- Ubuntu 18.04以上/Debian Buster以上(Debian系ディストリ以外でも動くとは思いますが、一切テストしていません)
	- Raspberry Piシリーズ(初代B以上, Zeroを含む)等でも問題なく動作します。(扱う添付ファイルが大きい場合はメモリ容量に注意してください)
- PHP 7.2以上(Ubuntu 18.04でapt install phpをすると7.2が入るので)

### 開発環境
- Ubuntu 20.04
- PHP 8.0 (cli)

## 配置方法

```bash=
git clone https://github.com/mikuta0407/gLMAF.git
```

本文中の`/path/to/gLMAF` はすべてこのcloneされたgLMAFディレクトリへのパスとして**読み替えてください**。

## フォルダ構成について

- `eml/`
	デバッグモード時にemlファイルが保存される場所です。ファイル名がメールUIDとなるemlファイルが保存されます。
- `files/`
	ここに添付ファイルが保存されます。
- `lib/`
	実行するにあたって必要なライブラリが保存されています。変更しないでください。(PEARのMail_mimeDecodeの最小限のファイルが保存されています)
- `var/`
	変数保存場所です。現状では前回の最新メールUIDが保存されるlastmailnumが保存されています。何か理由がない限り変更しないでください。
- `glmaf.php`
	メインプログラムです。「php glmaf.php」と実行することで動作します。
- `README.md`
	この説明ファイルです。

## 実行時のディレクトリについて

- ファイルシステムへのR/Wを行うため、**フォルダ全てにrwx**を付与してください(実行ユーザーのみでいいので、最低でも700(rwx------)にしてください。)
	- ```bash=
		find /path/to/gLMAF -type d -exec chmod u+rwx {} +
		find /path/to/gLMAF -type f -exec chmod u+rw {} +
		# chmodのgroup/otherは適宜変更してください
	- PHPファイルに関しては実行権限を付与した状態で直接実行でも動作するようになっています(`./glmaf.php` と実行しても動作します)
		```bash=
		chmod u+x glmaf.php
		```
- プログラム内の処理でファイルの親パスを自動で読みに行くようにしているので、実行時のカレントディレクトリは選びません。(cron使用時にも事前のcdは必要ありません)

## 配置後に設定変更が必要な場所について

`var/account.ini`の編集が必要です。

- `id`: IMAPアクセス時のID(大抵の場合メールアドレス)を入力してください
- `password`: IMAPアクセス時のパスワードを入力してください。 **	平文保存となるため、ファイル管理を徹底してください**
- `server`: IMAPのアクセス先を入力してください。
- `port`: IMAPのポートが993番以外に書き換えてください(通常は993のままで大丈夫です)

`var/lastmailnum`については、空っぽのメールアカウントから始める場合、またはそのメールアカウントの受信トレイには処理を行いたいメールのみが入っている場合は変更の必要がありません。(既に大量にメールが存在するアカウントの場合、最新メールUIDを事前に入力しておく必要があります。0のままだと過去のメール全てに対して処理が行われるため、時間とストレージを大量に消費します)

## 実行方法について

引数無しでphpファイルを実行してください

- phpコマンドから実行する場合
```bash
php ./glmaf.php
```

- 実行権限を付与して実行ファイルとして実行する場合
```
chmod u+x ./glmaf.php #セットアップ時のみ
./glmaf.php
```

### cron実行について

crontabには以下のように記述できます。

- 1分毎に処理する場合
```
* * * * * php /path/to/gLMAF/glmaf.php
```

- 5分毎に処理する場合
```
*/5 * * * * php /path/to/gLMAF/glmaf.php
```

#### ログを残す場合
ただのリダイレクトです。

ログァイル名、パス等自由です。

(ログファイルに出力される内容は後述)

- 1分毎に処理する場合
```
* * * * * php /path/to/gLMAF/glmaf.php >> /path/to/gLMAF/log.txt
```

- 5分毎に処理する場合
```
*/5 * * * * php /path/to/gLMAF/glmaf.php >> /path/to/gLMAF/log.txt
```

## メールアカウントに関する注意点

接続先のメールアカウントの全ての新着メールに対して実行が行われます。そのため、このプログラムで行いたいメールのみを受信するアドレス(アカウント)の作成を推奨します

## プログラムの動作内容について

このプログラムは、実行されると以下のような処理を行います(通常時)

1. `var/account.ini`からサーバ接続情報を取得
2. メールボックスの最新メールのUIDを取得(成功するまで繰り返し)
	- (メールUIDはメールアカウントへの最初のメールを1として、連番で設定されています。)
3. 前回実行時の最初のメールUIDを`var/lastmailnum`から取得
4. 前回の最新メールUIDと今回の最新メールUIDを比較(同じ以下の場合はここで終了)
5. 最新が存在する場合は今回の最新メールUIDを`var/lastmailnum`へ保存
6. 前回の最新メールUIDと今回の最新メールUIDの差を取得し、差の数分以下の処理
	1. サーバから当該メールUIDのメールデータ(eml形式)で取得
	2. emlデータを処理し、添付ファイルがあれば保存

eml処理部分は以下のような処理をしています

1. mimeDecodeライブラリによってemlの内容をobject変数として参照できる用にデコード
2. 日時、fromアドレス、件名を取得、出力(ログ用)
3. emlの中に`multipart/mixed`という文字列(一般的に添付ファイルがあることを示す)がある場合に、以下の処理を実行
	1. part毎に処理し、textではない場合は添付ファイル名を取得し、part内のbody(base64からデコードされたバイナリ)をそのファイル名で保存

プログラムの特性上、実行中に添付バイナリファイルが2重にメモリ上に存在(eml状態とデコード状態)するため、大きな添付ファイルを扱い場合にはその分のメモリを占有します。

emlファイルについては通常時はファイルシステムへの保存を行いません。

### 標準出力に出力される内容(ログファイルに出力される内容)について
- メール1件毎に以下のようなログが出力されます
	- 添付ファイルなしの場合
		```
		======
		Mailnum: 100
		Time: Fri, 5 Nov 2021 20:58:47 +0900
		From: example@example.com
		Subject: これはテストメールです
		添付ファイルなし
		```
		
	- 添付ファイルがある場合
		```
		======
		Mailnum: 126
		Time: Fri, 5 Nov 2021 20:58:47 +0900
		From: example@example.com
		Subject: 添付ありテスト
		添付ファイル: hoge.pdf
		添付ファイル: fuga.mp4
		添付ファイル: test.docx
		```
		
### デバッグ有効時について
コマンドライン引数としてメールUIDを指定すると、デバッグモードとして起動します。

デバッグモード時は挙動が以下のようになります

- コマンドライン引数として渡されたメールUIDに対して強制的に処理(メールUIDの比較を行いません)
- 最新メールUIDを取得しません(`var/lastmailnum`に対して最新メールUIDを保存しません)
- 前回の最新メールUIDと、今回指定されたメールUIDを出力します。
- 取得したemlデータを`eml/<メールUID>.eml`として保存します
- mimeDecodeによってデコードされた変数のvar_dumpデータを`eml/<メールUID>.dump`として保存します。

デバッグモードは特定のメールのみの処理にも利用ができますが、保存失敗等の不具合の状況確認のために実装されています。(そのためにemlファイルとdumpファイルが保存されます) 

## その他応用方法について

eml処理関数内で`$mailtext`変数にメール本文が格納されています。

これらを利用することで、メールが来たらWebhookでDiscordに投稿する、といった疑似的なBotとしての利用ができます。Discord等はWebhookで添付ファイルも送信できるはずなので、そのあたりは工夫してご利用ください。

(本プログラムはファイルの保存までを実装しているため、その後の処理もいろいろ考えられますね。)

## 利用・参考プログラムについて

- mimeDecode
	https://pear.php.net/package/Mail_mimeDecode/
- mimeDecodeを利用した処理部分
	http://www.aiwake.co.jp/modules/bulletin/index.php?page=article&storyid=4

## ライセンスについて
This software is released under the MIT License, see LICENSE.txt.

MITライセンスです。LICENSE.txtをご確認ください。(lib/PEAR.php, lib/mimeDecode.phpは修正BSDライセンスです)