using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;
using System.Threading;
using System.Threading.Tasks;
using System.Diagnostics;

namespace TournamentSeating
{
    class SeatingCalculator
    {
        private int m_players;
        private int m_games;
        private int m_tables;
        private int m_tablesInLastRound;
        private int m_rounds;
        private int m_minPlaceholders;
        private int m_maxPlaceholders;

        private int m_averagePairFreq;

        private ISeatingCalculatorListener m_listener;
        private bool m_running;

        private int[,] m_seatings = null;
        private int[,] m_finalSeatings = null;
        private int[] m_pairFreqs = null;
        private int[] m_intermediatePairFreqs = null;
        private int[] m_finalPairFreqs = null;

        Random m_rnd = new Random(0); // (int)DateTimeOffset.Now.ToUnixTimeSeconds());

        public SeatingCalculator(int a_players, int a_games, int a_tables, ISeatingCalculatorListener a_listener)
        {
            m_players = a_players;
            m_games = a_games;
            m_tables = a_tables;
            m_listener = a_listener;
            resetValues();
        }

        private void resetValues()
        {
            m_seatings = null;
            int seats_per_round = 10 * m_tables;
            m_tablesInLastRound = m_tables;
            int totalGames;
            if (seats_per_round >= m_players)
            {
                m_rounds = m_games;
                totalGames = m_rounds * m_tables;
                m_minPlaceholders = (seats_per_round - m_players) / m_tables;
                m_maxPlaceholders = (seats_per_round - m_players + m_tables - 1) / m_tables;
            }
            else
            {
                m_rounds = ((m_players * m_games + seats_per_round - 1) / (10 * m_tables));
                totalGames = m_rounds * m_tables;
                int totalPlaceholders = totalGames * 10 - m_players * m_games;
                while (totalPlaceholders >= 10 && m_tablesInLastRound > 1)
                {
                    totalPlaceholders -= 10;
                    --totalGames;
                    --m_tablesInLastRound;
                }

                m_minPlaceholders = totalPlaceholders / totalGames;
                m_maxPlaceholders = (totalPlaceholders + totalGames - 1) / totalGames;
            }

            // The formula of average pair frequency is: m_games * (m_games * m_players - totalGames) / (m_players * totalGames)
            // All we do here is rounding.
            m_averagePairFreq = m_games * (m_games * m_players - totalGames) * 2 / (m_players * totalGames);
            m_averagePairFreq = (m_averagePairFreq + 1) / 2;
        }

        public int PlayerCount 
        {
            get { return m_players; }
            set
            {
                if (value < 2)
                {
                    m_players = 10;
                }
                else
                {
                    m_players = value;
                }
                resetValues();
            }
        }

        public int GamesPerPlayer
        {
            get { return m_games; }
            set
            {
                if (value < 1)
                {
                    m_games = 1;
                }
                else
                {
                    m_games = value;
                }
                resetValues();
            }
        }

        public int TableCount
        {
            get { return m_tables; }
            set
            {
                if (value < 1)
                {
                    m_tables = 1;
                }
                else
                {
                    m_tables = value;
                }
                resetValues();
            }
        }

        public int RoundCount { get { return m_rounds; } }
        public int MinPlaceholders { get { return m_minPlaceholders; } }
        public int MaxPlaceholders { get { return m_maxPlaceholders; } }
        public int GamesInLastRound { get { return m_tablesInLastRound; } }

        public bool IsRunning
        {
            get
            {
                lock (this)
                {
                    return m_running;
                }
            }
        }

        public void Stop()
        {
            lock (this)
            {
                if (!m_running)
                {
                    return;
                }
                m_running = false;
            }
        }

        public void Start()
        {
            lock (this)
            {
                if (m_running)
                {
                    return;
                }
                m_running = true;
            }

            Thread thread = new Thread(delegate ()
            {
                CalculatePlayerTables();
            });
            thread.Start();
        }

