# UCAT Module for Moodle

## Moodle 2.2 or later

Use files in moodle_ucat/moodle22 directory.

### Installation

1. Copy "blocks" and "mod" directories to your Moodle root
directory.

2. Log in to your Moodle site and run Site administration > Notifications.


## Moodle 2.0.x

Use files in moodle_ucat/moodle directory.

### Installation

1. Copy "blocks" and "customscripts" directories to your Moodle root
directory.

2. Edit config.php file and add the code

	$CFG->customscripts = '<Moodle root>/customscripts';

before

	require_once(dirname(__FILE__) . '/lib/setup.php');

Replace <Moodle root> with the full path to your Moodle root directory.

3. Log in to your Moodle site and run Site administration > Notifications.


### インストール方法

1. blocksとcustomscriptsディレクトリをMoodleのルートディレクトリにコピーしてく
ださい。

2. config.phpを編集して、

	$CFG->customscripts = '<Moodle root>/customscripts';

を

	require_once(dirname(__FILE__) . '/lib/setup.php');

の前に追加してください。<Moodle root>はMoodleルートディレクトリへの絶対パスを
指定してください。

3. Moodleサイトにログインして、通知を実行してください。

付記:
------

研究の一部は科学研究費補助金(基盤研究(C)課題番号：22520590）を利用している．

Acknowledgements:
------

A part of the present study was supported by a Grant-in-Aid for Scientific Research for 2010-2012 (No.22520590) from the Japan Society for the Promotion of Science.
