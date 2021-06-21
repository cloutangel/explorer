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
    {BasicTransferTxindexMetadata}
      <h3>Basic transfer info</h3>
      <ul>
        <li><b>Fees:</b> {FeeNanos:amount} Bitclout</li>
        <li><b>Input value:</b> {TotalInputNanos:amount} Bitclout</li>
        <li><b>Output value:</b> {TotalOutputNanos:amount} Bitclout</li>
      </ul>
    {/BasicTransferTxindexMetadata}
    {BitcoinExchangeTxindexMetadata}
      <h3>Bitcoin exchange info</h3>
      <ul>
        <li><b>Bitcoin tx:</b> {BitcoinTxnHash}</li>
        <li><b>Address:</b> {BitcoinSpendAddress}</li>
        <li><b>Burned:</b> {SatoshisBurned:btc} BTC</li>
        <li><b>Credited:</b> {NanosCreated:amount} Bitclout</li>
        <li><b>Before:</b> {TotalNanosPurchasedBefore:amount} Bitclout</li>
        <li><b>After:</b> {TotalNanosPurchasedAfter:amount} Bitclout</li>
      </ul>
    {/BitcoinExchangeTxindexMetadata}
    {CreatorCoinTxindexMetadata}
      <h3>Creator token info</h3>
      <ul>
        <li><b>Operation:</b> {OperationType}</li>
        <li><b>Amount to sell:</b> {BitCloutToSellNanos:amount} Bitclout</li>
        <li><b>Creator tokens to sell:</b> {CreatorCoinToSellNanos:amount} Token</li>
      </ul>
    {/CreatorCoinTxindexMetadata}
    {UpdateProfileTxindexMetadata}
      <h3>Update profile info</h3>
      <ul>
        <li><b>Username:</b> {NewUsername}</li>
        <li><b>Description:</b> {NewDescription}</li>
        <li><b>Avatar:</b> <img src="{NewProfilePic}" width="64" height="64" alt="{NewUsername}"/></li>
        <li><b>Founder reward:</b> {NewCreatorBasisPoints:points_to_percent}</li>
      </ul>
    {/UpdateProfileTxindexMetadata}
    {FollowTxindexMetadata}
      <h3>Follow info</h3>
      <ul>
        <li><b>Type:</b> {IsUnfollow}unfollow{/IsUnfollow}{!IsUnfollow}follow{/!IsUnfollow}</li>
      </ul>
    {/FollowTxindexMetadata}
  {/TransactionMetadata}
  <h2>Inputs / Outputs</h2>
  {>_tx_io_list}
{/tx}
{err}
  <p>Cannot load transaction: {hash}</p>
{/err}