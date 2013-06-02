<?php // $Id$
require_once '../../config.php';
require_once "$CFG->dirroot/blocks/cat_manager/lib.php";

$attemptid = required_param('attempt', PARAM_INT);
cat_download_record($attemptid);
