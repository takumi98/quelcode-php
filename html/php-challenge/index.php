<?php
session_start();
require('dbconnect.php');

// RT関数
// リツイートの投稿を探す
function getRetweetMessage($db,$message_id) {
	$retweets = $db->prepare('SELECT * FROM posts WHERE retweet_message_id=?');
		$retweets->execute(array(
			$message_id
		));

		return $retweets->fetch();
}

// RTした元の記事のidを取得
function getRetweetOriginal($db,$message_id) {
  $retweets = $db->prepare('SELECT * FROM posts WHERE id=?');
  $retweets->execute(array(
    $message_id
  ));
  $rt_o = $retweets->fetch();

  $retweet_original = $db->prepare('SELECT * FROM members WHERE id=?');
  $retweet_original->execute(array(
    $rt_o['member_id']
  ));

  return $retweet_original->fetch();
}

// 同じ記事をRTしていないか調べる
function getRetweetDone($db,$rt_h,$rt_message_id){
  for ($i = 0; $i < count($rt_h); $i++) {
    if ($rt_message_id == $rt_h[$i]['retweet_message_id']){
    $result = true;
    break;
    }
	}
	
  return $result;
}
// すでにいいねしていないか調べる
function getLikeDone($db,$like_message_id){
	// 全てのいいねの情報を取得
	$like_lists = $db->query('SELECT * FROM `like`');
	$like_list = $like_lists->fetchAll();
	
  for ($i = 0; $i < count($like_list); $i++) {
    if ($like_message_id == $like_list[$i]['like_message_id']){
    $result = true;
    break;
    }
	}
	
  return $result;
}
// どのユーザーがいいねしたか
function getLikeDoneId($db,$like_user_id,$like_message_id){
	// 全てのいいねの情報を取得
	$like_lists = $db->prepare('SELECT * FROM `like` WHERE like_user_id=?');
	$like_lists->execute(array(
		$like_user_id
	));
	$like_list = $like_lists->fetchAll();
	
  for ($i = 0; $i < count($like_list); $i++) {
    if ($like_message_id == $like_list[$i]['like_message_id']){
			$result = true;
			break;
    }
	}
	
  return $result;
}

// ユーザーの過去のRT記事
function getRetweethistory($db,$user_id){
  $retweet_history = $db->prepare('SELECT * FROM posts WHERE member_id=? AND retweet_message_id is not null');
	$retweet_history->execute(array(
		$user_id
	));
	
  return $retweet_history->fetchAll();
}

// ユーザーの過去のいいね
function getLikehistory($db,$user_id){
  $like_history = $db->prepare('SELECT * FROM `like` WHERE like_user_id=?');
	$like_history->execute(array(
		$user_id
	));
	
  return $like_history->fetchAll();
}


// 記事がRTかどうか
function getMessage($db,$message_id){
	$messages = $db->prepare('SELECT * FROM posts WHERE id=?');
	$messages->execute(array(
		$message_id
	));

	return $messages->fetch();
}

// いいねを取得
function getLikeUser($db,$message_id){
	$like_user = $db->prepare('SELECT `like_user_id` FROM `like` WHERE `like_message_id`=?');
	$like_user->execute(array(
		$message_id
	));

	return $like_user->fetch();
}

if (isset($_SESSION['id']) && $_SESSION['time'] + 3600 > time()) {
	// ログインしている
	$_SESSION['time'] = time();

	$members = $db->prepare('SELECT * FROM members WHERE id=?');
	$members->execute(array($_SESSION['id']));
  $member = $members->fetch();

  // RT履歴
	$rt_h = getRetweethistory($db,$member['id']);
	// いいね履歴
	$like_h = getLikehistory($db,$member['id']);

} else { // ログインしていない
	header('Location: login.php'); exit();
}

// 投稿を記録する
if (!empty($_POST)) {
	if ($_POST['message'] != '') {
		$message = $db->prepare('INSERT INTO posts SET member_id=?, message=?, reply_post_id=?, created=NOW()');
		$message->execute(array(
			$member['id'],
			$_POST['message'],
			$_POST['reply_post_id']
		));

		header('Location: index.php'); exit();
	}
}

// 投稿を取得する
$page = $_REQUEST['page'];
if ($page == '') {
	$page = 1;
}
$page = max($page, 1);

