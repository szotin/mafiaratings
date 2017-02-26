using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Drawing;
using System.Data;
using System.Linq;
using System.Text;
using System.Windows.Forms;
using MafiaRatings.GameObjects;

namespace MafiaRatings
{
    public partial class GameButtons : UserControl
    {
        private int mPlayerNum = -1;

        public GameButtons()
        {
            InitializeComponent();
        }

        private void UpdateWarnings()
        {
            GamePlayer player = Database.Game.Players[mPlayerNum];
            switch (player.Warnings)
            {
                case 1:
                    warningsLabel.Text = Properties.Resources.Warning;
                    break;
                case 2:
                case 3:
                    warningsLabel.Text = String.Format(Properties.Resources.Warnings, player.Warnings);
                    break;
                default:
                    warningsLabel.Text = string.Empty;
                    break;
            }
        }

        private void warningButton_Click(object sender, EventArgs e)
        {
            Database.Game.WarnPlayer(mPlayerNum);
            UpdateWarnings();
        }

        private void suisideButton_Click(object sender, EventArgs e)
        {
            Database.Game.Suicide(mPlayerNum);
        }

        private void kickOutButton_Click(object sender, EventArgs e)
        {
            Database.Game.KickOut(mPlayerNum);
        }

        public void UpdatePlayer()
        {
            if (mPlayerNum < 0 || mPlayerNum >= 10)
            {
                toolTip.SetToolTip(warningButton, string.Empty);
                toolTip.SetToolTip(suisideButton, string.Empty);
                toolTip.SetToolTip(kickOutButton, string.Empty);
                warningsLabel.Text = string.Empty;
            }
            else
            {
                GamePlayer player = Database.Game.Players[mPlayerNum];
                string nick = player.Name;
                toolTip.SetToolTip(warningButton, string.Format(Properties.Resources.Warn, nick));
                toolTip.SetToolTip(suisideButton, string.Format(Properties.Resources.Suiside, nick));
                toolTip.SetToolTip(kickOutButton, string.Format(Properties.Resources.KickOut, nick));
                UpdateWarnings();
            }
        }

        public int PlayerNum
        {
            get { return mPlayerNum; }
            set
            {
                if (mPlayerNum != value)
                {
                    mPlayerNum = value;
                    UpdatePlayer();
                }
            }
        }

        private void GameButtons_EnabledChanged(object sender, EventArgs e)
        {
            warningsLabel.Visible =
            warningButton.Visible =
            suisideButton.Visible =
            kickOutButton.Visible = Enabled;
        }
    }
}
