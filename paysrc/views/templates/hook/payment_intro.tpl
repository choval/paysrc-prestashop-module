<section style="border: 1px solid #999;margin-bottom:10px;border-radius:4px;padding:10px;background:#f9f9f9;position:relative;">
	<img src="/modules/paysrc/views/img/isologo.svg" height="20" alt="PaySrc" style="position:absolute;bottom:5px;right:5px;" />
	<p>
		{l s="You will receive our order confirmation by email containing address and amount to transfer."}
	</p>
	<p style="margin-bottom:0;line-height:30px;font-size:2rem;">
		<img src="/modules/paysrc/views/img/BCH.svg" style="height:30px; vertical-align:top;" alt="Bitcoin Cash" />
		<span class="paysrc-current-bch" data-usd="{$order_total_usd}" style="color:#222;">
			<img src="/modules/paysrc/views/img/loading.gif" alt="{l s="Loading"}" />
		</span>
		<small style="font-size:12px;">
			BCH
		</small>
	</p>
	<p style="font-size:10px;line-height:10px;margin:0;">
		* {l s="Amount will be fixed once the order is placed."}
	</p>
</section>

