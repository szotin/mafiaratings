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
        Random m_rnd = new Random((int)DateTimeOffset.Now.ToUnixTimeSeconds());

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
            if (seats_per_round >= m_players)
            {
                m_rounds = m_games;
            }
            else
            {
                m_rounds = ((m_players * m_games + seats_per_round - 1) / (10 * m_tables));
            }

            m_tablesInLastRound = m_tables;
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

        private int[] CalculateTableFreq(int player)
        {
            int[] tables = new int[m_tables];
            for (int r = 0; r < m_rounds; ++r)
            {
                if (m_seatings[player, r] > 0)
                {
                    ++tables[m_seatings[player, r] - 1];
                }
            }
            return tables;
        }

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
                for (int r = 0; r < m_rounds && moreSeats > 0; ++r,--moreSeats)
                {
                    int tables = m_tables;
                    int round = rnd_rounds[r];
                    if (round == m_rounds - 1)
                    {
                        tables = m_tablesInLastRound;
                    }
                    int t = m_rnd.Next(tables);
                    while (availableSeats[round * m_tables + t] != 10 - m_minPlaceholders)
                    {
                        t = m_rnd.Next(m_tables);
                    }
                    --availableSeats[round * m_tables + t];
                }
            }
            return availableSeats;
        }

        private void InitPlayerTables()
        {
            int[] availableSeats = generateAvailableSeats();
        }

        private void CalculatePlayerTables()
        {
            //while (IsRunning)
            //{
                InitPlayerTables();
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
