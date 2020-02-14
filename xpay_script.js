var xpaytoreload = 0;
var xpaytimer = setInterval(function(){
	if (typeof jQuery == 'undefined') return;
	var $ = jQuery;
	var t = $('#xpay-timer');
	if (t.length > 0) {
		++xpaytoreload;
		if (xpaytoreload > 120) {
			location.reload(true);
			clearInterval(xpaytimer);
			return;
		}
		var s = t.attr('data-seconds');
		--s;
		t.attr('data-seconds', s);
		if (s < 0) {
			t.html('EXPIRADO');
			location.reload(true);
			clearInterval(xpaytimer);
			return;
		}
		var mm = parseInt(s/60);
		var ss = s%60;
		if (mm < 10) mm = '0'+mm;
		if (ss < 10) ss = '0'+ss;
		t.html(mm+':'+ss);
	}
}, 950);