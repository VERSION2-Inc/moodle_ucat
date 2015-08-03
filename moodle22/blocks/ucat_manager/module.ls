M.block_ucat_manager ?= {
    ajaxurl: M.cfg.wwwroot + \/blocks/ucat_manager/ajax.php

    users_init: (@Y, @params) ->
        Y.one \#menuuset .on \change ->
            @ancestor \form .submit!

        Y.one \#deluset .on \click (e) ->
            if !confirm M.str.block_ucat_manager.reallydeleteuserset
                e.preventDefault!

        Y.all \.ability .on \click (e) ->
            ability = e.target.getData \ability
            userid = e.target.getData \user-id

            oldability = ability

            input = Y.Node.create \<input>
                .setAttrs {
                    size: 3
                    value: ability
                }

            input.on \key ->
                ability = @get \value

                cell = @ancestor!

                @set \disabled \disabled
                M.util.add_spinner Y, @ancestor! .show!

                Y.io M.block_ucat_manager.ajaxurl, {
                    method: \post
                    data:
                        act: \abilityupdate
                        course: params.course
                        uset: params.uset
                        user: userid
                        ability: ability
                    on:
                        success: (id, o) ->
                            res = JSON.parse o.responseText
                            cell.setData \ability res.ability
                            cell.setHTML res.ability
                        failure: (id, o) ->
                            cell.setHTML oldability
                }
            , \enter

            input.on \blur ->
                cell = @ancestor!
                cell.setHTML (cell.getData \ability)

            e.target.setHTML input

            input.select!
}

# function cat_copy_data(prefix) {
#     var newvalue = document.getElementById(prefix + "batch").value;
#     var elts = document.getElementsByTagName("input");
#     for (var i = 0; i < elts.length; i++) {
#         if (elts[i].id.match(/^chk_/) && elts[i].checked) {
#             var qid = elts[i].id.substr(4);
#             document.getElementById(prefix + qid).value = newvalue;
#         }
#     }
# }

# /**
#  *
#  * @param {Element} elt
#  */
# function cat_checkall(elt) {
#     if (elt.checked) {
#         checkall();
#     } else {
#         checknone();
#     }
# }
