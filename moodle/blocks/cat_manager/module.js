/**
 * Computer-Adaptive Testing
 *
 * @package ucat
 * @author  VERSION2 Inc.
 * @version $Id: module.js 8 2012-03-15 09:31:03Z yama $
 */

function cat_copy_data(prefix) {
    var newvalue = document.getElementById(prefix + "batch").value;
    var elts = document.getElementsByTagName("input");
    for (var i = 0; i < elts.length; i++) {
        if (elts[i].id.match(/^chk_/) && elts[i].checked) {
            var qid = elts[i].id.substr(4);
            document.getElementById(prefix + qid).value = newvalue;
        }
    }
}

function cat_checkall(elt) {
    if (elt.checked) {
        checkall();
    } else {
        checknone();
    }
}
