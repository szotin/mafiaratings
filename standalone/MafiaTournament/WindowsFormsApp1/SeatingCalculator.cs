using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;
using System.Threading;
using System.Threading.Tasks;

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
        private ISeatingCalculatorListener m_listener;
        private bool m_running;

        private int[,] m_seatings = null;
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
            if (seats_per_round >= m_players)
            {
                m_rounds = m_games;
                m_maxPlaceholders = m_minPlaceholders = (seats_per_round - m_players) / m_tables;
                if (m_minPlaceholders * m_tables < seats_per_round - m_players)
                {
                    ++m_maxPlaceholders;
                }
            }
            else
            {
                m_rounds = ((m_players * m_games + seats_per_round - 1) / (10 * m_tables));
                int totalTables = m_rounds * m_tables;
                int totalPlaceholders = totalTables * 10 - m_players * m_games;
                while (totalPlaceholders >= 10 && m_tablesInLastRound > 1)
                {
                    totalPlaceholders -= 10;
                    --totalTables;
                    --m_tablesInLastRound;
                }

                m_maxPlaceholders = m_minPlaceholders = totalPlaceholders / totalTables;
                if (m_minPlaceholders * totalTables < totalPlaceholders)
                {
                    ++m_maxPlaceholders;
                }
            }
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

        #region Calculate player tables

        private int[] generateAvailableSeats()
        {
            int totalGames = (m_rounds - 1) * m_tables + m_tablesInLastRound;
            int[] availableSeats = new int[totalGames];

            // available seats initiali
            for (int g = 0; g < totalGames; ++g)
            {
                availableSeats[g] = 10 - m_minPlaceholders;
            }

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

            int moreSeats = totalGames * (10 - m_minPlaceholders) - m_players * m_games;
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
            return availableSeats;
        }

        private bool resolveDeadEnd(int player, int[] availableSeats)
        {
            for (int r = 0; r < m_rounds; ++r)
            {
                if (m_seatings[player, r] != 0)
                {
                    continue;
                }

                for (int p1 = 0; p1 < player; ++p1)
                {
                    int t = m_seatings[p1, r];
                    if (t <= 0)
                    {
                        continue;
                    }

                    for (int r1 = 0; r1 < m_rounds; ++r1)
                    {
                        if (r1 == r)
                        {
                            continue;
                        }

                        int g = r1 * m_tables + t - 1;
                        if (g >= availableSeats.Length || availableSeats[g] <= 0)
                        {
                            continue;
                        }

                        int t1 = m_seatings[p1, r1];
                        if (t1 == t)
                        {
                            continue;
                        }

                        if (t1 > 0)
                        {
                            int g1 = r * m_tables + t1 - 1;
                            if (availableSeats[g1] <= 0)
                            {
                                continue;
                            }
                            --availableSeats[g1];
                            ++availableSeats[r1 * m_tables + t1 - 1];
                        }

                        m_seatings[p1, r1] = t;
                        m_seatings[p1, r] = t1;
                        --availableSeats[g];
                        ++availableSeats[r * m_tables + t - 1];
                        return true;
                    }
                }
            }
            return false;
        }

        private bool PlacePlayer(int player, int table, int[] availableTables, int[] availableSeats)
        {
            int count = 0;
            while (true)
            {
                for (int g = table, r = 0; g < availableSeats.Length; g += m_tables, ++r)
                {
                    if (availableSeats[g] > 0 && m_seatings[player, r] == 0)
                    {
                        ++count;
                    }
                }
                if (count > 0)
                {
                    break;
                }
                if (!resolveDeadEnd(player, availableSeats))
                {
                    return false;
                }
            }

            count = m_rnd.Next(count);
            for (int g = table, r = 0; g < availableSeats.Length; g += m_tables, ++r)
            {
                if (availableSeats[g] > 0 && m_seatings[player, r] == 0 && count-- <= 0)
                {
                    m_seatings[player, r] = table + 1;
                    --availableTables[table];
                    --availableSeats[g];
                    return true;
                }
            }
            return false;
        }

        private void InitPlayerTables(int recursion)
        {
            m_seatings = new int[m_players, m_rounds];
            int[] availableSeats = generateAvailableSeats();
            int[] availableTables = new int[m_tables];
            for (int t = 0, g = 0; g < availableSeats.Length; ++g, ++t)
            {
                if (t >= m_tables)
                {
                    t = 0;
                }
                availableTables[t] += availableSeats[g];
            }

            for (int p = 0; p < m_players; ++p)
            {
                int gamesRemaining = m_games;
                while (gamesRemaining >= m_tables)
                {
                    for (int t = 0; t < m_tables; ++t)
                    {
                        if (!PlacePlayer(p, t, availableTables, availableSeats))
                        {
                            if (recursion == 100)
                            {
                                throw new Exception("DEATH");
                            }

                            InitPlayerTables(recursion + 1);
                            return;
                        }
                    }
                    gamesRemaining -= m_tables;
                }

                while (gamesRemaining > 0)
                {
                    int t = m_rnd.Next(m_tables);
                    while (availableTables[t] == 0)
                    {
                        t = m_rnd.Next(m_tables);
                    }
                    if (!PlacePlayer(p, t, availableTables, availableSeats))
                    {
                        if (recursion == 100)
                        {
                            throw new Exception("DEATH");
                        }

                        InitPlayerTables(recursion + 1);
                        return;
                    }
                    --gamesRemaining;
                }
            }

            m_listener.SeatingsUpdated((int[,])m_seatings.Clone());
        }

        private void CalculatePlayerTables()
        {
            //while (IsRunning)
            //{
                InitPlayerTables(0);
            //}
            lock (this)
            {
                m_running = false;
            }
            m_listener.CalculationFinished();
            Console.WriteLine("Done");
        }

        #endregion
    }
}
