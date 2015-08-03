M = @M
M.mod_ucat ?= {}

M.mod_ucat.str = M.str.mod_ucat

M.mod_ucat.attempt_init = (@Y) ->

M.mod_ucat.mod_form_init = (@Y) ->
    Y = @Y

    Y.one('body').appendChild Y.Node.create("""
        <div id="tppanelcontent">
            <div class="yui3-widget-bd">
                <form id="targetprobabilityform">
                    <p>#{@str.targetprobability}</p>
                    <p><input type="text" id="targetprobability"></p>
                    <div id="targetprobability_error" class="error"></div>
                </form>
            </div>
        </div>
        """)
    Y.one('#targetprobabilityform').on 'submit', (e) ->
        e.preventDefault()

    tppanel = new Y.Panel {
        +modal
        +centered
        +render
        -visible
        srcNode: '#tppanelcontent'
        width: 400
        zIndex: 1
        buttons: [
            {
                value: M.str.moodle.ok
                section: Y.WidgetStdMod.FOOTER
                action: (e) ->
                    e.preventDefault()

                    tp = parseFloat Y.one('#targetprobability').get('value')
                    logitbias = -Math.log(tp / (1 - tp))

                    tperror = Y.one('#targetprobability_error')
                    tperror.setHTML ?= tperror.setContent

                    if !isFinite logitbias
                        tperror.setHTML M.str.mod_ucat.tpoutofrange
                        return

                    Y.one('#id_logitbias').set 'value', logitbias
                    tppanel.hide()
            }
            {
                value: M.str.moodle.cancel
                section: Y.WidgetStdMod.FOOTER
                action: (e) ->
                    e.preventDefault()
                    tppanel.hide()
            }
        ]
    }

    Y.one('#setlogitbiasbytp').on 'click', ->
        tppanel.show()
        Y.one('#targetprobability').focus()
