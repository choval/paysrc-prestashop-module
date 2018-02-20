<div class="panel">
	<div class="panel-heading">
		{l s="Instructions"}
	</div>
	{if empty($usd_currency_id)}
	<h2>
		{l s="Activate US Dollar (USD) currency"}
	</h2>
	<p>
		{l s="The BCH:USD price is used to calculate the amount of BCH."}
	</p>
	<ol style="font-size:1rem;">
		<li style="margin: 15px 0;">
			{l s="Go to International - Localization - Currencies"}
		</li>
		<li style="margin: 15px 0;">
			{l s="Add US Dollar"}
			<br />
			<small>
				{l s="The currency doesn't need to be enabled."}
			</small>
		</li>
		<li style="margin: 15px 0;">
			{l s="Enable live exchange rates"}
			<br />
			<small>
				{l s="Or make sure the exchange rate is updated."}
			</small>
		</li>
	</ol>
	{/if}
	{if empty($profile_name)}
	<h2>
		{l s="Create a PaySrc account"}
	</h2>
	<ol style="font-size:1rem;">
		<li style="margin: 15px 0;">
			{l s="Follow this link and register an account."}
			<br />
			<a href="https://paysrc.com/dashboard/register" target="paysrc">
				paysrc.com/dashboard/register
			</a>
		</li>
		<li style="margin: 15px 0;">
			{l s="Verify your account by clicking on the link sent to the registered email."}
		</li>
		<li style="margin: 15px 0;">
			{l s="Create an application token."}
			<br />
			<a href="https://paysrc.com/dashboard#account/applications" target="paysrc">
				paysrc.com/dashboard#account/applications
			</a>
		</li>
		<li style="margin: 15px 0;">
			{l s="Copy the token of the application and paste in the form below."}
		</li>
	</ol>
	{/if}

</div>

