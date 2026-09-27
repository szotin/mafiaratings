<?php

return array
(
	'round' =>
	'<h3>Description</h3>
		<p><code>round(num, precision)</code></p>
		<p>Returns the rounded value of num to specified precision (number of digits after the decimal point). precision can also be negative or zero (default).</p>
	<p><h3>Parameters</h3>
		<dl>
			<dt>num</dt><dd>The value to round. If missing, 0 is returned.</dd>
			<dt>precision</dt><dd>The optional number of decimal digits to round to.
				<p>If the precision is positive, num is rounded to precision significant digits after the decimal point.</p>
				<p>If the precision is negative, num is rounded to precision significant digits before the decimal point, i.e. to the nearest multiple of pow(10, -precision), e.g. for a precision of -1 num is rounded to tens, for a precision of -2 to hundreds, etc.</p></dd>
		</dl>
	<p><h3>Examples</h3><p>
		<code>round(1.4)</code> is 1<br> 
		<code>round(5.5)</code> is 6<br> 
		<code>round(3.1415926, 2)</code> is 3.14<br> 
		<code>round(587, -2)</code> is 600 </p>',

	'floor' =>
	'<h3>Description</h3>
		<p><code>floor(num)</code></p>
		<p>Returns the next lowest integer value by rounding down num if necessary.</p>
	<h3>Parameters</h3>
		<dl>
			<dt>num</dt><dd>The value to round. If missing, 0 is returned.</dd>
		</dl>
	<h3>Examples</h3>
		<p><code>floor(1.4)</code> is 1<br> 
		<code>floor(5.5)</code> is 5<br> 
		<code>floor(999.99999)</code> is 999<br>',

	'ceil' =>
	'<h3>Description</h3>
		<p><code>ceil(num)</code></p>
		<p>Returns the next highest integer value by rounding down num if necessary.</p>
	<h3>Parameters</h3>
		<dl>
			<dt>num</dt><dd>The value to round. If missing, 0 is returned.</dd>
		</dl>
	<h3>Examples</h3>
		<p><code>ceil(1.4)</code> is 1<br> 
		<code>ceil(5.5)</code> is 5<br> 
		<code>ceil(999.99999)</code> is 999<br>',

	'log' =>
	'<h3>Description</h3>
		<p><code>log(num, base)</code></p>
		<p>If the optional base parameter is specified, log() returns log<sub>base</sub>(num), otherwise log() returns the natural logarithm of num.</p>
	<h3>Parameters</h3>
		<dl>
			<dt>num</dt><dd>The value to calculate the logarithm for.</dd>
			<dt>base</dt><dd>The optional logarithmic base to use (defaults to \'e\' and so to the natural logarithm).</dd>
		</dl>
	<h3>Examples</h3>
		<p><code>log(1)</code> is '.log(1).'<br> 
		<code>log(4,2)</code> is '.log(4,2).'<br> 
		<code>log(1000,10)</code> is '.log(1000,10).'<br> 
		<code>log(0)</code> is '.EV_MIN_VALUE.' - this is the minimum value for the evaluator. It represents negative infinity.</p>',

	'min' =>
	'<h3>Description</h3>
		<p><code>min(value1, value2, ...)</code></p>
		<p>Returns the lowest value between value1, value2, value3, etc...</p>
	<h3>Parameters</h3>
		<dl>
			<dt>valueN</dt><dd>Value to participate in the race for the lowest.</dd>
		</dl>
	<h3>Examples</h3>
		<p><code>min(1)</code> is 1<br> 
		<code>min(7.01, 6.99)</code> is 6.99<br> 
		<code>min(1,2,3,4,5,6)</code> is 1<br> 
		<code>max(min(value(), 1), 0)</code> - guarantees that the result is between 0 and 1</p>',

	'max' =>
	'<h3>Description</h3>
		<p><code>max(value1, value2, ...)</code></p>
		<p>Returns the highest value between value1, value2, value3, etc...</p>
	<h3>Parameters</h3>
		<dl>
			<dt>valueN</dt><dd>Value to participate in the race for the highest.</dd>
		</dl>
	<h3>Examples</h3>
		<p><code>max(1)</code> is 1<br> 
		<code>max(7.01, 6.99)</code> is 7.01<br> 
		<code>max(1,2,3,4,5,6)</code> is 6<br> 
		<code>max(min(value(), 1), 0)</code> - guarantees that the result is between 0 and 1</p>',
		
	'counter' =>
	'<h3>Description</h3>
		<p><code>counter(type, number)</code></p>
		<p>Returns a value of counter for this player-game. Counter is an object created in the scoring system in the section Counters.<p>
	<h3>Parameters</h3>
		<dl>
			<dt>type</dt><dd>Defines how the value of counter is calculated.<br>0 - is for the final value of the counter calculated for all the games played.<br>1 - is for the value of counter calculated only for the games played before the current one (inclusive).</dd>
			<dt>number</dt><dd>The number of the counter in the order they appear in the scoring editor starting from 0.</dd>
		</dl>
	<h3>Examples</h3>
		<p>Suppose we created two counters for our scoring system. The first one (number 0) calculates number of games where a player was shot the first night while being red. The second one (number 1) calculates number of games a player played while being red.</p>
		<p>Then:<br>
		<code>counter(0,1)</code> returns the total number of games played while being red.<br> 
		<code>counter(1,1)</code> returns the number of games played before the current game (inclusive) while being red.<br> 
		<code>counter(1,1)-matter(8)</code> returns the number of games played before the current game (not inclusive) while being red.<br>
		<code>counter(0,0)</code> returns the total number of times a player has been shot while being red.<br>
		<code>counter(1,0) * 100 / counter(1,1)</code> returns the total percentage of first night kills while being red.</p>',

	'bonus' =>
	'<h3>Description</h3>
		<p><code>bonus()</code></p>
		<p>Returns the bonus points assigned to the player in the game by referee.</p>
	<h3>Examples</h3>
		<code>bonus()/2</code> bonus multiplied by two.<br> 
		<code>bonus()*difficulty()</code> Bonus weighted by game difficulty. See the function difficulty.</p',

	'role' =>
	'<h3>Description</h3>
		<p><code>role()</code></p>
		<p>Returns the role of the player in the game. The values are:<br>
			<ol start="0">
				<li>town</li>
				<li>sheriff</li>
				<li>mafia</li>
				<li>don</li>
			</ol>
		</p>
	<h3>Examples</h3>
		<code>role() < 2 ? bonus() : 0</code> bonus if player is red, 0 otherwise. See the function bonus.<br> 
		<code>role() == 1 || role() == 3 ? 1 : difficulty()*2</code> For special roles 2, for others a value between 0 and 2 depending on game difficulty. See the function difficulty.</p',

	'difficulty' =>
	'<h3>Description</h3>
		<p><code>difficulty()</code></p>
		<p>Returns the difficulty coefficient of the game. A value between 0 and 1. The total number of games won by the opposite team divided by the total number of games. If the player is red, this is the number of black wins divided by the number of games. If the player is black, it is opposite - number of red wins divided by the number of games.</p>
	<h3>Examples</h3>
		<code>difficulty() * 2</code> a number between 0 and 2 depending on the difficulty.</p',
		
	'matter' =>
	'<h3>Description</h3>
		<p><code>matter(type)</code></p>
		<p>Returns 0 or 1 depending on if some event happened in the game.</p>
	<h3>Parameters</h3>
		<dl>
			<dt>type</dt><dd>The code of the happened event. Codes:<ol start="0">
				<li>The player played the game</li>
				<li>The player won</li>
				<li>The player lost</li>
				<li>All players killed in a daytime were from another team</li>
				<li>All players killed in a daytime were from the player\'s team</li>
				<li>Got the best player status from the referee</li>
				<li>Got the best move status from the referee</li>
				<li>Survived in the game</li>
				<li>Killed in the first night</li>
				<li>Killed in the night</li>
				<li>Guessed 3 mafia after being killed first night</li>
				<li>Guessed 2 mafia after being killed first night</li>
				<li>Killed by warnings</li>
				<li>Kicked out</li>
				<li>Surrendered</li>
				<li>All votes vs mafia (>3 votings)</li>
				<li>All votes vs civs (>3 votings)</li>
				<li>Sheriff was killed the next day after finding by don</li>
				<li>Sheriff was found first night</li>
				<li>Sheriff was killed the first night</li>
				<li>Sheriff did three black checks in a row</li>
				<li>All sheriff checks were red</li>
				<li>Bonus was assigned to the player by the referee</li>
				<li>Guessed 1 mafia after being killed first night</li>
				<li>Got the worst move status from the referee (aka auto-bonus)</li>
				<li>Team kicked out (opposite team wins)</li>
				<li>Tie</li>
			</ol></dd>
		</dl>
	<h3>Examples</h3>
		<p><code>matter(8) && matter(2) ? 0.2 : 0</code> 0.2 if the player was killed the first night and their team lost.<br> 
		<code>matter(8) && !matter(23) && !matter(10) && !matter(11) ? -0.4 : 0</code> -0.4 if the player was killed the first night and left no black players in the legacy.<br> 
		<code>matter(12) || matter(13) ? -0.5 : 0</code> -0.5 if the player got 4 warnings, or was mod-killed.</p>',

	'table' =>
	'<h3>Description</h3>
		<p><code>table(index1, index2, ...)</code></p>
		<p>Returns a number from the Table of the gaining system.</p>
		<p>The table is nested as deeply as you make it: a flat list of numbers takes one index, a list of lists takes two, and so on. Indices start at 0. An index outside the table is moved to the nearest one that exists, so the first row answers for anything below it and the last row for anything above it. If you pass fewer indices than the table has levels, the first element of each remaining level is taken.</p>
	<h3>Parameters</h3>
		<dl>
			<dt>indexN</dt><dd>Position on the Nth level of the table, counted from 0. A fractional index is rounded down.</dd>
		</dl>
	<h3>Examples</h3>
		<p>Suppose the table is a list of rows, one row per star, and each row holds the points by place:</p>
		<p><code>table(stars-1, place-1)</code> the points for this place in a competition of this many stars. Both are shifted by one because places and stars are counted from 1 while indices are counted from 0.<br>
		<code>table(stars-1)</code> the first number of the row for these stars.<br>
		<code>table(0, stars-1, numPlayers/10-1, place-1)</code> a four level table, the first level choosing between two sets of rows - for instance one for tournaments and one for subseries.</p>',

	'score' =>
	'<h3>Description</h3>
		<p><code>score()</code></p>
		<p>Returns the points the player scored inside the competition, by its own scoring system - the sum of their main, bonus and first night points in a tournament, or their place points in a subseries. This is the competition result the player brings with them; the gaining system turns it into series points.</p>
	<h3>Examples</h3>
		<p><code>score()</code> the series gets exactly what the competition gave. Often used as the formula for a subseries.<br>
		<code>score() / 2</code> half of it.<br>
		<code>place = 1 ? score() * 1.5 : score()</code> the winner keeps one and a half of their result.</p>',

	'numPlayers' =>
	'<h3>Description</h3>
		<p><code>numPlayers()</code></p>
		<p>Returns the number of players who took part in the competition.</p>
	<h3>Examples</h3>
		<p><code>(numPlayers() - place) / (numPlayers() - 1)</code> 1 for the winner, 0 for the last place, and evenly spread in between whatever the size of the competition.<br>
		<code>numPlayers() / 10 - 1</code> used as a table index to pick a row by the size of the competition, where every row covers ten players.</p>',

	'stars' =>
	'<h3>Description</h3>
		<p><code>stars()</code></p>
		<p>Returns the number of stars this competition was given in the series. Stars are how a series says how much a competition weighs - a minor tournament and the main one of the year are both tournaments, and the stars are what tells them apart.</p>
	<h3>Examples</h3>
		<p><code>stars()</code> one point per star.<br>
		<code>table(stars()-1, place-1)</code> a row of the table per star.<br>
		<code>stars() > 0 && stars() < 6 ? stars() : 0</code> nothing at all outside the range of 1 to 5 stars.</p>',

	'ratingSum' =>
	'<h3>Description</h3>
		<p><code>ratingSum()</code></p>
		<p>Returns the sum of the ratings of all the players accepted to the competition, as their ratings were when it was counted. It says how strong the field was: the same place among strong players is worth more than among weak ones.</p>
	<h3>Examples</h3>
		<p><code>ratingSum() / numPlayers()</code> the average rating of the field.</p>',

	'ratingSum20' =>
	'<h3>Description</h3>
		<p><code>ratingSum20()</code></p>
		<p>Returns the sum of the ratings of the twenty highest rated players of the competition, or of all of them when there were fewer than twenty. Unlike ratingSum this does not keep growing with the size of the competition, so it compares a big tournament with a small one on the strength of the field rather than on its size.</p>
	<h3>Examples</h3>
		<p><code>ratingSum20() / (numPlayers() > 20 ? 20 : numPlayers())</code> the average rating of the twenty strongest players.<br>
		<code>10 + ratingSum20() / (numPlayers() > 20 ? 20 : numPlayers()) * stars() / 100</code> the points for the first place, grown by the strength of the field and by the stars.</p>',

	'travelingDistance' =>
	'<h3>Description</h3>
		<p><code>travelingDistance()</code></p>
		<p>Returns the sum, over all the players of the competition, of the distance in miles from the city of the player to the city of the competition. It says how far the players came, which is one way of saying how much the competition mattered to them.</p>
	<h3>Examples</h3>
		<p><code>travelingDistance() / numPlayers()</code> how far an average player came.</p>',

	'guestCoef' =>
	'<h3>Description</h3>
		<p><code>guestCoef()</code></p>
		<p>Returns the guest coefficient of the competition - the sum, over all its players, of <code>log(1 + distance/600, 2)</code>, where distance is how far in miles that player came. It counts the same thing as travelingDistance but with diminishing returns, so that one player from the other side of the world does not outweigh a hall full of guests from the next city.</p>
	<h3>Examples</h3>
		<p><code>guestCoef()</code> a competition where everybody is local is 0.<br>
		<code>min(guestCoef(), 10)</code> the same, but nothing above 10.</p>',

	'place' =>
	'<h3>Description</h3>
		<p><code>place</code></p>
		<p>The place the player took in the competition, counted from 1. Unlike the other names here it is a plain value rather than a function, so it is written without brackets.</p>
	<h3>Examples</h3>
		<p><code>table(stars-1, place-1)</code> the points for this place, taken from the table. One is subtracted because table indices are counted from 0.<br>
		<code>place = 1 ? 10 : 0</code> ten points for the winner and nothing for everybody else.<br>
		<code>((numPlayers - place) / (numPlayers - 1))^3</code> 1 for the winner falling steeply to 0 for the last place.</p>',
);

?>