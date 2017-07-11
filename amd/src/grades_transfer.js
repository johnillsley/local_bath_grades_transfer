define(['jquery'], function ($) {
    return {
        init: function () {
           //Show msg
            $( "#id_unlock_assessment" ).on( "click",function(){
                //Show a friendly warning message
                $(this).after("<div class=\"alert alert-warning alert-dismissable\"><a href=\"#\" class=\"close\" data-dismiss=\"alert\" aria-label=\"close\">&times;</a><strong>Success!</strong> Indicates a successful or positive action.</div>");

            });
        }
    };
});