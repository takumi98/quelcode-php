<?php
session_start();
require('dbconnect.php');

if (isset($_SESSION['id'])) {
	$id = $_REQUEST['id'];
	
	// 投稿を検査する
	$messages = $db->prepare('SELECT * FROM posts WHERE id=?');
	$messages->execute(array($id));
	$message = $messages->fetch();

	if ($message['member_id'] == $_SESSION['id'] && $message['retweet_message_id'] !== null) {
		// 削除する
		$del = $db->prepare('DELETE FROM posts WHERE id=?');
		$del->execute(array($id));
	}
	// RT削除
	if ($message['retweet_message_id'] === null) {// 元の記事
		$del = $db->prepare('DELETE FROM posts WHERE member_id=? AND retweet_message_id=?');
		$del->execute(array(
			$_SESSION['id'],
			$id
		));
	} elseif ($message['retweet_message_id'] != null && $_SESSION['id'] != $message['member_id']){// 他のユーザーにRTされた記事
		$del = $db->prepare('DELETE FROM posts WHERE member_id=? AND retweet_message_id=?');
		$del->execute(array(
			$_SESSION['id'],
			$message['retweet_message_id']
		));
	} else{
		$del = $db->prepare('DELETE FROM posts WHERE id=?');
		$del->execute(array($id));
	}
}

header('Location: index.php'); exit();
?>
