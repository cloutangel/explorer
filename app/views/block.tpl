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
    <table>
      <thead>
        <th class="index">Index</th>
        <th class="hash">Hash</th>
        <th>Type</th>
        <th>Fees</th>
      </thead>
      <tbody>
        {Transactions}
          <tr>
            <td>{TxnIndexInBlock}</td>
            <td><a href="/tx/{TransactionIDBase58Check}">{TransactionIDBase58Check}</a></td>
            <td>{TransactionType}</td>
            <td>{TransactionMetadata.BasicTransferTxindexMetadata.FeeNanos:amount}</td>
          </tr>
        {/Transactions}
      </tbody>
    </table>

  </ul>
{/block}
{err}
  <p>Cannot load block #{height}</p>
{/err}