// 最終ページを取得する
$counts = $db->query('SELECT COUNT(*) AS cnt FROM posts');
$cnt = $counts->fetch();
$maxPage = ceil($cnt['cnt'] / 5);
$page = min($page, $maxPage);

$start = ($page - 1) * 5;
$start = max(0, $start);

$posts = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id ORDER BY p.created DESC LIMIT ?, 5');
$posts->bindParam(1, $start, PDO::PARAM_INT);
$posts->execute();

// リツイートを記録する
if (isset($_REQUEST['retweet'])) {
  // 記事がRTなら情報を取得してくる
  $retweet_message = getRetweetMessage($db,$_REQUEST['retweet']);
	// すでにリツイートされているかの確認
	$retweet_conf = $db->prepare('SELECT COUNT(*) AS conf FROM posts WHERE retweet_message_id=? AND member_id=?');
	$retweet_conf->execute(array(
		$_REQUEST['retweet'],
		$member['id']
	));
	$rt_conf = $retweet_conf->fetch();
	// RT情報取得
	$retweet_messages = $db->prepare('SELECT * FROM posts WHERE id=?');
	$retweet_messages->execute(array(
		$_REQUEST['retweet']
	));
	$retweet = $retweet_messages->fetch();

	if ($rt_conf['conf'] == 0){// リツイートされていない
		// RTの投稿をRTしていてもとのms_idじゃない場合
		if ($retweet['retweet_message_id'] != 0){
			$rt_message = $db->prepare('INSERT INTO posts SET message=?, member_id=?, reply_post_id=0, retweet_message_id=?, created=NOW()');
			$rt_message->execute(array(
				$retweet['message'],
				$member['id'],
				$retweet['retweet_message_id'],
			));
			header('Location: index.php'); 
			exit();
		} else {// もとの記事のmessage_idで投稿
			$rt_message = $db->prepare('INSERT INTO posts SET message=?, member_id=?, reply_post_id=0, retweet_message_id=?, created=NOW()');
			$rt_message->execute(array(
				$retweet['message'],
				$member['id'],
				$_REQUEST['retweet'],
			));
			header('Location: index.php'); 
			exit();
		}
	}
}

// 全てのRT記事の取得
$retweet_lists = $db->query('SELECT * FROM posts WHERE retweet_message_id is not null');
$retweet_list = $retweet_lists->fetchAll();

// いいねを記録
if (isset($_REQUEST['like'])) {
	// いいねの記事がRTかどうか
	$message_h = getMessage($db,$_REQUEST['like']);
	if ($message_h['retweet_message_id'] == null){// RTの記事ではない
		$likes = $db->prepare('INSERT INTO `like` SET `like_user_id`=?, `like_message_id`=?');
		$likes->execute(array(
			$member['id'],
			$_REQUEST['like']
		));
		header('Location: index.php');
		exit();
	} else {// RTの記事
		// RTの場合idではなくretweet_message_idでいいねを登録する
		$retweet_id = $db->prepare('SELECT * FROM `posts` WHERE id=?');
		$retweet_id->execute(array(
			$_REQUEST['like']
		));
		$rt_id = $retweet_id->fetch();
	
		$likes_r = $db->prepare('INSERT INTO `like` SET `like_user_id`=?, `like_message_id`=?');
		$likes_r->execute(array(
			$member['id'],
			$rt_id['retweet_message_id']
		));
		header('Location: index.php');
		exit();
	}
}


// 返信の場合
if (isset($_REQUEST['res'])) {
	$response = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id AND p.id=? ORDER BY p.created DESC');
	$response->execute(array($_REQUEST['res']));

	$table = $response->fetch();
	$message = '@' . $table['name'] . ' ' . $table['message'];
}

// 記事の削除
if (isset($_REQUEST['delete'])){
  $id = $_REQUEST['delete'];
  
  // 投稿を検査する
  $messages = $db->prepare('SELECT * FROM posts WHERE id=?');
  $messages->execute(array($id));
  $message = $messages->fetch();

  // 削除する
  $del = $db->prepare('DELETE FROM posts WHERE id=?');
	$del->execute(array($id));
	
  header('Location: index.php'); 
  exit();
}

// いいねの削除
if (isset($_REQUEST['like_delete'])){
	$id = $_REQUEST['like_delete'];

	// ログインユーザーの照会
	$del = $db->prepare('DELETE FROM `like` WHERE like_user_id=? AND like_message_id=?');
	$del->execute(array(
		$_SESSION['id'],
		$id
	));
}

