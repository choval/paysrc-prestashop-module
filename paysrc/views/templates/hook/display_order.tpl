<div class="box">
	<h4>
		{l s="PaySrc payment details"}
	</h4>
	<p>
		<small>{l s="Status"}</small>
		<br />
		{$status}
	</p>
	<p>
		<small>{l s="Amount"}</small>
		<br />
		{$amount} {$coin}
	<p>
	<p>
		<small>{l s="Updated"}</small>
		<br />
		{$updated} GMT
	</p>
	<p style="margin:0;">
		<a href="{$root_uri}/p/{$payment}">
			<small>{l s="Click here for more information"}</small>
		</a>
	</p>
</div>

