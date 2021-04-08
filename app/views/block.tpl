<h1>Block #{height} info</h1>
{block}
  <ul>
    <li>
      <b>Block height:</b> {Header.Height}
    </li>
    <li>
      <b>Block hash:</b> {Header.BlockHashHex}
    </li>
    <li>
      <b>Prev block hash:</b> <a href="/?search={Header.PrevBlockHashHex}">{Header.PrevBlockHashHex}</a>
    </li>
    <li>
      <b>Transactions count:</b> {Transactions:count}
    </li>
    <li>
      <b>Time:</b> {Header.TstampSecs:datetime}
    </li>
  </ul>
  <h2>Transactions</h2>
  <ul>
    {Transactions}
      <li>
        <a href="/tx/{this}">{this}</a>
      </li>
    {/Transactions}
  </ul>
{/block}
{err}
  <p>Cannot load block #{height}</p>
{/err}