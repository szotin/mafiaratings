function randomInt(max)
{
     return Math.floor(Math.random() * (max - 1));
}

class Seating
{
	constructor(playersCount, tablesCount, gpp)
	{
		while (!Seating.areParamsValid(playersCount, tablesCount, gpp))
		{
			++gpp;
		}
		
		this.playersCount = playersCount;
		this.tablesCount = tablesCount;
		this.gpp = gpp;
		
		this.rounds = null;
		this.players = null;
		this.distr = null;
		
		this.running = false;
	}
	
	static areParamsValid(playersCount, tablesCount, gpp)
	{
		return tablesCount * 10 <= playersCount && (playersCount * gpp) % 10 == 0;
	}

	static getRoundsCount(playersCount, tablesCount, gpp)
	{
		tablesCount *= 10;
		return Math.floor((playersCount * gpp + tablesCount - 1) / tablesCount);
	}
	
    _getPlayerSkippingScore(p, r, rounds, players)
    {
        var score = 0;
        var skippedRounds = players[p];
        var round = rounds[r]
        for (var s = 0; s < skippedRounds.length; ++s)
        {
            var roundPlayers = rounds[skippedRounds[s]];
            for (var r1 = 0; r1 < roundPlayers.length; ++r1)
            {
                var p1 = roundPlayers[r1];
                if (p1 != p && round.includes(p1))
                {
                    ++score;
                }
            }
        }
        return score;
    }
    
    _findBestPlayerToSkip(r, rounds, players)
    {
        var currentScore = Number.MAX_SAFE_INTEGER;
        var currentPlayer = this.playersCount - 1;
        var round = rounds[r];
        for (var p = currentPlayer; p >= 0; --p)
        {
            var playerSkips = players[p].length;
            if (!round.includes(p) && playerSkips < this.rounds.length - this.gpp)
            {
                var score = this._getPlayerSkippingScore(p, r, rounds, players);
                
                if (score == 0 && playerSkips == 0)
                {
					return p;
                }

                if (score < currentScore || (score == currentScore && playerSkips < players[currentPlayer].length))
                {
                    currentScore = score;
                    currentPlayer = p;
                }
            }
        }
		return currentPlayer;
    }
    
    _generateSkipping()
    {
        var players = [];
        for (var i = 0; i < this.playersCount; ++i)
        {
            players.push([]);
        }
        
        var rounds = [];
        for (var r = 0; r < this.rounds.length; ++r)
        {
            var skipsCount = this.playersCount - this.rounds[r].length * 10;
            rounds.push([]);
            while (skipsCount-- > 0)
            {
                var p = this._findBestPlayerToSkip(r, rounds, players);
                rounds[r].push(p);
                players[p].push(r);
            }
        }
        return players;
    }
    
    _calculateTableScore(currentR, currentP, currentT)
    {
        var score = 0;
        var currentTable = this.rounds[currentR][currentT];
        for (var r = 0; r < currentR; ++r)
        {
            var round = this.rounds[r];
            for (var t = 0; t < round.length; ++t)
            {
                var table = round[t];
                if (table.includes(currentP))
                {
                    if (t == currentT)
                    {
                        ++score;
                    }
                    
                    for (var n = 0; n < table.length; ++n)
                    {
                        var p = table[n];
                        if (p != currentP && currentTable.includes(p))
                        {
                            score += this.rounds.length;
                        }
                    }
                    break;
                }
            }
        }
        return score;
    }
    
    _findPlayerTable(r, p)
    {
        var bestTable = -1;
        var bestScore = Number.MAX_SAFE_INTEGER;
        var round = this.rounds[r];
        for (var t = 0; t < round.length; ++t)
        {
            if (round[t].length < 10)
            {
                var score = this._calculateTableScore(r, p, t);
                if (score < bestScore)
                {
                    bestScore = score;
                    bestTable = t;
                }
            }
        }
        return bestTable;
    }
    
