define(['jquery'], function ($) {
    return {
        init: function () {
            //Show msg
            $("#id_unlock_assessment").on("click", function () {
                if ($(this).prop("checked")) {
                    //Show a friendly warning message
                    $('#unlock-msg').show();
                }
                else {
                    //Show a friendly warning message
                    $('#unlock-msg').hide();
                }
            });
        }
    };
});