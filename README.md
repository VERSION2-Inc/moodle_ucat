UCAT Module for Moodle 2.0.x
======================

Installation
------

1. Copy "blocks" and "customscripts" directories to your Moodle root
directory.

2. Edit config.php file and add the code

	$CFG->customscripts = '<Moodle root>/customscripts';

before

	require_once(dirname(__FILE__) . '/lib/setup.php');

Replace <Moodle root> with the full path to your Moodle root directory.

3. Log in to your Moodle site and run Site administration > Notifications.


インストール方法
------

1. blocksとcustomscriptsディレクトリをMoodleのルートディレクトリにコピーしてく
ださい。

2. config.phpを編集して、

	$CFG->customscripts = '<Moodle root>/customscripts';

を

	require_once(dirname(__FILE__) . '/lib/setup.php');

の前に追加してください。<Moodle root>はMoodleルートディレクトリへの絶対パスを
指定してください。

3. Moodleサイトにログインして、通知を実行してください。