    _generateTables(skipping)
    {
        for (var r = 0; r < this.rounds.length; ++r)
        {
            var round = this.rounds[r];
            for (var p = 0; p < this.playersCount; ++p)
            {
                if (!skipping[p].includes(r))
                {
					var t = this._findPlayerTable(r, p);
					if (t >= 0)
					{
						round[t].push(p);
					}
                }
            }
        }
    }
	
	_calculateFreq(p1, p2)
    {
        var freq = 0;
        var player1 = this.players[p1];
        var player2 = this.players[p2];
        for (var r = 0; r < this.rounds.length; ++r)
        {
            var t1 = player1[r];
            var t2 = player2[r];
            if (t1 >= 0 && t2 >= 0 && Math.floor(t1/10) == Math.floor(t2/10))
            {
                ++freq;
            }
        }
        return freq;
    }
	
	_calculateDistribution()
    {
        var distr = [];
        for (var i = 0; i <= this.gpp; ++i)
        {
            distr.push(0);
        }
        for (var p1 = 0; p1 < this.playersCount; ++p1)
        {
            for (var p2 = 0; p2 < p1; ++p2)
            {
                var freq = this._calculateFreq(p1, p2);
                ++distr[freq];
            }
        }
		return distr;
    }	
	
	_roundsToPlayers()
	{
		var players;
		if (this.players == null || this.players.length != this.playersCount || this.players[0].length != this.rounds.length)
		{
			var players = []
			for (var p = 0; p < this.playersCount; ++p)
			{
				var rounds = new Array(this.rounds.length);
				rounds.fill(-1);
				players.push(rounds);
			}
		}
		else
		{
			players = this.players;
		}
		
		for (var r = 0; r < this.rounds.length; ++r)
		{
			var tables = this.rounds[r];
			for (var t = 0; t < tables.length; ++t)
			{
				var table = tables[t];
				for (var n = 0; n < 10; ++n)
				{
					players[table[n]][r] = t * 10 + n;
				}
			}
		}
		
		this.players = players;
		this.distr = this._calculateDistribution();
	}
	
	_playersToRounds()
	{
		for (var p = 0; p < this.playersCount; ++p)
		{
			var player = this.players[p];
			for (var r = 0; r < player.length; ++r)
			{
				var playerRound = player[r];
				var n = playerRound % 10;
				var t = (playerRound - n) / 10;
				this.rounds[r][t][n] = p;
			}
		}
	}
	
	// _isDistrBetter(distr)
	// {
		// var minPairFreq = Math.floor((distr.length * 9) / this.playersCount);
		// var maxPairFreq = minPairFreq + 3;
		
		// var min = 0;
		// while (min < distr.length && distr[min] == 0 && this.distr[min] == 0)
		// {
			// ++min;
		// }
		
		// var max = distr.length - 1;
		// while (max > 0 && distr[max] == 0 && this.distr[max] == 0)
		// {
			// --max;
		// }
		
		// while (min < minPairFreq || max > maxPairFreq)
		// {
			// if (distr[max] > this.distr[max] || distr[min] > this.distr[min])
			// {
				// return false;
			// }

			// if (distr[max] < this.distr[max] || distr[min] < this.distr[min])
			// {
				// return true;
			// }

			// if (min < minPairFreq)
			// {
				// ++min;
			// }

			// if (max > minPairFreq)
			// {
				// --max;
			// }
		// }

		// while (min < max)
		// {
			// var f1 = distr[max] + distr[min];
			// var f2 = this.distr[max] + this.distr[min];
			// if (f1 != f2)
			// {
				// return f1 < f2;
			// }

			// ++min;
			// --max;
		// }
		// return false;
	// }
	