        #region Generating initial seatings
        // In initial sittings we make sure every game has the correct number of players.
        // It is not guaranteed that the players play the same number of games.
        // It is not guaranteed that players are sistributed evenly by tables and with each other.

        private int[] generateAvailableSeats()
        {
            int totalGames = (m_rounds - 1) * m_tables + m_tablesInLastRound;
            int[] availableSeats = new int[totalGames];

            // available seats initialization
            for (int g = 0; g < totalGames; ++g)
            {
                availableSeats[g] = 10 - m_minPlaceholders;
            }

            int moreSeats = totalGames * (10 - m_minPlaceholders) - m_players * m_games;
            if (moreSeats > 0)
            {
                int[] rnd_rounds = new int[m_rounds];
                for (int r = 0; r < m_rounds; ++r)
                {
                    rnd_rounds[r] = r;
                }
                for (int r = 0; r < m_rounds; ++r)
                {
                    int r1 = m_rnd.Next(m_rounds);
                    int round = rnd_rounds[r];
                    rnd_rounds[r] = rnd_rounds[r1];
                    rnd_rounds[r1] = round;
                }

                while (moreSeats > 0)
                {
                    for (int r = 0; r < m_rounds && moreSeats > 0; ++r)
                    {
                        int tables = m_tables;
                        int round = rnd_rounds[r];
                        int offset = round * m_tables;
                        if (round == m_rounds - 1)
                        {
                            tables = m_tablesInLastRound;
                        }
                        int t = m_rnd.Next(tables);
                        while (availableSeats[offset + t] != 10 - m_minPlaceholders)
                        {
                            t = m_rnd.Next(tables);

                            int i;
                            for (i = 0; i < tables; ++i)
                            {
                                if (availableSeats[offset + i] == 10 - m_minPlaceholders)
                                {
                                    break;
                                }
                            }
                            if (i >= tables)
                            {
                                t = tables;
                                break;
                            }
                        }

                        if (t < tables)
                        {
                            --availableSeats[round * m_tables + t];
                            --moreSeats;
                        }
                    }
                }
            }
            return availableSeats;
        }

        private void InitPlayerTables()
        {
            m_seatings = new int[m_players, m_rounds];
            int[] availableSeats = generateAvailableSeats();
            for (int t = 0, g = 0, r = 0; g < availableSeats.Length; ++g, ++t)
            {
                if (!IsRunning)
                {
                    return;
                }

                if (t >= m_tables)
                {
                    t = 0;
                    ++r;
                }

                for (int s = 0; s < availableSeats[g]; ++s)
                {
                    int p = m_rnd.Next(m_players);
                    while (m_seatings[p, r] > 0)
                    {
                        p = m_rnd.Next(m_players);
                    }
                    m_seatings[p, r] = t + 1;
                }
            }
        }

        #endregion
        #region Aligning player tables
        // Making sure every player plays the same number of games, and at each table evenly.

