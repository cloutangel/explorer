<h1>Latest blocks</h1>
<table>
  <thead>
    <th>Height</th>
    <th class="hash">Hash</th>
    <th>Transactions</th>
    <th class="datetime">Time</th>
  </thead>
  <tbody>
    {blocks}
      <tr>
        <td><a href="/block/{Header.Height}">{Header.Height}</a></td>
        <td>{Header.BlockHashHex}</td>
        <td>{Transactions:count}</td>
        <td>{Header.TstampSecs:datetime}</td>
      </tr>
    {/blocks}
  </tbody>
</table>