{if in_array($status,['NEW','PENDING'])}
	<div class="row">
		<div class="col-md-6 col-lg-7">
			<div style="padding:20px;">
				<h4>
					{l s="Instructions"}
				</h4>
				<br />
				<ol style="padding-left:1rem;">
					<li style="margin-bottom:10px;">
						{l s='Scan the QR code with your Bitcoin Cash wallet app.'}
						<br />
						<small>
							{l s='Need to use the legacy address or copy it?'}
							<a href="{$root_uri}/p/{$payment}">
								{l s="Click here."}
							</a>
						</small>
					</li>
					<li style="margin-bottom:10px;">
						{l s='Transfer the requested amount of BCH to the address.'}
						<br />
						<small>
							{l s='Do not fund from an exchange, use a regular wallet.'}
						</small>
					</li>
					<li style="margin-bottom:10px;">
						{l s='Your order will be sent once the requested amount is funded.'}
						<br />
						<small>
							{l s='You can fund the address from multiple sources.'}
						</small>
					</li>
				</ol>
				<p>
					{l s='A copy has been sent to your inbox, verify the order reference.'}
				</p>
				<br />
				<h4>
					{l s='Payment'}
				</h4>
				<p>
					<small>Address</small>
					<br />
					{$address}
				</p>
				<p>
					<small>Amount</small>
					<br />
					{$amount} {$coin}
				<p>
				<p>
					<small>Expires</small>
					<br />
					{$expires}
				</p>
				<br />
				<p>
				  {l s='If you have questions, comments or concerns, please contact our [1]expert customer support team[/1].' d='Modules.Wirepayment.Shop' sprintf=['[1]' => "<a href='{$contact_url}'>", '[/1]' => '</a>']}
				</p>
			</div>
		</div>
		<div class="col-md-6 col-lg-5">
			<div style="padding:20px;">
				<p>
					<a href="{$address}">
						<img src="{$api_uri}/payment/qr/{$payment}" alt="" width="100%" style="border: 5px dashed #ccc;box-shadow: 0 0 10px rgba(0,0,0,0.5);box-sizing:border-box;" />
					</a>
				</p>
				<p style="font-size:0.8rem;text-align:center;">
					<a href="{$root_uri}/p/{$payment}">
						Open detailed payment request
					</a>
				</p>
			</div>
		</div>
	</div>
	<p style="text-align:right;margin:0;">
		{l s="Powered by"}
		<a href="https://paysrc.com/" target="paysrc">
			<img src="/modules/paysrc/views/img/isologo.svg" alt="" height="20" style="position:relative;top:-2px;" />
		</a>
	</p>
{else if $status == 'PAID' || $status == 'CREDITED'}
	<div class="row">
		wazza
	</div>
{else}
	{$status}
    <p class="warning">
      {l s='We noticed a problem with your order. If you think this is an error, feel free to contact our [1]expert customer support team[/1].' d='Modules.Wirepayment.Shop' sprintf=['[1]' => "<a href='{$contact_url}'>", '[/1]' => '</a>']}
    </p>
{/if}