        private void AlignPlayersTables()
        {
            int[] freqs = new int[m_tables];
            int[] roundsBuf = new int[m_rounds];
            int[] playersBuf = new int[m_players];
            int minFreq = m_games / m_tables;
            int maxFreq = (m_games + m_tables - 1) / m_tables;
            bool aligned = false;
            for (int i = 0; i < 1000 && !aligned; ++i)
            {
                aligned = true;
                for (int player1 = 0; player1 < m_players; ++player1)
                {
                    if (!IsRunning)
                    {
                        return;
                    }

                    for (int t = 0; t < m_tables; ++t)
                    {
                        freqs[t] = 0;
                    }

                    int gamesCount = 0;
                    for (int r = 0; r < m_rounds; ++r)
                    {
                        int t = m_seatings[player1, r] - 1;
                        if (t >= 0)
                        {
                            ++freqs[t];
                            ++gamesCount;
                        }
                    }

                    int table1 = -1;
                    int table2 = -1;
                    if (gamesCount > m_games)
                    {
                        int maxFr = freqs[0];
                        table1 = 0;
                        for (int t = 1; t < m_tables; ++t)
                        {
                            if (maxFr < freqs[t])
                            {
                                maxFr = freqs[t];
                                table1 = t;
                            }
                        }
                    }
                    else if (gamesCount < m_games)
                    {
                        int minFr = freqs[0];
                        table2 = 0;
                        for (int t = 1; t < m_tables; ++t)
                        {
                            if (minFr > freqs[t])
                            {
                                minFr = freqs[t];
                                table2 = t;
                            }
                        }
                        table1 = -1;
                    }
                    else
                    {
                        for (int t = 0; t < m_tables; ++t)
                        {
                            int f = freqs[t];
                            if (f < minFreq)
                            {
                                table2 = t;
                            }
                            else if (f > maxFreq)
                            {
                                table1 = t;
                            }
                        }

                        if (table1 >= 0)
                        {
                            if (table2 < 0)
                            {
                                for (int t = 0; t < m_tables; ++t)
                                {
                                    if (freqs[t] <= minFreq)
                                    {
                                        table2 = t;
                                        break;
                                    }
                                }
                            }
                        }
                        else if (table2 >= 0)
                        {
                            for (int t = 0; t < m_tables; ++t)
                            {
                                if (freqs[t] >= maxFreq)
                                {
                                    table1 = t;
                                    break;
                                }
                            }
                        }
                        else
                        {
                            continue;
                        }
                    }

                    aligned = false;

                    int rounds = m_rounds;
                    if (table2 >= m_tablesInLastRound || table1 >= m_tablesInLastRound)
                    {
                        --rounds;
                    }
                    int round = 0;
                    for (int r = 0; r < rounds; ++r)
                    {
                        if (m_seatings[player1, r] == table1 + 1)
                        {
                            roundsBuf[round++] = r;
                        }
                    }
                    if (round <= 0)
                    {
                        throw new Exception(String.Format("Aligning players-tables. Rounds count must be positive for table {0} and player {1}", table1, player1));
                    }
                    round = roundsBuf[m_rnd.Next(round)];

                    int player2 = 0;
                    for (int p = 0; p < m_players; ++p)
                    {
                        if (m_seatings[p, round] == table2 + 1)
                        {
                            int fr = 0;
                            for (int r = 0; r < m_rounds; ++r)
                            {
                                if (m_seatings[p, round] == table1 + 1)
                                {
                                    ++fr;
                                }
                            }

                            if (table1 < 0)
                            {
                                if (fr < minFreq)
                                {
                                    playersBuf[player2++] = p;
                                }
                            }
                            else if (fr > m_rounds - m_games)
                            {
                                playersBuf[player2++] = p;
                            }
                        }
                    }

                    if (player2 <= 0)
                    {
                        for (int p = 0; p < m_players; ++p)
                        {
                            if (m_seatings[p, round] == table2 + 1)
                            {
                                playersBuf[player2++] = p;
                            }
                        }
                        if (player2 <= 0)
                        {
                            throw new Exception("Aligning players-tables. Players count must be positive.");
                        }
                    }

                    player2 = playersBuf[m_rnd.Next(player2)];

                    m_seatings[player1, round] = table2 + 1;
                    m_seatings[player2, round] = table1 + 1;
                }
            }
        }

        #endregion
        #region Aligning player vs player
        // Making sure every player plays with any other player as even as possible.

        struct Pair
        {
            public int player1;
            public int player2;
            public int round1;
            public int round2;

            public bool IsValid { get { return player1 >= 0 && player2 >= 0 && round1 >= 0 && round2 >= 0; } }
        }

        void CalcuatePairFrequences()
        {
            for (int r = 0; r <= m_games; ++r)
            {
                m_intermediatePairFreqs[r] = 0;
            }

            for (int p1 = 0; p1 < m_players; ++p1)
            {
                for (int p2 = 0; p2 < p1; ++p2)
                {
                    int count = 0;
                    for (int r = 0; r < m_rounds; ++r)
                    {
                        if (m_seatings[p1, r] == m_seatings[p2, r] && m_seatings[p1, r] > 0)
                        {
                            ++count;
                        }
                    }
                    ++m_intermediatePairFreqs[count];
                }
            }
        }

