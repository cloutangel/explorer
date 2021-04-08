<h1>Transaction: {hash}</h1>
{tx}
  <ul>
    <li><b>Block height:</b> <a href="/block/{BlockHeight}">{BlockHeight}</a></li>
    <li><b>Block hash:</b> {BlockHashHex}</li>
    <li><b>Index in block:</b> {TxnIndexInBlock}</li>
    <li><b>Transaction type:</b> {TransactionType}</li>
    <li><b>Raw transaction hex:</b> <code>{RawTransactionHex}</code></li>
  </ul>
  {TransactionMetadata}
    <h2>Metadata</h2>
    <h3>Affected public keys</h3>
    <table>
      <thead>
        <th>Type</th>
        <th class="address">Address</th>
      </thead>
      <tbody>
        {AffectedPublicKeys}
          <tr>
            <td>{Metadata}</td>
            <td><a href="/address/{PublicKeyBase58Check}">{PublicKeyBase58Check}</a></td>
          </tr>
        {/AffectedPublicKeys}
      </tbody>
    </table>
    <h3>Basic transfer info</h3>
    {BasicTransferTxindexMetadata}
      <ul>
        <li><b>Fees:</b> {FeeNanos:amount} Bitclout</li>
        <li><b>Input value:</b> {TotalInputNanos:amount} Bitclout</li>
        <li><b>Output value:</b> {TotalOutputNanos:amount} Bitclout</li>
      </ul>
    {/BasicTransferTxindexMetadata}
    {CreatorCoinTxindexMetadata}
      <h3>Creator token info</h3>
      <ul>
        <li><b>Operation:</b> {OperationType}</li>
        <li><b>Amount to sell:</b> {BitCloutToSellNanos:amount} Bitcout</li>
        <li><b>Creator tokens to sell:</b> {CreatorCoinToSellNanos:amount} Token</li>
      </ul>
    {/CreatorCoinTxindexMetadata}
  {/TransactionMetadata}
  <h2>Inputs / Outputs</h2>
  {>_tx_io_list}
{/tx}
{err}
  <p>Cannot load transaction: {hash}}</p>
{/err}