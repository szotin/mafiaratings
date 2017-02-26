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
    public partial class GameNightControl : GameControl
    {
        private struct PlayerControls
        {
            public BorderLabel nameLabel;
            public BorderRadioButton check;
            public GameButtons buttons;
            public BorderLabel numberLabel;
        }

        private PlayerControls[] mPlayerControls;

        private void InitPlayerControls()
        {
            mPlayerControls = new PlayerControls[10];

            mPlayerControls[0].nameLabel = nameLabel1;
            mPlayerControls[0].check = checkRadioButton1;
            mPlayerControls[0].buttons = gameButtons1;
            mPlayerControls[0].numberLabel = numberLabel1;

            mPlayerControls[1].nameLabel = nameLabel2;
            mPlayerControls[1].check = checkRadioButton2;
            mPlayerControls[1].buttons = gameButtons2;
            mPlayerControls[1].numberLabel = numberLabel2;

            mPlayerControls[2].nameLabel = nameLabel3;
            mPlayerControls[2].check = checkRadioButton3;
            mPlayerControls[2].buttons = gameButtons3;
            mPlayerControls[2].numberLabel = numberLabel3;

            mPlayerControls[3].nameLabel = nameLabel4;
            mPlayerControls[3].check = checkRadioButton4;
            mPlayerControls[3].buttons = gameButtons4;
            mPlayerControls[3].numberLabel = numberLabel4;

            mPlayerControls[4].nameLabel = nameLabel5;
            mPlayerControls[4].check = checkRadioButton5;
            mPlayerControls[4].buttons = gameButtons5;
            mPlayerControls[4].numberLabel = numberLabel5;

            mPlayerControls[5].nameLabel = nameLabel6;
            mPlayerControls[5].check = checkRadioButton6;
            mPlayerControls[5].buttons = gameButtons6;
            mPlayerControls[5].numberLabel = numberLabel6;

            mPlayerControls[6].nameLabel = nameLabel7;
            mPlayerControls[6].check = checkRadioButton7;
            mPlayerControls[6].buttons = gameButtons7;
            mPlayerControls[6].numberLabel = numberLabel7;

            mPlayerControls[7].nameLabel = nameLabel8;
            mPlayerControls[7].check = checkRadioButton8;
            mPlayerControls[7].buttons = gameButtons8;
            mPlayerControls[7].numberLabel = numberLabel8;

            mPlayerControls[8].nameLabel = nameLabel9;
            mPlayerControls[8].check = checkRadioButton9;
            mPlayerControls[8].buttons = gameButtons9;
            mPlayerControls[8].numberLabel = numberLabel9;

            mPlayerControls[9].nameLabel = nameLabel10;
            mPlayerControls[9].check = checkRadioButton10;
            mPlayerControls[9].buttons = gameButtons10;
            mPlayerControls[9].numberLabel = numberLabel10;
        }

        public GameNightControl()
        {
            InitializeComponent();
            InitPlayerControls();
            for (int i = 0; i < 10; ++i)
            {
                mPlayerControls[i].check.String = Properties.Resources.Check;
            }
            noCheckRadioButton.String = Properties.Resources.NoCheck;
        }

        public static bool IsMyGameState(GameState state)
        {
            switch (state)
            {
                case GameState.NightDonCheckStart:
                case GameState.NightDonCheck:
                case GameState.NightSheriffCheckStart:
                case GameState.NightSheriffCheck:
                    return true;
            }
            return false;
        }

        public override bool IsActive
        {
            get { return IsMyGameState(Database.Game.State); }
        }

        protected override void OnReload()
        {
            Game game = Database.Game;
            for (int i = 0; i < 10; ++i)
            {
                GamePlayer player = game.Players[i];
                PlayerControls controls = mPlayerControls[i];

                controls.nameLabel.String = player.Name;
                controls.buttons.PlayerNum = i;
            }
            titleLabel.Text = string.Format(Properties.Resources.NightNum, game.Round + 1);
        }

        protected override void OnGameStateChange()
        {
            Game game = Database.Game;
            bool checking = false;
            int markedPlayer = -1;
            switch (game.State)
            {
                case GameState.NightDonCheckStart:
                    if (game.DonCanCheck)
                    {
                        tipLabel.Text = Properties.Resources.DonChecking;
                        checking = true;
                    }
                    else
                    {
                        tipLabel.Text = Properties.Resources.NoDon;
                    }
                    break;
                case GameState.NightDonCheck:
                    markedPlayer = game.CurrentNominant;
                    if (game.Players[game.CurrentNominant].Role == GamePlayerRole.Sheriff)
                    {
                        tipLabel.Text = Properties.Resources.YesToDon;
                    }
                    else
                    {
                        tipLabel.Text = Properties.Resources.NoToDon;
                    }
                    break;
                case GameState.NightSheriffCheckStart:
                    if (game.SheriffCanCheck)
                    {
                        tipLabel.Text = Properties.Resources.SheriffChecking;
                        checking = true;
                    }
                    else
                    {
                        tipLabel.Text = Properties.Resources.NoSheriff;
                    }
                    break;
                case GameState.NightSheriffCheck:
                    markedPlayer = game.CurrentNominant;
                    if (game.Players[game.CurrentNominant].Role == GamePlayerRole.Mafia || game.Players[game.CurrentNominant].Role == GamePlayerRole.Don)
                    {
                        tipLabel.Text = Properties.Resources.YesToSheriff;
                    }
                    else
                    {
                        tipLabel.Text = Properties.Resources.NoToSheriff;
                    }
                    break;
            }

            bool noCheckChecked = true;
            for (int i = 0; i < 10; ++i)
            {
                GamePlayer player = game.Players[i];
                PlayerControls controls = mPlayerControls[i];

                int color;
                if (i == markedPlayer)
                {
                    color = HIGHLIGHT;
                }
                else if (player.IsAlive)
                {
                    color = ALIVE;
                }
                else
                {
                    color = DEAD;
                }

                SetControlState(controls.nameLabel, color, true);
                SetControlState(controls.numberLabel, color, true);

                bool checkingEnabled = false;
                bool checkingChecked = false;
                if (checking)
                {
                    checkingEnabled = true;
                    if (game.State == GameState.NightDonCheckStart)
                    {
                        if (player.IsDummy)
                        {
                            checkingEnabled = false;
                        }
                        else if (player.DonCheck == game.Round)
                        {
                            noCheckChecked = false;
                            checkingChecked = true;
                        }
                        else if (player.Role == GamePlayerRole.Mafia || player.Role == GamePlayerRole.Don || player.DonCheck >= 0)
                        {
                            checkingEnabled = false;
                        }
                    }
                    else if (game.State == GameState.NightSheriffCheckStart)
                    {
                        if (player.IsDummy)
                        {
                            checkingEnabled = false;
                        }
                        else if (player.SheriffCheck == game.Round)
                        {
                            noCheckChecked = false;
                            checkingChecked = true;
                        }
                        else if (player.Role == GamePlayerRole.Sheriff || player.SheriffCheck >= 0)
                        {
                            checkingEnabled = false;
                        }
                    }
                }
                controls.check.Checked = checkingChecked;
                SetControlState(controls.check, color, checkingEnabled);

                if (player.IsAlive)
                {
                    SetControlState(controls.buttons, color, true);
                    controls.buttons.UpdatePlayer();
                }
                else
                {
                    SetControlState(controls.buttons, color, false);
                }
            }

            noCheckPanel.Visible = checking;
            noCheckRadioButton.Checked = noCheckChecked;
        }

        private void checkRadioButton_CheckedChanged(object sender, EventArgs e)
        {
            if (Initializing)
            {
                return;
            }

            Game game = Database.Game;
            Initializing = true;
            if (!noCheckRadioButton.IsMyEvent(sender))
            {
                noCheckRadioButton.Checked = false;
            }
            else if (game.State == GameState.NightDonCheckStart)
            {
                game.DonChecks(-1);
            }
            else
            {
                game.SheriffChecks(-1);
            }

            for (int i = 0; i < 10; ++i)
            {
                PlayerControls controls = mPlayerControls[i];
                if (!controls.check.IsMyEvent(sender))
                {
                    controls.check.Checked = false;
                }
                else if (game.State == GameState.NightDonCheckStart)
                {
                    game.DonChecks(i);
                }
                else
                {
                    game.SheriffChecks(i);
                }
            }
            Initializing = false;
        }
    }
}