	_isDistrBetter(distr)
	{
		var min = 0;
		while (min < distr.length && distr[min] == 0 && this.distr[min] == 0)
		{
			++min;
		}
		
		var max = distr.length - 1;
		while (max > 0 && distr[max] == 0 && this.distr[max] == 0)
		{
			--max;
		}
		
		var dmin1 = distr[min];
		if (dmin1 == 0)
		{
			return true;
		}
		
		var dmin2 = this.distr[min];
		if (dmin2 == 0)
		{
			return false;
		}
		
		var dmax1 = distr[max];
		if (dmax1 == 0)
		{
			return true;
		}
		
		var dmax2 = this.distr[max];
		if (dmax2 == 0)
		{
			return false;
		}
		
		if (dmin1 + dmax1 < dmin2 + dmax2)
		{
			return true;
		}
		
		if (dmin1 + dmax1 > dmin2 + dmax2)
		{
			return false;
		}
		return dmin1 < dmin2;
	}
	
	_trySwapPlayers(r, p1, p2)
    {
        var pr1 = this.players[p1][r];
        var pr2 = this.players[p2][r];
        if (pr1 >= 0 && pr2 >= 0 && Math.abs(pr1 - pr2) >= 10)
        {
            this.players[p1][r] = pr2;
            this.players[p2][r] = pr1;
            
            var distr = this._calculateDistribution();
            if (this._isDistrBetter(distr))
            {
				this.distr = distr;
				return true;
            }
            
            this.players[p1][r] = pr1;
            this.players[p2][r] = pr2;
        }
        return false;
    }
	
    _optimizeDistrStep(onProgress, onComplete)
    {
        for (var r = 0; r < this.rounds.length && this.running; ++r)
        {
            if (this.rounds[r].length < 2)
            {
                break;
            }
            
            for (var p1 = 0; p1 < this.playersCount && this.running; ++p1)
            {
                for (var p2 = 0; p2 < p1 && this.running; ++p2)
                {
                    if (this._trySwapPlayers(r, p1, p2))
                    {
						onProgress(this.distr);
						setTimeout(function(s) { s._optimizeDistrStep(onProgress, onComplete); }, 0, this);
                        return;
                    }
                }
            }
        }
		this._playersToRounds();
        onComplete(this.running);
		this.running = false;
    }
	
	optimizeDistr(onProgress, onComplete)
	{
		this.running = true;
		onProgress(this.distr);
		setTimeout(function(s) { s._optimizeDistrStep(onProgress, onComplete); }, 0, this);
	}
	
	stop()
	{
		this.running = false;
	}
	
	_shuffleNumbers()
	{
		for (var r = 0; r < this.rounds.length; ++r)
		{
			var round = this.rounds[r];
			for (var t = 0; t < round.length; ++t)
			{
				var table = round[t];
				for (var n = 0; n < 10; ++n)
				{
					var n1;
					do
					{
						n1 = randomInt(10);
					} while (n1 == n);
					var p = table[n];
					table[n] = table[n1];
					table[n1] = p;
				}
			}
		}
	}
	
	generate(playersCount, tablesCount, gpp)
	{
		if (!Seating.areParamsValid(playersCount, tablesCount, gpp))
		{
			return false;
		}
		
		this.playersCount = playersCount;
		this.tablesCount = tablesCount;
		this.gpp = gpp;
		
		var tables;
		var rounds = [];
		
		var roundsCount = Seating.getRoundsCount(playersCount, tablesCount, gpp);
		
		for (var i = 1; i < roundsCount; ++i)
		{
			tables = [];
			for (var j = 0; j < tablesCount; ++j)
			{
				tables.push([]);
			}
			rounds.push(tables);
		}
		
		var tablesInLastRound = playersCount * gpp / 10 - (roundsCount - 1) * tablesCount;
		tables = [];
		for (var j = 0; j < tablesInLastRound; ++j)
		{
			tables.push([]);
		}
		
		rounds.push(tables);
		this.rounds = rounds;

		this._generateTables(this._generateSkipping());
		//this._shuffleNumbers();
		this._roundsToPlayers();
		return true;
	}
}

var seating = new Seating(30, 3, 10);
//var seating = new Seating(20, 2, 5);