        private bool IsSitting1Better(int[] freq1, int[] freq2)
        {
            int min = 0;
	        while (min < m_rounds && freq1[min] == 0 && freq2[min] == 0)
	        {
		        ++min;
	        }

            int max = m_games;
        	while (max > 0 && freq1[max] == 0 && freq2[max] == 0)
	        {
		        --max;
	        }

            // m_averagePairFreq + 3: 3 is a magic number meaning that we are ok with values in the range (averageFrequency ± 1). 
            // Where averageFrequency is ((totalSeats - m_games) / (m_players - 1)), where totalSeats is the sum of all seats where current player participated. 
            // In most of the cases totalSeats is (m_games * 10). In order to simplify calculations we set it as (m_games * (10 - m_minPlaceholders))
            while (min < m_averagePairFreq || max > m_averagePairFreq + 3)
	        {
		        if (freq1[max] > freq2[max] || freq1[min] > freq2[min])
		        {
			        return false;
		        }

	            if (freq1[max] < freq2[max] || freq1[min] < freq2[min])
	            {
		            return true;
	            }

	            if (min < m_averagePairFreq)
	            {
		            ++min;
	            }

	            if (max > m_averagePairFreq)
	            {
		            --max;
	            }
            }

	        while (min < max)
	        {
		        int f1 = freq1[max] + freq1[min];
                int f2 = freq2[max] + freq2[min];
		        if (f1 != f2)
		        {
			        return f1 < f2;
		        }

		        ++min;
		        --max;
	        }
        	return false;
        }

        Pair GetRandomPair()
        {
            Pair pair = new Pair();
            pair.player1 = pair.player2 = pair.round1 = pair.round2 = -1;

            int[] candidates = new int[m_players];
            for (int i = 0; i < 10000; ++i)
            {
                int p1 = m_rnd.Next(m_players);
                int r1 = m_rnd.Next(m_rounds);
                int r2 = m_rnd.Next(m_rounds);
                int t1 = m_seatings[p1, r1];
                int t2 = m_seatings[p1, r2];
                while (t1 == t2)
                {
                    r2 = m_rnd.Next(m_rounds);
                    t2 = m_seatings[p1, r2];
                }

                int candidatesCount = 0;
                for (int p = 0; p < m_players; ++p)
                {
                    if (m_seatings[p, r2] == t1 && m_seatings[p, r1] == t2)
                    {
                        candidates[candidatesCount++] = p;
                    }
                }

                if (candidatesCount > 0)
                {
                    pair.player1 = p1;
                    pair.player2 = candidates[m_rnd.Next(candidatesCount)];
                    pair.round1 = r1;
                    pair.round2 = r2;
                    break;
                }
            }
            return pair;
        }

        private void FinalizeSeatings()
        {
            if (m_seatings != null)
            {
                m_finalSeatings = (int[,])m_seatings.Clone();
                if (m_intermediatePairFreqs != null)
                {
                    for (int i = 0; i < m_games; ++i)
                    {
                        m_finalPairFreqs[i] = m_intermediatePairFreqs[i];
                    }
                }
                m_listener.SeatingsUpdated(m_finalSeatings);
            }
        }

