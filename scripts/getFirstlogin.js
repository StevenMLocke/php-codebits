$(document).ready(function() {
    $( "#stuSearch" ).autocomplete({
        source: 'http://some.domain.com/pages/includes/autocomplete.php'
    });
});