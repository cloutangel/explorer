{pagination}
  <div class="pagination">
    <ul>
      <li><a href="{url}?p={first}">First</a></li>
      {no_first:} <li>…</li>
      {pages:} <li><a href="{url}?p={this}">{this}</a></li>
      {no_last:} <li>…</li>
      <li><a href="{url}?p={last}">Last</a></li>
    </ul>
  </div>
{/pagination}