function PaysrcUpdateValues() {
	$('.paysrc-current-bch').each(function() {
		if( !$(this).data('paysrc-original-bch') ) {
			var current = $(this).html();
			$(this).data('paysrc-original-bch',current);
		} else {
			$(this).html( $(this).data('paysrc-original-bch') );
		}
	});
	if( $('.paysrc-current-bch[data-usd]').length ) {
		$.getJSON('https://api.paysrc.com/v1/price/latest').then(function(res) {
			$('.paysrc-current-bch').each(function() {
				var usd = $(this).attr('data-usd');
				var total = usd / res.price_averages.USD;
				$(this).html(total.toFixed(8));
			});
		},function() {
			$('.paysrc-current-bch').each(function() {
				$(this).html('NETWORK FAIL');
			});
		});
	}
}
$(document).ready(function() {
	PaysrcUpdateValues();
});
