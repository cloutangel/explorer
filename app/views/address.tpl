<h1>Address: {address}</h1>
{info}
  <grid>
    <cell span="10">
      <ul>
        <li><b>Balance:</b> {balance:amount} Bitclout</li>
        {user.username}
          <li><b>Username:</b> <a href="https://bitclout.com/u/{user.username}" target="_blank" rel="noopener noreferrer">{user.username}</a></li>
        {/user.username}
        <li><b>Transactions:</b> {tx_count}</li>
      </ul>
    </cell span="10">
    {user.avatar}
      <cell span="2">
        <a href="https://bitclout.com/u/{user.username}" target="_blank" rel="noopener noreferrer">
          <img src="{user.avatar}" alt="{username} on Bitclout"/>
        </a>
      </cell>
    {/user.avatar}
  </grid>
  <h2>Transactions</h2>
  {>_tx_list Transactions=txs}
{/info}
{err}
  <p>Cannot load address info: {address}</p>
{/err}