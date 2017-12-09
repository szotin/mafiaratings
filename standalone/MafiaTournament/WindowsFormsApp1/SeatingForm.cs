using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Data;
using System.Drawing;
using System.Linq;
using System.Text;
using System.Threading.Tasks;
using System.Windows.Forms;

namespace TournamentSeating
{
    public partial class SeatingForm : Form, ISeatingCalculatorListener
    {
        private SeatingCalculator m_calculator = null;

        public SeatingForm()
        {
            InitializeComponent();
            m_calculator = new SeatingCalculator((int)playersUpDown.Value, (int)gamesUpDown.Value, (int)tablesUpDown.Value, this);
            ResetGrid();
        }

        private void playersUpDown_ValueChanged(object sender, EventArgs e)
        {
            m_calculator.PlayerCount = (int)playersUpDown.Value;
            ResetGrid();
        }

        private void gamesUpDown_ValueChanged(object sender, EventArgs e)
        {
            m_calculator.GamesPerPlayer = (int)gamesUpDown.Value;
            ResetGrid();
        }

        private void tablesUpDown_ValueChanged(object sender, EventArgs e)
        {
            m_calculator.TableCount = (int)tablesUpDown.Value;
            ResetGrid();
        }

        private void ResetGrid()
        {
            grid.Columns.Clear();
            grid.Rows.Clear();
            int rounds = m_calculator.RoundCount;
            grid.Columns.Add("Player", "");
            DataGridViewColumn column = grid.Columns[0];
            column.Width = 32;
            column.SortMode = DataGridViewColumnSortMode.NotSortable;
            for (int i = 1; i <= rounds; ++i)
            {
                grid.Columns.Add("Round" + i, "Round " + i);
                column = grid.Columns[i];
                column.Width = 80;
                column.HeaderCell.Style.Alignment = DataGridViewContentAlignment.MiddleCenter;
                column.SortMode = DataGridViewColumnSortMode.NotSortable;
            }

            for (int i = 1; i <= m_calculator.PlayerCount; ++i)
            {
                grid.Rows.Add("" + i);
            }
        }

        private void calculateButton_Click(object sender, EventArgs e)
        {
            StringBuilder text = new StringBuilder();
            if (m_calculator.TableCount > 1)
            {
                text.Append(String.Format("You are about to generate seatings for {0} players tournament using {1} tables.", m_calculator.PlayerCount, m_calculator.TableCount));
            }
            else
            {
                text.Append(String.Format("You are about to generate seatings for {0} players tournament using 1 table.", m_calculator.PlayerCount));
            }

            if (m_calculator.GamesPerPlayer > 1)
            {
                text.Append(String.Format(" Every player will play {0} games.", m_calculator.GamesPerPlayer));
            }
            else
            {
                text.Append(String.Format(" Every player will play {0} games.", m_calculator.GamesPerPlayer));
            }

            if (m_calculator.RoundCount > 1)
            {
                text.Append(String.Format("\n\nIt will take {0} rounds", m_calculator.RoundCount));
            }
            else
            {
                text.Append(String.Format("\n\nIt will take 1 round", m_calculator.RoundCount));
            }

            if (m_calculator.MaxPlaceholders > 0)
            {
                if (m_calculator.MinPlaceholders == 0)
                {
                    text.Append(String.Format(" and you will need 1 placeholder in some games"));
                }
                else if (m_calculator.MaxPlaceholders != m_calculator.MinPlaceholders)
                {
                    text.Append(String.Format(" and you will need from {0} to {1} placeholders in every game", m_calculator.MinPlaceholders, m_calculator.MaxPlaceholders));
                }
                else if (m_calculator.MaxPlaceholders > 1)
                {
                    text.Append(String.Format(" and you will need from {0} placeholders in every game", m_calculator.MaxPlaceholders));
                }
                else
                {
                    text.Append(String.Format(" and you will need 1 placeholder in every game", m_calculator.MaxPlaceholders));
                }
            }

            if (m_calculator.GamesInLastRound != m_calculator.TableCount)
            {
                if (m_calculator.GamesInLastRound > 1)
                {
                    text.Append(String.Format(". There will be only {0} games in the last round", m_calculator.GamesInLastRound));
                }
                else
                {
                    text.Append(String.Format(". There will be only 1 game in the last round", m_calculator.GamesInLastRound));
                }
            }

            text.Append(".\n\nIs this what you need?");
            if (MessageBox.Show(text.ToString(), "Calculating tournament", MessageBoxButtons.YesNo) != DialogResult.Yes)
            {
                return;
            }

            if (m_calculator.IsRunning)
            {
                m_calculator.Stop();
                calculateButton.Text = "Stopping...";
                calculateButton.Enabled = false;
            }
            else
            {
                calculateButton.Text = "Stop";
                calculateButton.Enabled = true;
                tablesUpDown.Enabled = gamesUpDown.Enabled = playersUpDown.Enabled = false;
                m_calculator.Start();
            }
        }

        void ISeatingCalculatorListener.SeatingsUpdated()
        {

        }

        private void CalculationEnd()
        {
            tablesUpDown.Enabled = gamesUpDown.Enabled = playersUpDown.Enabled = true;
            calculateButton.Text = "Calculate";
            calculateButton.Enabled = true;
        }

        void ISeatingCalculatorListener.CalculationFinished()
        {
            if (InvokeRequired)
            {
                Invoke(new MethodInvoker(delegate () { CalculationEnd(); }));
            }
            else
            {
                CalculationEnd();
            }
        }
    }
}