        void SwapPair(Pair pair)
        {
            int t1 = m_seatings[pair.player1, pair.round1];
            int t2 = m_seatings[pair.player1, pair.round2];
            m_seatings[pair.player2, pair.round2] = t2;
            m_seatings[pair.player1, pair.round1] = t2;
            m_seatings[pair.player1, pair.round2] = t1;
            m_seatings[pair.player2, pair.round1] = t1;

            CalcuatePairFrequences();
            if (IsSitting1Better(m_intermediatePairFreqs, m_pairFreqs))
            {
                if (IsSitting1Better(m_intermediatePairFreqs, m_finalPairFreqs))
                {
                    m_finalSeatings = (int[,])m_seatings.Clone();
                    for (int i = 0; i <= m_games; ++i)
                    {
                        m_pairFreqs[i] = m_finalPairFreqs[i] = m_intermediatePairFreqs[i];
                    }
                    m_listener.SeatingsUpdated(m_finalSeatings);
                }
                else
                {
                    for (int i = 0; i <= m_games; ++i)
                    {
                        m_pairFreqs[i] = m_intermediatePairFreqs[i];
                    }
                }
            }
            else
            {
                m_seatings[pair.player2, pair.round2] = t1;
                m_seatings[pair.player1, pair.round1] = t1;
                m_seatings[pair.player1, pair.round2] = t2;
                m_seatings[pair.player2, pair.round1] = t2;
            }
        }

        void alignPvP()
        {
            int suffles = 1000;
            int swaps = 1000000;

            InitPlayerTables();
            AlignPlayersTables();

            m_pairFreqs = new int[m_games + 1];
            m_finalPairFreqs = new int[m_games + 1];
            m_intermediatePairFreqs = new int[m_games + 1];
            CalcuatePairFrequences();
            m_finalSeatings = (int[,])m_seatings.Clone();
            for (int i = 0; i <= m_games; ++i)
            {
                m_pairFreqs[i] = m_finalPairFreqs[i] = m_intermediatePairFreqs[i];
            }
            m_listener.SeatingsUpdated(m_finalSeatings);

            for (int i = 0; i < suffles; ++i)
            {
                InitPlayerTables();
                AlignPlayersTables();
                CalcuatePairFrequences();
                if (IsSitting1Better(m_intermediatePairFreqs, m_finalPairFreqs))
                {
                    m_finalSeatings = (int[,])m_seatings.Clone();
                    for (int g = 0; g <= m_games; ++g)
                    {
                        m_pairFreqs[g] = m_finalPairFreqs[g] = m_intermediatePairFreqs[g];
                    }
                    m_listener.SeatingsUpdated(m_finalSeatings);
                }
                else
                {
                    for (int g = 0; g <= m_games; ++g)
                    {
                        m_pairFreqs[g] = m_intermediatePairFreqs[g];
                    }
                }

                Pair pair = GetRandomPair();
                if (pair.IsValid)
                {
                    SwapPair(pair);
                    for (int j = 1; j < swaps; ++j)
                    {
                        pair = GetRandomPair();
                        SwapPair(pair);
                    }
                }
            }
            m_intermediatePairFreqs = null;
            m_finalPairFreqs = null;
            m_pairFreqs = null;
        }
        #endregion
        #region Assigning numbers
        // Making sure numbers are distributed evenly among players.
        // Making sure 4 zones are distributed evenly among players.
        // 4 zones are: 1-2; 3-5; 6-8; 9-10.
        // For example, if every player plays 4 games, it is guaranteed that all games will be played on different numbers. It is not guaranteed that all 4 numbers are in the different zones, but we'll go as close as possible.
        // Another example, if every player plays 10 games, it is guaranteed that every player will play on every number once.
        // Last example, if every player plays 12 games, it is guaranteed that every player will play on every number once. In addition to that every player will play 2 games with 2 random but different numbers from the different zones.

        void alignNumbers()
        {
        }
        #endregion


        private void CalculatePlayerTables()
        {
            try
            {
                alignPvP();
                alignNumbers();
            }
            catch (Exception e)
            {
                m_listener.Error(e);
                Console.WriteLine(e);
            }

            if (m_seatings != null)
            {
                FinalizeSeatings();
                m_finalSeatings = null;
                m_seatings = null;
                m_pairFreqs = null;
                m_finalPairFreqs = null;
            }

            lock (this)
            {
                m_running = false;
            }

            m_listener.CalculationFinished();
            Console.WriteLine("Done");
        }
    }
}
