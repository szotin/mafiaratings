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
    public partial class GameArrangeControl : GameControl
    {
        private struct PlayerControls
        {
            public BorderLabel nameLabel;
            public GameButtons buttons;
            public BorderComboBox nightCombo;
            public BorderLabel numberLabel;
        }

        private PlayerControls[] mPlayerControls;
        private int mNightComboRefreshCount = 0;

        private void InitPlayerControls()
        {
            mPlayerControls = new PlayerControls[10];

            mPlayerControls[0].nameLabel = nameLabel1;
            mPlayerControls[0].buttons = gameButtons1;
            mPlayerControls[0].nightCombo = nightCombo1;
            mPlayerControls[0].numberLabel = numberLabel1;

            mPlayerControls[1].nameLabel = nameLabel2;
            mPlayerControls[1].buttons = gameButtons2;
            mPlayerControls[1].nightCombo = nightCombo2;
            mPlayerControls[1].numberLabel = numberLabel2;

            mPlayerControls[2].nameLabel = nameLabel3;
            mPlayerControls[2].buttons = gameButtons3;
            mPlayerControls[2].nightCombo = nightCombo3;
            mPlayerControls[2].numberLabel = numberLabel3;

            mPlayerControls[3].nameLabel = nameLabel4;
            mPlayerControls[3].buttons = gameButtons4;
            mPlayerControls[3].nightCombo = nightCombo4;
            mPlayerControls[3].numberLabel = numberLabel4;

            mPlayerControls[4].nameLabel = nameLabel5;
            mPlayerControls[4].buttons = gameButtons5;
            mPlayerControls[4].nightCombo = nightCombo5;
            mPlayerControls[4].numberLabel = numberLabel5;

            mPlayerControls[5].nameLabel = nameLabel6;
            mPlayerControls[5].buttons = gameButtons6;
            mPlayerControls[5].nightCombo = nightCombo6;
            mPlayerControls[5].numberLabel = numberLabel6;

            mPlayerControls[6].nameLabel = nameLabel7;
            mPlayerControls[6].buttons = gameButtons7;
            mPlayerControls[6].nightCombo = nightCombo7;
            mPlayerControls[6].numberLabel = numberLabel7;

            mPlayerControls[7].nameLabel = nameLabel8;
            mPlayerControls[7].buttons = gameButtons8;
            mPlayerControls[7].nightCombo = nightCombo8;
            mPlayerControls[7].numberLabel = numberLabel8;

            mPlayerControls[8].nameLabel = nameLabel9;
            mPlayerControls[8].buttons = gameButtons9;
            mPlayerControls[8].nightCombo = nightCombo9;
            mPlayerControls[8].numberLabel = numberLabel9;

            mPlayerControls[9].nameLabel = nameLabel10;
            mPlayerControls[9].buttons = gameButtons10;
            mPlayerControls[9].nightCombo = nightCombo10;
            mPlayerControls[9].numberLabel = numberLabel10;
        }

        public GameArrangeControl()
        {
            InitializeComponent();
            InitPlayerControls();
        }

        public static bool IsMyGameState(GameState state)
        {
            return (state == GameState.Night0Arrange);
        }

        public override bool IsActive
        {
            get { return IsMyGameState(Database.Game.State); }
        }

        protected override void OnReload()
        {
            Game game = Database.Game;
            timerControl.Enabled = true;

            ++mNightComboRefreshCount;
            bool hasDummy = (game.GetDummyPlayer() != null);
            for (int i = 0; i < 10; ++i)
            {
                GamePlayer player = game.Players[i];
                PlayerControls controls = mPlayerControls[i];

                controls.nameLabel.String = player.Name;
                controls.buttons.PlayerNum = i;

                controls.nightCombo.Items.Clear();

                if (player.IsDummy)
                {
                    controls.nightCombo.Items.Add(String.Format(Properties.Resources.NightNum, 1));
                    controls.nightCombo.SelectedIndex = 0;
                    controls.nightCombo.Enabled = false;
                }
                else
                {
                    controls.nightCombo.Items.Add(string.Empty);
                    if (!hasDummy)
                    {
                        if (player.HasImmunity)
                        {
                            controls.nightCombo.Items.Add(Properties.Resources.Immunity);
                        }
                        else
                        {
                            controls.nightCombo.Items.Add(String.Format(Properties.Resources.NightNum, 1));
                        }
                    }
                    for (int j = 2; j <= 5; ++j)
                    {
                        controls.nightCombo.Items.Add(String.Format(Properties.Resources.NightNum, j));
                    }
                }
            }
            --mNightComboRefreshCount;
        }

        protected override void OnGameStateChange()
        {
            Game game = Database.Game;
            bool hasDummy = (game.GetDummyPlayer() != null);
            ++mNightComboRefreshCount;
            for (int i = 0; i < 10; ++i)
            {
                GamePlayer player = game.Players[i];
                PlayerControls controls = mPlayerControls[i];

                if (player.IsAlive)
                {
                    if (player.Role == GamePlayerRole.Mafia)
                    {
                        SetControlState(controls.nameLabel, ALIVE, true);
                        SetControlState(controls.buttons, ALIVE, !player.IsDummy);
                        SetControlState(controls.nightCombo, ALIVE, true);
                        SetControlState(controls.numberLabel, HIGHLIGHT, true);
                    }
                    else if (player.Role == GamePlayerRole.Don)
                    {
                        SetControlState(controls.nameLabel, HIGHLIGHT, true);
                        SetControlState(controls.buttons, HIGHLIGHT, !player.IsDummy);
                        SetControlState(controls.nightCombo, HIGHLIGHT, true);
                        SetControlState(controls.numberLabel, HIGHLIGHT, true);
                    }
                    else
                    {
                        SetControlState(controls.nameLabel, ALIVE, true);
                        SetControlState(controls.buttons, ALIVE, !player.IsDummy);
                        SetControlState(controls.nightCombo, ALIVE, true);
                        SetControlState(controls.numberLabel, ALIVE, true);
                    }
                }
                else
                {
                    SetControlState(controls.nameLabel, DEAD, true);
                    SetControlState(controls.buttons, DEAD, false);
                    SetControlState(controls.nightCombo, DEAD, false);
                    SetControlState(controls.numberLabel, DEAD, true);
                }
                controls.buttons.UpdatePlayer();

                if (!player.IsDummy)
                {
                    if (player.Arranged < 0)
                    {
                        controls.nightCombo.SelectedIndex = 0;
                    }
                    else if (hasDummy)
                    {
                        controls.nightCombo.SelectedIndex = player.Arranged;
                    }
                    else
                    {
                        controls.nightCombo.SelectedIndex = player.Arranged + 1;
                    }
                }
            }
            --mNightComboRefreshCount;
        }

        private void nightComboBox_SelectedIndexChanged(object sender, EventArgs e)
        {
            if (mNightComboRefreshCount > 0)
            {
                return;
            }

            Game game = Database.Game;
            BorderComboBox box = null;
            int playerNum;
            for (playerNum = 0; playerNum < 10; ++playerNum)
            {
                PlayerControls controls = mPlayerControls[playerNum];
                if (controls.nightCombo.IsMyEvent(sender))
                {
                    box = controls.nightCombo;
                    break;
                }
            }

            if (box == null)
            {
                return;
            }

            bool hasDummy = (game.GetDummyPlayer() != null);
            if (hasDummy)
            {
                game.ArrangePlayer(playerNum, box.SelectedIndex);
            }
            else
            {
                game.ArrangePlayer(playerNum, box.SelectedIndex - 1);
            }

            ++mNightComboRefreshCount;
            for (int i = 0; i < 10; ++i)
            {
                GamePlayer player = game.Players[i];
                if (player.IsDummy)
                {
                    continue;
                }

                PlayerControls controls = mPlayerControls[i];
                if (player.Arranged < 0)
                {
                    controls.nightCombo.SelectedIndex = 0;
                }
                else if (hasDummy)
                {
                    controls.nightCombo.SelectedIndex = player.Arranged;
                }
                else
                {
                    controls.nightCombo.SelectedIndex = player.Arranged + 1;
                }
            }
            --mNightComboRefreshCount;
        }
    }
}