// RTのid
$retweet_counts = $db->prepare('SELECT COUNT(*) AS `count` FROM posts WHERE retweet_message_id=?');
	if ((int)$post['retweet_message_id'] != 0) {// RTしている記事
		$retweet_counts->execute(array($post['retweet_message_id']));
		$retweet_count = $retweet_counts->fetch();
	}

// htmlspecialcharsのショートカット
function h($value) {
	return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// 本文内のURLにリンクを設定します
function makeLink($value) {
	return mb_ereg_replace("(https?)(://[[:alnum:]\+\$\;\?\.%,!#~*/:@&=_-]+)", '<a href="\1\2">\1\2</a>' , $value);
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<title>ひとこと掲示板</title>

	<link rel="stylesheet" href="style.css" />
	<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.13.1/css/all.css" integrity="sha384-xxzQGERXS00kBmZW/6qxqJPyxW3UR0BPsL4c8ILaIWXva5kFi7TxkIIaMiKtqV1Q" crossorigin="anonymous">
</head>

<body>
<div id="wrap">
  <div id="head">
    <h1>ひとこと掲示板</h1>
  </div>
  <div id="content">
  	<div style="text-align: right"><a href="logout.php">ログアウト</a></div>
    <form action="" method="post">
      <dl>
        <dt><?php echo h($member['name']); ?>さん、メッセージをどうぞ</dt>
        <dd>
          <textarea name="message" cols="50" rows="5"><?php echo h($message); ?></textarea>
          <input type="hidden" name="reply_post_id" value="<?php echo h($_REQUEST['res']); ?>" />
        </dd>
      </dl>
      <div>
        <p>
          <input type="submit" value="投稿する" />
        </p>
      </div>
    </form>

<?php
foreach ($posts as $post):
?>
    <div class="msg">
		<img src="member_picture/<?php echo h($post['picture']); ?>" width="48" height="48" alt="<?php echo h($post['name']); ?>" />
		<!-- 投稿がリツートかどうか -->
		<?php if ((int)$post['retweet_message_id'] != 0) {
						$retweet_users = $db->prepare('SELECT name FROM members WHERE id=?');
						$retweet_users->execute(array($post['member_id']));
						$retweet_user = $retweet_users->fetch();
		?>
		<!-- リツイートしたユーザーの表示 -->
		<p class="day"><?php echo h($retweet_user['name']) ?>がリツイートしました。</p>
		<?php } ?>
		<?php 
		// 過去のRT
		$retweet_history_id = getRetweetDone($db,$retweet_list,$post['id']);
    // RT数のカウント
    $retweet_counts = $db->prepare('SELECT COUNT(*) AS `count` FROM posts WHERE retweet_message_id=?');
			if ((int)$post['retweet_message_id'] != 0) {// RTしている記事
				$retweet_counts->execute(array($post['retweet_message_id']));
				$retweet_count = $retweet_counts->fetch();
      } elseif ($retweet_history_id === true) {// 自RTされている元の記事
        $retweet_counts->execute(array($post['id']));
				$retweet_count = $retweet_counts->fetch();
			} else {// リツイートされていない記事
				$retweet_count['count'] = '';
			}
		?>
		<?php 
		// 過去のいいね
		$like_history_id = getLikeDone($db,$post['id']);
    // いいねのカウント
    $like_counts = $db->prepare('SELECT COUNT(*) AS `count` FROM `like` WHERE like_message_id=?');
		if ((int)$post['retweet_message_id'] != 0) {// いいねしている記事
			$like_counts->execute(array($post['retweet_message_id']));
			$like_count = $like_counts->fetch();
			if ($like_count['count'] == 0) {
				$like_count['count'] = '';
			}
  	} elseif ($like_history_id === true) {// 自いいねされている元の記事
  	  $like_counts->execute(array($post['id']));
			$like_count = $like_counts->fetch();
		} else {// いいねされていない記事
			$like_count['count'] = '';
		}
		?>
    <p>
      <?php echo makeLink(h($post['message'])); ?>
      <span class="name"> <!-- RTの場合もとの記事を投稿したユーザーを表示する -->
        （<?php if ($post['retweet_message_id'] == null){
                  echo h($post['name']);
                } else {
                  $original_user = getRetweetOriginal($db,$post['retweet_message_id']);
                  echo h($original_user['name']);
                } 
          ?>）
      </span>
			[<a href="index.php?res=<?php echo h($post['id']); ?>">Re</a>]

		<!-- リツイートアイコン -->
      <?php if ($post['retweet_message_id'] > 0) {// 記事がRTの場合
							$retweet_history = getRetweetDone($db,$rt_h,$post['retweet_message_id']);
							$like_user = getLikeUser($db,$post['retweet_message_id']);
      ?>
        <?php if($retweet_history === true){// 過去にRTしている記事?>
                [<a href="delete.php?id=<?php echo h($post['id']); ?>"><i class="fas fa-retweet active" style="color: rgb(23, 191, 99)"><?php echo h($retweet_count['count']) ?></i></a>]
        <?php } else { //RTしていない記事?>
                [<a href="index.php?retweet=<?php echo h($post['id']); ?>"><i class="fas fa-retweet active" style="color: #8899A6"><?php echo h($retweet_count['count']) ?></i></a>]
        <?php } ?>
      <?php } else{
              $retweet_history = getRetweetDone($db,$rt_h,$post['id']);
      ?>
        <?php if($retweet_history === true){// 過去にRTしている記事?>
                [<a href="delete.php?id=<?php echo h($post['id']); ?>"><i class="fas fa-retweet active" style="color: rgb(23, 191, 99)"><?php echo h($retweet_count['count']) ?></i></a>]
        <?php } else { //RTしていない記事?>
                [<a href="index.php?retweet=<?php echo h($post['id']); ?>"><i class="fas fa-retweet active" style="color: #8899A6"><?php echo h($retweet_count['count']) ?></i></a>]
        <?php } ?>
        <?php }?>

		<!-- いいねボタン -->
		<?php 
			if ($post['retweet_message_id'] > 0) {// 記事がRTの場合
				$like_done = getLikeDone($db,$post['retweet_message_id']);
				$test = getLikeDoneId($db,$member['id'],$post['retweet_message_id']);
		?>
		<?php if ($test === true){?>
					[<a href="index.php?like_delete=<?php echo h($post['retweet_message_id']); ?>"><i class="fas fa-heart active" style="color: rgb(224, 36, 94);"><?php echo h($like_count['count']) ?></i></a>]
					
		<?php } else {?>
					[<a href="index.php?like=<?php echo h($post['id']); ?>"><i class="fas fa-heart active" style="color: #8899A6"><?php echo h($like_count['count']) ?></i></a>]

		<?php }?>
		<?php 
			} else {// 記事がRTじゃない場合
				$like_done = getLikeDone($db,$post['id']);
				$test = getLikeDoneId($db,$member['id'],$post['id']);
		?>
		<?php if ($test === true){?>
					[<a href="index.php?like_delete=<?php echo h($post['id']); ?>"><i class="fas fa-heart active" style="color: rgb(224, 36, 94);"><?php echo h($like_count['count']) ?></i></a>]
					
		<?php } else {?>
					[<a href="index.php?like=<?php echo h($post['id']); ?>"><i class="fas fa-heart active" style="color: #8899A6"><?php echo h($like_count['count']) ?></i></a>]
					
		<?php }?>
		<?php	
			}
		?>	

		</p>
    <p class="day"><a href="view.php?id=<?php echo h($post['id']); ?>"><?php echo h($post['created']); ?></a>
		<?php
if ($post['reply_post_id'] > 0):
?>
<a href="view.php?id=<?php echo
h($post['reply_post_id']); ?>">
返信元のメッセージ</a>
<?php
endif;
?>
<?php
if ($_SESSION['id'] == $post['member_id']):
?>
[<a href="index.php?delete=<?php echo h($post['id']); ?>"
style="color: #F33;">削除</a>]
<?php
endif;
?>
    </p>
    </div>
<?php
endforeach;
?>

<ul class="paging">
<?php
if ($page > 1) {
?>
<li><a href="index.php?page=<?php print($page - 1); ?>">前のページへ</a></li>
<?php
} else {
?>
<li>前のページへ</li>
<?php
}
?>
<?php
if ($page < $maxPage) {
?>
<li><a href="index.php?page=<?php print($page + 1); ?>">次のページへ</a></li>
<?php
} else {
?>
<li>次のページへ</li>
<?php
}
?>
</ul>
  </div>
</div>
</body>
</html>
