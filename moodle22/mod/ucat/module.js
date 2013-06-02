M.mod_ucat = M.mod_ucat || {
    /** @memberOf M.mod_ucat */
    ajax_uri: "http://localhost/dev/moodle22/mod/ucat/ajax.php?action=load_question&cmid=",

    attempt_init: function(Y, cmid) {
        this.Y=Y;
        this.cmid = cmid;
        this.ajax_uri = "http://localhost/dev/moodle22/mod/ucat/ajax.php?action=load_question&cmid="+cmid;

        Y.on("click", function (e) {
            console.log("startatt");
            M.mod_ucat.load_question();
        }, "#startattempt");
    },

    start_attempt: function() {

    },

    load_question: function() {
//        var cfg = {
//                method: "POST",
//                data: ""
//        };

        function comp(tid, response, args) {
            console.log(response.responseText);
            Y.one("#questionarea").setContent(response.responseText);
            console.log("ok");
        }
        Y=this.Y;
        Y.on("io:complete", comp, Y);
        Y.io(this.ajax_uri);
    }
};

