<div class="search-hint">Looking for something special?</div>
<form role="search" class="search-form" method="get" action="<?php echo home_url('/') ?>">
  <label>
    <input type="text" class="search-input" placeholder="Search products..." value="<?php echo get_search_query() ?>" name="s"/>
  </label>
  <button type="submit" class="search-button">Search</button>
</form>