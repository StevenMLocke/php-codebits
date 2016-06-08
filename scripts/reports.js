$(document).ready(function(){
    $('button').prop("disabled", true);
    $('.report-input').on("input", function(){
        if ($('.report-input').val()) {
            $('button').prop("disabled", false);
        }else{
            $('button').prop("disabled", true);
        }
    });
});