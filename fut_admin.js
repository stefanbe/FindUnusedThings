$(function() {
    $('#fut-admin').on('click','#fut-things .fut-plugin-del',function() {
        if($('.fut-plugin-del:checked').length > 0)
            $('.fut-plugin-del-all').prop("checked",true);
        else
            $('.fut-plugin-del-all').prop("checked",false);
    });

    $('#fut-admin').on('click','.fut-plugin-del-all',function() {
        if($(this).is(":checked"))
            $('#fut-things .fut-plugin-del').prop("checked",true);
        else
            $('#fut-things .fut-plugin-del').prop("checked",false);
    });

    $('form[name="fut-form"]').submit(function(e){
        if($('#fut-things .fut-plugin-del:checked').length < 1)
            e.preventDefault();
    });

    $('#fut-admin').on('click','#fut-things .fut-preview', function(e) {
        e.preventDefault();
        if(is_img(this.href))
            dialog_img_preview(this.href);
        else
            dialog_iframe_preview(this.href);
    });
});

