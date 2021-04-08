<grid class="tx-io-list">
  <cell span="6" m-span="12">
    <table>
      <thead>
        <th class="index">Index</th>
        <th>UTXO</th>
      </thead>
      <tbody>
        {Inputs}
          <tr class="row">
            <td>{Index}</td>
            <td>
              <a href="/tx/{TransactionIDBase58Check}">{TransactionIDBase58Check}</a>
            </td>
          </tr>
        {/Inputs}
      </tbody>
    </table>
    {!Inputs}
      <p>No inputs</p>      
    {/!Inputs}
  </cell>
  <cell span="6" m-span="12">
    <table>
      <thead>
        <th>Amount</th>
        <th class="address">Address</th>
      </thead>
      <tbody>
        {Outputs}
          <tr>
            <td>+{AmountNanos:amount} Bitclout</td>
            <td>
              <a href="/address/{PublicKeyBase58Check}">{PublicKeyBase58Check}</a>
            </td>
          </tr>
        {/Outputs}
      </tbody>
    </table>
    {!Outputs}
      <p>No Outputs</p>      
    {/!Outputs}
  </cell>
</grid>