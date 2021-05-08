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
      <b>Prev block hash:</b> <a href="/block/{Header.PrevBlockHashHex}">{Header.PrevBlockHashHex}</a>
    </li>
    <li>
      <b>Transactions count:</b> {TransactionCount}
    </li>
    <li>
      <b>Time:</b> {Header.TstampSecs:datetime}
    </li>
  </ul>
  <h2>Transactions (page: {global.p} out of {global.last})</h2>
  {>_tx_list}
  {>_pagination pagination=global.pagination}
{/block}
{err}
  <p>Cannot load block #{height}</p>
{/err}