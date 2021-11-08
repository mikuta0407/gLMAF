#!/usr/bin/php
<?php

/*
IMAPに接続し、最新メールから添付ファイルを保存するPHPスクリプト

作成者: @mikuta0407

Manual: https://hackmd.io/@mikuta0407/glmaf (または./README.md)

参考プログラム: http://www.aiwake.co.jp/modules/bulletin/index.php?page=article&storyid=4
*/

// MIMEのデコードライブラリをrequire_once
require_once __DIR__.'/lib/mimeDecode.php';

// account.ini読み込み
$envarray = parse_ini_file(__DIR__.'/var/account.ini');
$imapid = $envarray['id']; // IMAPユーザー名
$imappasswd = $envarray['password']; // IMAPパスワード
$imapserver = "Imaps://".$envarray['server'].":".$envarray['port']; // IMAPサーバ

// デバッグ用
if (isset($argv[1])){
    echo "!!!Debug Mode!!!\n";
    $debug = true; // 通常はfalse
    $mailnum = $argv[1]; // CLIからの実行時、引数としてメール番号を指定することでデバッグ時に任意のメールを取得できる
} else {
    $debug = false;
}

// メール全件数を取得 ($mailnumへ)
if(!$debug){
    do {
        $mailnum = shell_exec("curl -sS -u $imapid:$imappasswd '$imapserver' -X 'EXAMINE INBOX' | grep EXISTS | sed -e 's/\* //' | sed -e 's/EXISTS.*//' | sed -e 's/ //g '");
        $mailnum = str_replace("\n", '', $mailnum);
    } while (!$mailnum);
}

// 前回の全件数を確認 ($lastmailnumへ)
$lastmailnum = file_get_contents(__DIR__. "/var/lastmailnum");

// デバッグ用強制処理
if ($debug){
    echo "前回: $lastmailnum\n";
    echo "今回: $mailnum\n";
    // eml取得
    $eml = shell_exec("curl -sS -u $imapid:$imappasswd $imapserver/INBOX\;UID=$mailnum | nkf");
    // eml保存
    file_put_contents(__DIR__."/eml/$mailnum.eml", $eml);
    // eml処理
    extracteml($eml, $mailnum);    
}

// 前回処理時より後に最新メールが存在した場合 (ここのifはデバッグ有効時は通らない)
if ($mailnum > $lastmailnum && !$debug){
    // 今回処理したメール番号(現状最新)を保存
    file_put_contents(__DIR__."/var/lastmailnum", $mailnum);
    // 未処理最新分すべて
    for ($i = $lastmailnum + 1; $i <= $mailnum; $i++){
        // eml取得
        $eml = shell_exec("curl -sS -u $imapid:$imappasswd $imapserver/INBOX\;UID=$i | nkf");
        // eml処理
        extracteml($eml, $i);
    }    
}

// eml処理関数 (引数はemlの内容および処理するメール番号(CLIへの出力用))
function extracteml($eml, $i){
    global $debug;

    //受信メールから読み込み
    $params['include_bodies'] = true;
    $params['decode_bodies'] = true;
    $params['decode_headers'] = true;
    $params['crlf'] = "\r\n";

    $decoder = new Mail_mimeDecode($eml);
    $structure = $decoder->decode($params);
    if ($debug) {
        ob_start();
        var_dump($structure);
        $dump = ob_get_contents();
        ob_end_clean();
        file_put_contents(__DIR__ . "/eml/tmp.dump", $dump);
    }

    // 日時
    $time = $structure->headers['date'];
    //送信者のメールアドレスを抽出
    $mailfrom = $structure->headers['from'];
    $mailfrom = addslashes($mailfrom);
    $mailfrom = str_replace('"','',$mailfrom);
    $mailfrom = preg_replace('/(^.*<|>$)/', '', $mailfrom);

    // 件名を取得
    $subject = $structure->headers['subject'];

    echo "======\nMailnum: $i\nTime: $time\nFrom: $mailfrom\nSubject: $subject\n";

    if (strpos($eml, "multipart/mixed") !== FALSE){
        // 本文、添付ファイル(画像)を抽出
        switch (strtolower($structure->ctype_primary)) {
            case "text":
                // シングルパート(テキストのみ)
                $mailtext = $structure->body;
                break;
            case "multipart":
                // マルチパート(画像付き)
                foreach ($structure->parts as $part) {
                    switch (strtolower($part->ctype_primary)) {
                        case "text":
                            $body = $part->body;
                            break;
                        default:
                            // $part->ctype_parameters['name'] にファイル名
                            if (isset($part->ctype_parameters['name'])){
                                echo "添付ファイル: ". $part->ctype_parameters['name'] ."\n";
                                file_put_contents(__DIR__. "/files/".$part->ctype_parameters['name'],$part->body);
                            }
                            break;
                    }
                }
                break;
            default:
                
        }
    } else {
        echo "添付ファイルなし\n";
    }
}