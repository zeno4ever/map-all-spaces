<?php

$menu = $_GET['menu'];

?>
<nav class="menu">
	<ul>
		<li><a href="/?menu=home" class="<? if (strcmp($menu, 'home') == 0) {
												echo 'active';
											}; ?>">Home</a></li>
		<li><a href="/heatmap/?menu=heatmap" class="<? if (strcmp($menu, 'heatmap') == 0) {
														echo 'active';
													}; ?>">Heatmap</a></li>
		<li><a href="/faq.php?menu=faq" class="<? if (strcmp($menu, 'faq') == 0) {
													echo 'active';
												}; ?>">FAQ</a></li>
		<li><a href="/onespace.php?menu=onespace" class="<? if (strcmp($menu, 'onespace') == 0) {
																echo 'active';
															}; ?>">Status your space</a></li>
		<li><a href="/hswikilist.php?menu=hswikilist" class="<? if (strcmp($menu, 'hswikilist') == 0) {
																echo 'active';
															}; ?>">Hackerspace Census</a></li>
		<li><a href="/about.php?menu=about" class="<? if (strcmp($menu, 'about') == 0) {
														echo 'active';
													}; ?>">About</a></li>
		<li style="float:right"><a href="https://github.com/zeno4ever/map-all-spaces" target=_blank><img src="/image/github-white.png" alt="Join us on Github"></a></li>
	</ul>
</nav>