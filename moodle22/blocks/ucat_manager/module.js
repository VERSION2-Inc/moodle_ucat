/**
 * Computer-Adaptive Testing
 *
 * @package ucat
 * @author  VERSION2 Inc.
 * @version $Id: module.js 22 2012-08-05 18:25:21Z yama $
 */

/**
 * @param {String} prefix
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

/**
 *
 * @param {Element} elt
 */
function cat_checkall(elt) {
    if (elt.checked) {
        checkall();
    } else {
        checknone();
    }
}
