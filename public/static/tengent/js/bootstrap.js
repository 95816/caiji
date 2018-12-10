$(document).ready(function () {
    play(10)
});

function play(b) {
    var c = Date.parse(new Date());
    var d = $("input:hidden[name='smartbox_from']").val();
    var f = $("input:hidden[name='tv_child']").val();
    $.ajax({
        'type': 'post',
        'data': {
            ajax: 1,
            timestamp: c,
            io: b,
            id: d,
            cid: f
        },
        'url': URL,
        'dataType': 'json',
        'success': function (a) {
            if (a.status) {
                console.log(a);
                set_action(a)
            } else {
                alert(a.msg)
            }
        }
    })
}

function set_action(a) {
    var b = '<iframe border="0" frameborder="0" style="border: 2px solid #f0f0f0" height="660" marginheight="0" marginwidth="0" scrolling="no"' + 'src="' + a.url + a.r_url + '" width="100%" allowfullscreen="true"' + ' allowtransparency="false" id="source"></iframe>';
    $('#player').html(b)
}

function secret(a) {
    code = CryptoJS.MD5(CODE).toString();
    var b = CryptoJS.enc.Utf8.parse(code.substring(0, 16));
    var c = CryptoJS.enc.Utf8.parse(code.substring(16));
    return CryptoJS.AES.decrypt(a, c, {
        iv: b,
        padding: CryptoJS.pad.Pkcs7
    }).toString(CryptoJS.enc.Utf8)
}