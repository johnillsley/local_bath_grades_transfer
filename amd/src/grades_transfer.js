define(['jquery'], function ($) {
    return {
        init: function () {
            //Fetched any new assessments msg
            $("#fetched_new_assessments_notif").prependTo('#region-main').animate(
                {
                    top: '350px'
                }, 'slow'
            ).fadeOut(5000);
            //Show msg
            $("input[name='bath_grade_transfer_samis_unlock_assessment']").on("click", function () {
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