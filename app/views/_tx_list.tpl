<table>
  <thead>
    <th class="index" title="Block height">Block</th>
    <th class="index" title="Index in block">Index</th>
    <th class="tx-hash">Hash</th>
    <th>Type</th>
    <th>Fees</th>
  </thead>
  <tbody>
    {Transactions}
      <tr>
        {IsMempool:}<td>mempool</td>
        {!IsMempool:}<td><a href="/block/{BlockHeight}">{BlockHeight}</a></td>
        <td>{TxnIndexInBlock}</td>
        <td><a href="/tx/{TransactionIDBase58Check}">{TransactionIDBase58Check}</a></td>
        <td>{TransactionType}</td>
        <td>{TransactionMetadata.BasicTransferTxindexMetadata.FeeNanos:amount}</td>
      </tr>
    {/Transactions}
  </tbody>
</table>