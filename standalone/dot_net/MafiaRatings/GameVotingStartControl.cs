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
    public partial class GameStartVotingControl : GameControl
    {
        private struct PlayerControls
        {
            public BorderLabel nameLabel;
            public BorderLabel numberLabel;
        }

        private PlayerControls[] mPlayerControls;

        private void InitPlayerControls()
        {
            mPlayerControls = new PlayerControls[10];

            mPlayerControls[0].nameLabel = nameLabel1;
            mPlayerControls[0].numberLabel = numberLabel1;

            mPlayerControls[1].nameLabel = nameLabel2;
            mPlayerControls[1].numberLabel = numberLabel2;

            mPlayerControls[2].nameLabel = nameLabel3;
            mPlayerControls[2].numberLabel = numberLabel3;

            mPlayerControls[3].nameLabel = nameLabel4;
            mPlayerControls[3].numberLabel = numberLabel4;

            mPlayerControls[4].nameLabel = nameLabel5;
            mPlayerControls[4].numberLabel = numberLabel5;

            mPlayerControls[5].nameLabel = nameLabel6;
            mPlayerControls[5].numberLabel = numberLabel6;

            mPlayerControls[6].nameLabel = nameLabel7;
            mPlayerControls[6].numberLabel = numberLabel7;

            mPlayerControls[7].nameLabel = nameLabel8;
            mPlayerControls[7].numberLabel = numberLabel8;

            mPlayerControls[8].nameLabel = nameLabel9;
            mPlayerControls[8].numberLabel = numberLabel9;

            mPlayerControls[9].nameLabel = nameLabel10;
            mPlayerControls[9].numberLabel = numberLabel10;
        }

        public GameStartVotingControl()
        {
            InitializeComponent();
            InitPlayerControls();
        }

        public static bool IsMyGameState(GameState state)
        {
            return state == GameState.VotingStart;
        }

        public override bool IsActive
        {
            get { return IsMyGameState(Database.Game.State); }
        }

        protected override void OnGameStateChange()
        {
            Game game = Database.Game;
            titleLabel.Text = string.Format(Properties.Resources.DayNum, game.Round + 1);

            string tipText = string.Empty;
            int markedPlayer = -1;
            int numPlayers = game.NumPlayers;
            GamePlayer player;

            switch (game.CurrentVoting.Nominants.Count)
            {
                case 0:
                    if (game.KillingThisDay)
                    {
                        tipText = Properties.Resources.NoNomination;
                    }
                    else
                    {
                        tipText = Properties.Resources.NoNomination1;
                    }
                    break;
                case 1:
                    markedPlayer = game.CurrentVoting.Nominants[0].PlayerNum;
                    player = game.Players[markedPlayer];
                    if (game.KillingThisDay)
                    {
                        tipText = string.Format(Properties.Resources.OneNomination, player, player.IsMale ? Properties.Resources.him : Properties.Resources.her);
                    }
                    else
                    {
                        tipText = string.Format(Properties.Resources.OneNomination1, player, player.IsMale ? Properties.Resources.him : Properties.Resources.her);
                    }
                    break;
                default:
                    tipText = Properties.Resources.NominatedPlayers;
                    break;
            }
            tipLabel.Text = tipText;

            int i = 0;
            for (; i < game.CurrentVoting.Nominants.Count; ++i)
            {
                PlayerControls controls = mPlayerControls[i];
                int playerNum = game.CurrentVoting.Nominants[i].PlayerNum;

                controls.nameLabel.String = game.Players[playerNum].ToString();
                controls.numberLabel.String = (playerNum + 1).ToString() + ":";

                SetControlState(controls.nameLabel, ALIVE, true);
                SetControlState(controls.numberLabel, ALIVE, true);
            }

            for (; i < 10; ++i)
            {
                PlayerControls controls = mPlayerControls[i];
                controls.nameLabel.Visible = controls.numberLabel.Visible = false;
            }
        }
    }
}
