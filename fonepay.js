console.log('fonepay page loaded');

jQuery(document).ready(function(){
    var url_string = window.location.href
    var url = new URL(url_string);
    var msg = url.searchParams.get("msg");
    if( msg){
        jQuery('.invoice-info').parent().append("<div class='alert alert-info' role='alert'>\
        <strong>" + msg + "</strong>\
          <button type='button' class='close' data-dismiss='alert' aria-label='Close'>\
            <span aria-hidden='true'>&times;</span>\
          </button></div>");
    }
})
